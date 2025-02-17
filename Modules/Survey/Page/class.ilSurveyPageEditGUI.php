<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

/**
 * Survey per page view
 *
 * @author		Jörg Lützenkirchen <luetzenkirchen@leifos.com
 * @ilCtrl_Calls ilSurveyPageEditGUI:
 */
class ilSurveyPageEditGUI
{
    protected \ILIAS\Survey\PrintView\GUIService $print;
    protected \ILIAS\HTTP\Services $http;
    protected \ILIAS\DI\UIServices $ui;
    protected bool $suppress_clipboard_msg = false;
    protected string $pgov;
    protected \ILIAS\Survey\Editing\EditingGUIRequest $svy_request;
    protected \ILIAS\Survey\Editing\EditManager $edit_manager;
    protected ilCtrl $ctrl;
    protected ilRbacSystem $rbacsystem;
    protected ilDBInterface $db;
    protected ilTabsGUI $tabs;
    protected ilGlobalTemplateInterface $tpl;
    protected ilToolbarGUI $toolbar;
    protected ilObjUser $user;
    protected int $ref_id;
    protected ilLanguage $lng;
    protected ilObjSurvey $object;
    protected ilSurveyEditorGUI $editor_gui;
    protected ?int $current_page = null;
    protected bool $has_previous_page;
    protected bool $has_next_page;
    protected bool $has_datasets;
    protected bool $use_pool;
    protected ilLogger $log;

    public function __construct(
        ilObjSurvey $a_survey,
        ilSurveyEditorGUI $a_survey_editor_gui
    ) {
        global $DIC;

        $this->lng = $DIC->language();
        $this->ctrl = $DIC->ctrl();
        $this->rbacsystem = $DIC->rbac()->system();
        $this->db = $DIC->database();
        $this->tabs = $DIC->tabs();
        $this->tpl = $DIC["tpl"];
        $this->toolbar = $DIC->toolbar();
        $this->user = $DIC->user();
        $this->editor_gui = $a_survey_editor_gui;
        $this->ref_id = $a_survey->getRefId();
        $this->object = $a_survey;
        $this->log = ilLoggerFactory::getLogger("svy");
        $this->edit_manager = $DIC->survey()
            ->internal()
            ->domain()
            ->edit();
        $this->svy_request = $DIC->survey()
            ->internal()
            ->gui()
            ->editing()
            ->request();
        $this->pgov = $this->svy_request->getTargetPosition();
        $this->ui = $DIC->ui();
        $this->http = $DIC->http();
        $this->print = $DIC->survey()
            ->internal()
            ->gui()
            ->print();
    }

    public function executeCommand(): void
    {
        $lng = $this->lng;
        $ilCtrl = $this->ctrl;
        $rbacsystem = $this->rbacsystem;

        $cmd = $ilCtrl->getCmd("renderPage");
        $next_class = $ilCtrl->getNextClass($this);

        switch ($next_class) {
            default:
                $this->determineCurrentPage();

                $has_content = false;

                if ($rbacsystem->checkAccess("write", $this->ref_id)) {
                    // add page?
                    if ($this->svy_request->getNewId()) {
                        $this->insertNewQuestion($this->svy_request->getNewId());
                        $ilCtrl->setParameter($this->editor_gui, "pgov", $this->current_page);
                        $ilCtrl->redirect($this, "questions");
                    }

                    // subcommands
                    if ($this->svy_request->getHForm("subcmd")) {
                        $subcmd = $this->svy_request->getHForm("subcmd");

                        // make sure that it is set for current and next requests
                        $ilCtrl->setParameter($this->editor_gui, "pgov", $this->current_page);
                        $this->pgov = $this->current_page;
                        // this is necessary, since ILIAS 7
                        // wrote the REQUEST parameter
                        $this->editor_gui->setRequestedPgov($this->pgov);

                        $id = explode("_", $this->svy_request->getHForm("node"));
                        $id = (int) ($id[1] ?? 0);

                        // multi operation
                        if (strpos($this->svy_request->getHForm("subcmd"), "multi") === 0) {
                            if ($this->svy_request->getHForm("multi")) {
                                // removing types as we only allow questions anyway
                                $id = array();
                                foreach (explode(";", $this->svy_request->getHForm("multi")) as $item) {
                                    $item_arr = explode("_", $item);
                                    $id[] = (int) array_pop($item_arr);
                                }

                                if ($subcmd === "multiDelete") {
                                    $subcmd = "deleteQuestion";
                                }
                            } elseif ($subcmd === "multiDelete") {
                                // #9525
                                $this->tpl->setOnScreenMessage('failure', $lng->txt("no_checkbox"), true);
                                $ilCtrl->redirect($this, "renderPage");
                            } else {
                                $this->tpl->setOnScreenMessage('failure', $lng->txt("no_checkbox"));
                            }
                        }

                        if (strpos($subcmd, "addQuestion") === 0) {
                            $type = explode("_", $subcmd);
                            $type = (int) $type[1];
                            $has_content = $this->addQuestion(
                                $type,
                                $this->object->getPoolUsage(),
                                $id,
                                $this->svy_request->getHForm("node")
                            );
                        } else {
                            switch ($subcmd) {
                                case "deleteQuestion":
                                    $has_content = true;
                                    $ids = (!is_array($id))
                                        ? [$id]
                                        : $id;
                                    $this->deleteQuestion($ids);
                                    break;
                                default:
                                    if ($id === 0 && in_array($subcmd, ["multiCut", "multiCopy"])) {
                                        $id = [];
                                    }
                                    $has_content = $this->$subcmd($id, $this->svy_request->getHForm("node"));
                                    break;

                            }
                        }
                    }
                }

                if (!$has_content) {
                    $this->$cmd();
                }
                break;
        }
    }

    public function determineCurrentPage(): void
    {
        $current_page = null;
        $jump = $this->svy_request->getJump();
        if ($jump != "") {
            $jump_parts = explode("__", $jump);
            $current_page = (int) ($jump_parts[0] ?? 1);
        }

        if (!$current_page) {
            $current_page = $this->pgov;
        }
        if (!$current_page) {
            $current_page = $this->svy_request->getPage();
        }
        if (!$current_page) {
            $current_page = 1;
        }
        $this->current_page = (int) $current_page;
    }

    /**
     * Add new question to survey (database part)
     * @todo: move out of GUI class, see also ilObjSurvey->insertQuestion
     * @return int survey question id
     */
    protected function appendNewQuestionToSurvey(
        int $a_new_id,
        bool $a_duplicate = true,
        bool $a_force_duplicate = false
    ): int {
        $ilDB = $this->db;
        $this->log->debug("append question, id: " . $a_new_id . ", duplicate: " . $a_duplicate . ", force: " . $a_force_duplicate);

        // get maximum sequence index in test
        $result = $ilDB->queryF(
            "SELECT survey_question_id FROM svy_svy_qst WHERE survey_fi = %s",
            array('integer'),
            array($this->object->getSurveyId())
        );
        $sequence = $result->numRows();

        // create duplicate if pool question (or forced for question blocks copy)
        if ($a_duplicate) {
            // this does nothing if this is not a pool question and $a_force_duplicate is false
            $survey_question_id = $this->object->duplicateQuestionForSurvey($a_new_id, $a_force_duplicate);
        }
        // used by copy & paste
        else {
            $survey_question_id = $a_new_id;
        }

        // check if question is not already in the survey, see #22018
        if ($this->object->isQuestionInSurvey($survey_question_id)) {
            return $survey_question_id;
        }
        // append to survey
        $next_id = $ilDB->nextId('svy_svy_qst');
        $affectedRows = $ilDB->manipulateF(
            "INSERT INTO svy_svy_qst (survey_question_id, survey_fi," .
            "question_fi, sequence, tstamp) VALUES (%s, %s, %s, %s, %s)",
            array('integer', 'integer', 'integer', 'integer', 'integer'),
            array($next_id, $this->object->getSurveyId(), $survey_question_id, $sequence, time())
        );

        $this->log->debug("insert svy_svy_qst, id: " . $next_id . ", qfi: " . $survey_question_id . ", seq: " . $sequence);

        return $survey_question_id;
    }

    /**
     * Add new question to survey
     */
    public function insertNewQuestion(
        int $a_new_id
    ): void {
        $lng = $this->lng;
        if (!SurveyQuestion::_isComplete($a_new_id)) {
            $this->tpl->setOnScreenMessage('failure', $lng->txt("survey_error_insert_incomplete_question"));
        } else {
            $a_new_id = $this->appendNewQuestionToSurvey($a_new_id);
            $this->object->loadQuestionsFromDb();
            $this->object->saveCompletionStatus();

            $pos = $this->svy_request->getTargetQuestionPosition();

            // a[fter]/b[efore] on same page
            if (substr($pos, -1) !== "c") {
                // block handling
                $current = $this->object->getSurveyPages();
                $current = $current[$this->current_page - 1];
                if (count($current) === 1) {
                    // as questions are moved to first block question
                    // always use existing as first
                    // the new question is moved later on (see below)
                    $this->object->createQuestionblock(
                        $this->getAutoBlockTitle(),
                        true,
                        false,
                        array((int) $pos, $a_new_id)
                    );
                } else {
                    $block_id = array_pop($current);
                    $block_id = $block_id["questionblock_id"];

                    $this->object->addQuestionToBlock($a_new_id, $block_id);
                }
            }
            // c: as new page (from toolbar/pool)
            elseif ((int) $pos) {
                $pos = (int) $pos . "a";
                $this->current_page++;
            }
            // at the beginning
            else {
                $first = $this->object->getSurveyPages();
                $first = $first[0];
                $first = array_shift($first);
                $pos = $first["question_id"] . "b";
                $this->current_page = 1;
            }

            // move to target position
            $this->object->moveQuestions(
                array($a_new_id),
                (int) $pos,
                ((substr($pos, -1) === "a") ? 1 : 0)
            );

            $this->object->fixSequenceStructure();
        }
    }

    /**
     * Copy and insert questions from block
     */
    public function insertQuestionBlock(
        int $a_block_id
    ): void {
        $new_ids = array();
        $question_ids = $this->object->getQuestionblockQuestionIds($a_block_id);
        foreach ($question_ids as $qid) {
            $new_ids[] = $this->appendNewQuestionToSurvey($qid, true, true);
        }

        if (count($new_ids)) {
            $this->object->loadQuestionsFromDb();

            $pos = $this->svy_request->getTargetQuestionPosition();

            // a[fter]/b[efore] on same page
            if (substr($pos, -1) !== "c") {
                // block handling
                $current = $this->object->getSurveyPages();
                $current = $current[$this->current_page - 1];
                if (count($current) === 1) {
                    // as questions are moved to first block question
                    // always use existing as first
                    // the new question is moved later on (see below)
                    $this->object->createQuestionblock(
                        $this->getAutoBlockTitle(),
                        true,
                        false,
                        array((int) $pos) + $new_ids
                    );
                } else {
                    $block_id = array_pop($current);
                    $block_id = $block_id["questionblock_id"];

                    foreach ($new_ids as $qid) {
                        $this->object->addQuestionToBlock($qid, $block_id);
                    }
                }
            }
            // c: as new page (from toolbar/pool)
            else {
                // re-create block
                $this->object->createQuestionblock(
                    $this->getAutoBlockTitle(),
                    true,
                    false,
                    $new_ids
                );

                // after given question
                if ((int) $pos) {
                    $pos = (int) $pos . "a";
                }
                // at the beginning
                else {
                    $first = $this->object->getSurveyPages();
                    $first = $first[0];
                    $first = array_shift($first);
                    $pos = $first["question_id"] . "b";
                }
            }

            // move to target position
            $this->object->moveQuestions(
                $new_ids,
                (int) $pos,
                ((substr($pos, -1) === "a") ? 1 : 0)
            );
        }
    }

    /**
     * Call add question to survey form
     * @param int $a_type question type
     * @param bool $a_use_pool add question to pool
     * @param int $a_pos target position
     * @param string $a_special_position special positions (toolbar | page_end)
     * @throws ilCtrlException
     */
    protected function addQuestion(
        int $a_type,
        bool $a_use_pool,
        string $a_pos,
        string $a_special_position
    ): bool {
        $ilCtrl = $this->ctrl;
        $ilTabs = $this->tabs;

        // get translated type
        $type_trans = "";
        $questiontypes = ilObjSurveyQuestionPool::_getQuestiontypes();
        foreach ($questiontypes as $item) {
            if ($item["questiontype_id"] == $a_type) {
                $type_trans = $item["type_tag"];
            }
        }

        $id = $a_pos;

        // new page behind current (from toolbar)
        if ($a_special_position === "toolbar") {
            $id = $this->object->getSurveyPages();
            if ($a_pos && $a_pos !== "fst") {
                $id = $id[(int) $a_pos - 1];
                $id = array_pop($id);
                $id = $id["question_id"] . "c";
            } else {
                $id = "0c";
            }
        }
        // append current page
        elseif ($a_special_position === "page_end") {
            $id = $this->object->getSurveyPages();
            $id = $id[$this->current_page - 1];
            $id = array_pop($id);
            $id = $id["question_id"] . "a";
        } else {
            $id .= "b";
        }

        if ($a_use_pool) {
            $ilCtrl->setParameter($this->editor_gui, "pgov_pos", $id);
            $ilCtrl->setParameter($this->editor_gui, "sel_question_types", $type_trans);
            if ($this->svy_request->getPoolUsage() === 0) {
                $ilTabs->clearSubTabs(); // #17193
                $this->editor_gui->createQuestionObject(null, $type_trans, $id);
            } else {
                $this->editor_gui->executeCreateQuestionObject($type_trans, null, $id);
            }
            return true;
        } else {
            // create question and redirect to question form

            $q_gui = SurveyQuestionGUI::_getQuestionGUI($type_trans);
            $q_gui->object->setObjId($this->object->getId());
            $q_gui->object->createNewQuestion();
            $q_gui_class = get_class($q_gui);

            // #12531
            $ilCtrl->setParameterByClass($q_gui_class, "pgov", $this->current_page);
            $ilCtrl->setParameterByClass($q_gui_class, "pgov_pos", $id);
            $ilCtrl->setParameterByClass($q_gui_class, "ref_id", $this->ref_id);
            $ilCtrl->setParameterByClass($q_gui_class, "new_for_survey", $this->ref_id);
            $ilCtrl->setParameterByClass($q_gui_class, "q_id", $q_gui->object->getId());
            $ilCtrl->setParameterByClass($q_gui_class, "sel_question_types", $q_gui->getQuestionType());
            $ilCtrl->redirectByClass($q_gui_class, "editQuestion");
        }
        return false;
    }

    /**
     * Add question to be cut to clipboard
     */
    protected function cutQuestion(int $a_id): void
    {
        $lng = $this->lng;

        $this->tpl->setOnScreenMessage('success', $lng->txt("survey_questions_to_clipboard_cut"));
        $this->suppress_clipboard_msg = true;

        $this->edit_manager->setQuestionClipboard(
            $this->ref_id,
            $this->current_page,
            "cut",
            array($a_id)
        );
    }

    /**
     * Add question to be copied to clipboard
     */
    protected function copyQuestion(int $a_id): void
    {
        $lng = $this->lng;

        $this->tpl->setOnScreenMessage('success', $lng->txt("survey_questions_to_clipboard_copy"));
        $this->suppress_clipboard_msg = true;

        $this->edit_manager->setQuestionClipboard(
            $this->ref_id,
            $this->current_page,
            "copy",
            array($a_id)
        );
    }

    /**
     * Add questions to be cut to clipboard
     *
     * @param int[] $a_id question ids
     */
    protected function multiCut(array $a_id): void
    {
        $lng = $this->lng;

        if (count($a_id) > 0) {
            $this->tpl->setOnScreenMessage('success', $lng->txt("survey_questions_to_clipboard_cut"));
            $this->suppress_clipboard_msg = true;

            $this->edit_manager->setQuestionClipboard(
                $this->ref_id,
                $this->current_page,
                "cut",
                $a_id
            );
        }
    }

    /**
     * Add questions to be copied to clipboard
     * @param int[] $a_id question ids
     */
    protected function multiCopy(array $a_id): void
    {
        $lng = $this->lng;

        if (count($a_id) > 0) {
            $this->tpl->setOnScreenMessage('success', $lng->txt("survey_questions_to_clipboard_copy"));
            $this->suppress_clipboard_msg = true;

            $this->edit_manager->setQuestionClipboard(
                $this->ref_id,
                $this->current_page,
                "copy",
                $a_id
            );
        }
    }

    /**
     * Empty clipboard
     */
    protected function clearClipboard(): void
    {
        $this->edit_manager->clearQuestionClipboard($this->ref_id);
    }

    /**
     * Paste from clipboard
     * @param int $a_id target position
     */
    protected function paste(int $a_id): void
    {
        $source_page = $this->edit_manager->getQuestionClipboardSourcePage($this->ref_id);
        $qids = $this->edit_manager->getQuestionClipboardQuestions($this->ref_id);
        $mode = $this->edit_manager->getQuestionClipboardMode($this->ref_id);
        $pages = $this->object->getSurveyPages();
        $source = $pages[$source_page - 1];
        $target = $pages[$this->current_page - 1];

        // #12558 - use order of source page
        $nodes = array();
        foreach ($source as $src_qst) {
            if (in_array($src_qst["question_id"], $qids)) {
                $nodes[] = $src_qst["question_id"];
            }
        }

        // append to last position?
        $pos = 0;
        if ($this->svy_request->getHForm("node") === "page_end") {
            $a_id = $target;
            $a_id = array_pop($a_id);
            $a_id = $a_id["question_id"];
            $pos = 1;
        }

        // cut
        if ($mode === "cut") {
            // special case: paste cut on same page (no block handling needed)
            if ($source_page == $this->current_page) {
                // re-order nodes in page
                if (count($nodes) <= count($source)) {
                    $this->object->moveQuestions($nodes, $a_id, $pos);
                }
                $this->clearClipboard();
                return;
            } else {
                // only if source has block
                $source_block_id = false;
                if (count($source) > 1) {
                    $source_block_id = $source;
                    $source_block_id = array_shift($source_block_id);
                    $source_block_id = $source_block_id["questionblock_id"];

                    // remove from block
                    if (count($source) > count($nodes)) {
                        foreach ($nodes as $qid) {
                            $this->object->removeQuestionFromBlock($qid, $source_block_id);
                        }
                    }
                    // remove complete block
                    else {
                        $this->object->unfoldQuestionblocks(array($source_block_id));
                    }
                }

                // page will be "deleted" by operation
                if (count($source) === count($nodes) && $source_page < $this->current_page) {
                    $this->current_page--;
                }
            }
        }

        // copy
        elseif ($mode === "copy") {
            $titles = array();
            foreach ($this->object->getSurveyPages() as $page) {
                foreach ($page as $question) {
                    $titles[] = $question["title"];
                }
            }

            // copy questions
            $question_pointer = array();
            foreach ($nodes as $qid) {
                // create new questions
                $question = ilObjSurvey::_instanciateQuestion($qid);
                // handle exisiting copies
                $title = $question->getTitle();
                $max = 0;
                foreach ($titles as $existing_title) {
                    #21278 preg_quote with delimiter
                    if (preg_match("/" . preg_quote($title, "/") . " \(([0-9]+)\)$/", $existing_title, $match)) {
                        $max = max($match[1], $max);
                    }
                }
                if ($max) {
                    $title .= " (" . ($max + 1) . ")";
                } else {
                    $title .= " (2)";
                }
                $titles[] = $title;
                $question->setTitle($title);

                $question->id = -1;
                $question->saveToDb();

                $question_pointer[$qid] = $question->getId();
                $this->appendNewQuestionToSurvey($question->getId(), false);
            }

            // copy textblocks
            $this->object->cloneTextblocks($question_pointer);

            $this->object->loadQuestionsFromDb();

            $nodes = array_values($question_pointer);
        }


        // paste

        // create new block
        if (count($target) === 1) {
            $nodes = array_merge(array($a_id), $nodes);

            // moveQuestions() is called within
            $this->object->createQuestionblock(
                $this->getAutoBlockTitle(),
                true,
                false,
                $nodes
            );
        }
        // add to existing block
        else {
            $target_block_id = $target;
            $target_block_id = array_shift($target_block_id);
            $target_block_id = $target_block_id["questionblock_id"];

            foreach ($nodes as $qid) {
                $this->object->addQuestionToBlock($qid, $target_block_id);
            }

            // move to new position
            $this->object->moveQuestions($nodes, $a_id, $pos);
        }

        $this->clearClipboard();
    }

    /**
     * Move questions in page
     */
    protected function dnd(): void
    {
        $source_arr = explode("_", $this->svy_request->getHForm("source"));
        $target_arr = explode("_", $this->svy_request->getHForm("target"));
        $source_id = (int) array_pop($source_arr);
        if ($this->svy_request->getHForm("target") !== "droparea_end") {
            $target_id = (int) array_pop($target_arr);
            $pos = 0;
        } else {
            $page = $this->object->getSurveyPages();
            $page = $page[$this->current_page - 1];
            $last = array_pop($page);
            $target_id = (int) $last["question_id"];
            $pos = 1;
        }
        if ($source_id !== $target_id) {
            $this->object->moveQuestions(array($source_id), $target_id, $pos);
        }
    }

    /**
     * Confirm removing question block
     */
    protected function deleteBlock(): void
    {
        $lng = $this->lng;
        $ilCtrl = $this->ctrl;

        $ilCtrl->setParameter($this->editor_gui, "pgov", $this->current_page);
        $this->tpl->setOnScreenMessage('question', $lng->txt("remove_questions"));

        $page = $this->object->getSurveyPages();
        $page = $page[$this->current_page - 1];

        // #10567
        if ($this->svy_request->getCheckSum() !== md5(print_r($page, true))) {
            $ilCtrl->redirect($this, "renderPage");
        }

        $page = array_shift($page);
        $block_id = $page["questionblock_id"];
        if ($block_id) {
            $this->editor_gui->removeQuestionsForm(array($block_id), array(), array());
        } else {
            $this->editor_gui->removeQuestionsForm(array(), array($page["question_id"]), array());
        }
    }

    /**
     * Confirm removing question(s) from survey
     *
     * @param array $a_id
     * @throws ilCtrlException
     */
    protected function deleteQuestion(array $a_id): void
    {
        $ilCtrl = $this->ctrl;
        $ilCtrl->setParameter($this->editor_gui, "pgov", $this->current_page);
        $this->editor_gui->removeQuestionsForm(array(), $a_id, array());
    }

    /**
     * Remove question(s) from survey
     */
    protected function confirmRemoveQuestions(): void
    {
        $ilCtrl = $this->ctrl;
        $ids = $this->svy_request->getQuestionIds();

        $pages = $this->object->getSurveyPages();
        $source = $pages[$this->current_page - 1];

        $block_id = $source;
        $block_id = array_shift($block_id);
        $block_id = $block_id["questionblock_id"];

        if (count($ids) && count($source) > count($ids)) {
            // block is obsolete
            if (count($source) - count($ids) === 1) {
                $this->object->unfoldQuestionblocks(array($block_id));
            }
            // block will remain, remove question(s) from block
            else {
                foreach ($ids as $qid) {
                    $this->object->removeQuestionFromBlock($qid, $block_id);
                }
            }

            $this->object->removeQuestions($ids, array());
        }
        // all items on page
        else {
            // remove complete block
            if ($block_id) {
                $this->object->removeQuestions(array(), array($block_id));
            }
            // remove single question
            else {
                $this->object->removeQuestions($ids, array());
            }

            // render previous page
            if ($this->current_page > 1) {
                $this->current_page--;
            }
        }

        $this->object->saveCompletionStatus();

        // #10567
        $ilCtrl->setParameter($this, "pgov", $this->current_page);
        $ilCtrl->redirect($this, "renderPage");
    }

    /**
     * Edit question block
     */
    protected function editBlock(int $a_id): void
    {
        $this->callEditor("editQuestionblock", "bl_id", $a_id);
    }

    /**
     * Add heading to question
     */
    protected function addHeading(int $a_id): void
    {
        $this->callEditor("addHeading", "q_id", $a_id);
    }

    /**
     * Edit question heading
     */
    protected function editHeading(int $a_id): void
    {
        $this->callEditor("editHeadingObject", "q_id", $a_id);
    }

    /**
     * Delete question heading
     */
    protected function deleteHeading(int $a_id): void
    {
        $this->callEditor("removeHeading", "q_id", $a_id);
    }

    protected function callEditor(
        string $a_cmd,
        string $a_param,
        string $a_value
    ): void {
        $ilTabs = $this->tabs;

        $ilTabs->clearSubTabs();
        $ctrl = $this->ctrl;

        $ctrl->setParameter($this->editor_gui, $a_param, $a_value);
        $ctrl->redirect($this->editor_gui, $a_cmd);

        //$_REQUEST[$a_param] = $a_value;
        //call_user_func(array($this->editor_gui, $a_cmd));
    }

    /**
     * Split current page in 2 pages
     */
    protected function splitPage(
        int $a_id
    ): void {
        $pages = $this->object->getSurveyPages();
        $source = $pages[$this->current_page - 1];

        $block_questions = array();
        $add = $block_id = false;
        foreach ($source as $idx => $item) {
            if ($item["question_id"] == $a_id) {
                $block_id = $item["questionblock_id"];
                $add = $idx;
            }
            if ($add) {
                $block_questions[] = $item["question_id"];
            }
        }

        // just 1 question left: block is obsolete
        if ($add === 1) {
            $this->object->unfoldQuestionblocks(array($block_id));
        }
        // remove questions from block
        else {
            foreach ($block_questions as $qid) {
                $this->object->removeQuestionFromBlock($qid, $block_id);
            }
        }

        // more than 1 moved?
        if (count($block_questions) > 1) {
            // create new block and move target questions
            $this->object->createQuestionblock(
                $this->getAutoBlockTitle(),
                true,
                false,
                $block_questions
            );
        }

        $this->current_page++;
    }

    /**
     * Move question to next page
     */
    protected function moveNext(int $a_id): void
    {
        $pages = $this->object->getSurveyPages();
        $source = $pages[$this->current_page - 1];
        $target = $pages[$this->current_page];
        if (count($target)) {
            $target_id = $target;
            $target_id = array_shift($target_id);
            $target_block_id = $target_id["questionblock_id"];
            $target_id = $target_id["question_id"];

            // nothing to do if no block
            if (count($source) > 1) {
                $block_id = $source;
                $block_id = array_shift($block_id);
                $block_id = $block_id["questionblock_id"];

                // source pages block is obsolete
                if (count($source) === 2) {
                    // delete block
                    $this->object->unfoldQuestionblocks(array($block_id));
                } else {
                    // remove question from block
                    $this->object->removeQuestionFromBlock($a_id, $block_id);
                }
            }

            // move source question to target
            $this->object->moveQuestions(array($a_id), $target_id, 0);

            // new page has no block yet
            if (count($target) < 2) {
                // create block and  move target question and source into block
                $this->object->createQuestionblock(
                    $this->getAutoBlockTitle(),
                    true,
                    false,
                    array($a_id, $target_id)
                );
            } else {
                // add source question to block
                $this->object->addQuestionToBlock($a_id, $target_block_id);
            }

            // only if current page is not "deleted"
            if (count($source) > 1) {
                $this->current_page++;
            }
        }
    }

    /**
     * Move question to previous page
     */
    protected function movePrevious(
        int $a_id
    ): void {
        $pages = $this->object->getSurveyPages();
        $source = $pages[$this->current_page - 1];
        $target = $pages[$this->current_page - 2];
        if (count($target)) {
            $target_id = $target;
            $target_id = array_pop($target_id);
            $target_block_id = $target_id["questionblock_id"];
            $target_id = $target_id["question_id"];

            // nothing to do if no block
            if (count($source) > 1) {
                $block_id = $source;
                $block_id = array_shift($block_id);
                $block_id = $block_id["questionblock_id"];

                // source pages block is obsolete
                if (count($source) === 2) {
                    // delete block
                    $this->object->unfoldQuestionblocks(array($block_id));
                } else {
                    // remove question from block
                    $this->object->removeQuestionFromBlock($a_id, $block_id);
                }
            }

            // move source question to target
            $this->object->moveQuestions(array($a_id), $target_id, 1);

            // new page has no block yet
            if (count($target) < 2) {
                // create block and  move target question and source into block
                $this->object->createQuestionblock(
                    $this->getAutoBlockTitle(),
                    true,
                    false,
                    array($target_id, $a_id)
                );
            } else {
                // add source question to block
                $this->object->addQuestionToBlock($a_id, $target_block_id);
            }

            $this->current_page--;
        }
    }

    protected function editQuestion(int $a_id): void
    {
        $ilCtrl = $this->ctrl;

        $data = $this->object->getSurveyQuestions();
        $data = $data[$a_id];

        $q_gui = $data["type_tag"] . "GUI";
        $ilCtrl->setParameterByClass($q_gui, "pgov", $this->current_page);
        $ilCtrl->setParameterByClass($q_gui, "q_id", $a_id);

        $ilCtrl->redirectByClass($q_gui, "editQuestion");
    }

    /**
     * Add question to survey form (used in toolbar)
     */
    protected function addQuestionToolbarForm(): void
    {
        $lng = $this->lng;
        $ilCtrl = $this->ctrl;
        $tpl = $this->tpl;

        $form = new ilPropertyFormGUI();
        $form->setFormAction($ilCtrl->getFormAction($this, "addQuestionToolbar"));
        $form->setTitle($lng->txt("survey_add_new_question"));

        // question types
        $questiontypes = ilObjSurveyQuestionPool::_getQuestiontypes();
        $type_map = array();
        foreach ($questiontypes as $trans => $item) {
            $type_map[$item["questiontype_id"]] = $trans;
        }
        $si = new ilSelectInputGUI($lng->txt("question_type"), "qtype");
        $si->setOptions($type_map);
        $form->addItem($si);

        $pages = $this->object->getSurveyPages();
        if ($pages) {
            $pages_drop = array("fst" => $lng->txt("survey_at_beginning"));
            foreach ($pages as $idx => $questions) {
                $question = array_shift($questions);
                if ($question["questionblock_id"]) {
                    $pages_drop[$idx + 1] = $lng->txt("survey_behind_page") . " " . $question["questionblock_title"];
                } else {
                    $pages_drop[$idx + 1] = $lng->txt("survey_behind_page") . " " . strip_tags($question["title"]);
                }
            }
            $pos = new ilSelectInputGUI($lng->txt("position"), "pgov");
            $pos->setOptions($pages_drop);
            $form->addItem($pos);

            $pos->setValue($this->current_page);
        } else {
            // #9089: 1st page
            $pos = new ilHiddenInputGUI("pgov");
            $pos->setValue("fst");
            $form->addItem($pos);
        }

        if ($this->object->getPoolUsage()) {
            $this->editor_gui->createQuestionObject($form);
        }

        $form->addCommandButton("addQuestionToolbar", $lng->txt("create"));
        $form->addCommandButton("renderPage", $lng->txt("cancel"));

        $tpl->setContent($form->getHTML());
    }

    /**
     * Add question to survey action (used in toolbar)
     */
    protected function addQuestionToolbar(): void
    {
        $ilCtrl = $this->ctrl;
        $lng = $this->lng;

        $pool_active = $this->object->getPoolUsage();

        if ($this->svy_request->getPoolUsage() === 0 && $pool_active) {
            $this->tpl->setOnScreenMessage('failure', $lng->txt("select_one"), true);
            $this->addQuestionToolbarForm();
            return;
        }

        // make sure that it is set for current and next requests
        $ilCtrl->setParameter($this->editor_gui, "pgov", $this->current_page);
        if (!$this->addQuestion(
            $this->svy_request->getQuestionType(),
            $pool_active,
            $this->pgov,
            "toolbar"
        )) {
            $this->renderPage();
        }
    }

    /**
     * Move current page
     */
    protected function movePageForm(): void
    {
        $lng = $this->lng;
        $ilCtrl = $this->ctrl;
        $tpl = $this->tpl;

        $form = new ilPropertyFormGUI();
        $form->setFormAction($ilCtrl->getFormAction($this, "movePage"));
        $form->setTitle($lng->txt("survey_move_page"));

        $old_pos = new ilHiddenInputGUI("old_pos");
        $old_pos->setValue($this->current_page);
        $form->addItem($old_pos);

        $pages = $this->object->getSurveyPages();
        if ($pages) {
            $pages_drop = array();
            if ((int) $this->current_page !== 1) {
                $pages_drop["fst"] = $lng->txt("survey_at_beginning");
            }
            foreach ($pages as $idx => $questions) {
                if (($idx + 1) != $this->current_page && ($idx + 2) != $this->current_page) {
                    $question = array_shift($questions);
                    if ($question["questionblock_id"]) {
                        $pages_drop[$idx + 1] = $lng->txt("survey_behind_page") . " " . $question["questionblock_title"];
                    } else {
                        $pages_drop[$idx + 1] = $lng->txt("survey_behind_page") . " " . strip_tags($question["title"]);
                    }
                }
            }
            $pos = new ilSelectInputGUI($lng->txt("position"), "pgov");
            $pos->setOptions($pages_drop);
            $form->addItem($pos);
        }

        $form->addCommandButton("movePage", $lng->txt("submit"));
        $form->addCommandButton("renderPage", $lng->txt("cancel"));

        $tpl->setContent($form->getHTML());
    }

    /**
     * Move current page to new position
     * @todo this needs to be refactored outside of a GUI class, same with ilSurveyEditorGUI->insertQuestions
     */
    protected function movePage(): void
    {
        $lng = $this->lng;
        $ilCtrl = $this->ctrl;

        $questions = [];

        // current_page is already set to new position
        $target_page = $this->current_page - 1;
        $source_page = $this->svy_request->getOldPosition() - 1;

        $pages = $this->object->getSurveyPages();
        foreach ($pages[$source_page] as $question) {
            $questions[] = $question["question_id"];
        }

        // move to first position
        $position = 0;
        if ($this->pgov !== "fst") {
            $position = 1;
        }

        $target = $pages[$target_page];
        if ($position === 0) {								// before
            $target = array_shift($target);             // ... use always the first question of the page
        } else {											// after
            $target = array_pop($target);             // ... use always the last question of the page
        }
        $this->object->moveQuestions($questions, $target["question_id"], $position);

        if ($target_page < $source_page && $position) {
            $this->current_page++;
        }

        $this->tpl->setOnScreenMessage('success', $lng->txt("survey_page_moved"), true);
        $ilCtrl->setParameter($this, "pgov", $this->current_page);
        $ilCtrl->redirect($this, "renderPage");
    }

    /**
     * Render toolbar form
     */
    protected function renderToolbar(?array $a_pages): void
    {
        $ilToolbar = $this->toolbar;
        $ilCtrl = $this->ctrl;
        $lng = $this->lng;
        $ilUser = $this->user;
        $pages_drop = null;

        if (!$this->has_datasets) {
            $button = ilLinkButton::getInstance();
            $button->setCaption("survey_add_new_question");
            $button->setUrl($ilCtrl->getLinkTarget($this, "addQuestionToolbarForm"));
            $ilToolbar->addStickyItem($button);

            if ($this->object->getPoolUsage()) {
                //$ilToolbar->addSeparator();

                $last_on_page = 0;
                if ($a_pages &&
                    isset($a_pages[$this->current_page - 1])) {
                    $last_on_page = $a_pages[$this->current_page - 1];
                    $last_on_page = array_pop($last_on_page);
                    $last_on_page = $last_on_page["question_id"];
                }

                $ilCtrl->setParameter($this->editor_gui, "pgov", $this->current_page);
                $ilCtrl->setParameter($this->editor_gui, "pgov_pos", $last_on_page . "c");

                $cmd = ((int) $ilUser->getPref('svy_insert_type') === 1 ||
                    ($ilUser->getPref('svy_insert_type') ?? '') === '')
                    ? 'browseForQuestions'
                    : 'browseForQuestionblocks';

                $button = ilLinkButton::getInstance();
                $button->setCaption("browse_for_questions");
                $button->setUrl($ilCtrl->getLinkTarget($this->editor_gui, $cmd));
                $ilToolbar->addStickyItem($button);

                $ilCtrl->setParameter($this->editor_gui, "pgov", "");
                $ilCtrl->setParameter($this->editor_gui, "pgov_pos", "");
            }

            if ($a_pages) {
//                $ilToolbar->addSeparator();
            }
        }

        // parse data for pages drop-down
        if ($a_pages) {
            // previous/next

            $ilCtrl->setParameter($this, "pg", $this->current_page - 1);
            $button = ilLinkButton::getInstance();
            $button->setCaption("survey_prev_question");
            if ($this->has_previous_page) {
                $button->setUrl($ilCtrl->getLinkTarget($this, "renderPage"));
            }
            $button->setDisabled(!$this->has_previous_page);
            $ilToolbar->addStickyItem($button);

            $ilCtrl->setParameter($this, "pg", (string) ((int) $this->current_page + 1));
            $button = ilLinkButton::getInstance();
            $button->setCaption("survey_next_question");
            if ($this->has_next_page) {
                $button->setUrl($ilCtrl->getLinkTarget($this, "renderPage"));
            }
            $button->setDisabled(!$this->has_next_page);
            $ilToolbar->addStickyItem($button);

            $ilCtrl->setParameter($this, "pg", $this->current_page); // #14615

            foreach ($a_pages as $idx => $questions) {
                $page = $questions;
                $page = array_shift($page);
                if ($page["questionblock_id"]) {
                    $pages_drop[$idx + 1] = $page["questionblock_title"];

                    if (count($questions) > 1) {
                        foreach ($questions as $question) {
                            $pages_drop[($idx + 1) . "__" . $question["question_id"]] = "- " . $question["title"];
                        }
                    }
                } else {
                    $pages_drop[$idx + 1] = strip_tags($page["title"]);
                }
            }
        }

        // jump to page
        if (is_array($pages_drop) && count($pages_drop) > 1) {
            //$ilToolbar->addSeparator();

            $ilToolbar->setFormAction($ilCtrl->getFormAction($this));

            $si = new ilSelectInputGUI($lng->txt("survey_jump_to"), "jump");
            $si->addCustomAttribute("onChange=\"forms['ilToolbar'].submit();\"");
            $si->setOptions($pages_drop);
            $si->setValue($this->current_page);
            $ilToolbar->addInputItem($si, true);

            // we need this to have to right cmd
            $cmd = new ilHiddenInputGUI("cmd[renderPage]");
            $cmd->setValue("1");
            $ilToolbar->addInputItem($cmd);

            if (!$this->has_datasets) {
                $ilToolbar->addSeparator();

                $ilCtrl->setParameter($this, "csum", md5(print_r($a_pages[$this->current_page - 1], true)));
                $url = $ilCtrl->getLinkTarget($this, "deleteBlock");
                $ilCtrl->setParameter($this, "csum", "");

                $button = ilLinkButton::getInstance();
                $button->setCaption("survey_delete_page");
                $button->setUrl($url);
                $ilToolbar->addButtonInstance($button);

                $ilToolbar->addSeparator();

                $button = ilLinkButton::getInstance();
                $button->setCaption("survey_move_page");
                $button->setUrl($ilCtrl->getLinkTarget($this, "movePageForm"));
                $ilToolbar->addButtonInstance($button);
            }
        }

        // print view
        if (is_array($pages_drop)) {
            $ilToolbar->addSeparator();
            $print_view = $this->print->page($this->ref_id);
            $modal_elements = $print_view->getModalElements(
                $this->ctrl->getLinkTarget(
                    $this,
                    "printViewSelection"
                )
            );
            $ilToolbar->addComponent($modal_elements->button);
            $ilToolbar->addComponent($modal_elements->modal);
        }
    }

    /**
     * render questions per page
     */
    protected function renderPage(): void
    {
        $ilCtrl = $this->ctrl;
        $lng = $this->lng;
        $tpl = $this->tpl;
        $rbacsystem = $this->rbacsystem;

        $pages = $this->object->getSurveyPages();
        $this->has_next_page = ($this->current_page < count($pages));
        $this->has_previous_page = ($this->current_page > 1);
        $this->has_datasets = ilObjSurvey::_hasDatasets($this->object->getSurveyId());

        $mess = "";
        if ($this->has_datasets) {
            $mbox = new ilSurveyContainsDataMessageBoxGUI();
            $mess = $mbox->getHTML();
        }

        $ilCtrl->setParameter($this, "pg", $this->current_page);
        $ilCtrl->setParameter($this, "pgov", "");

        $this->renderToolbar($pages);
        if ($pages) {
            $ttpl = new ilTemplate("tpl.il_svy_svy_page_view.html", true, true, "Modules/Survey");
            $tpl->addOnLoadCode("ilSurveyPageView.init();");
            $ttpl->setVariable("FORM_ACTION", $ilCtrl->getFormAction($this));
            $lng->loadLanguageModule("form");

            $read_only = ($this->has_datasets || !$rbacsystem->checkAccess("write", $this->ref_id));

            $commands = $multi_commands = array();

            if (!$read_only) {
                // clipboard is empty
                if ($this->edit_manager->isQuestionClipboardEmpty($this->ref_id)) {
                    $multi_commands[] = array("cmd" => "multiDelete", "text" => $lng->txt("delete"));
                    $multi_commands[] = array("cmd" => "multiCut", "text" => $lng->txt("cut"));
                    $multi_commands[] = array("cmd" => "multiCopy", "text" => $lng->txt("copy"));
                    $multi_commands[] = array("cmd" => "selectAll", "text" => $lng->txt("select_all"));
                } else {
                    if (!$this->suppress_clipboard_msg) {
                        $this->tpl->setOnScreenMessage('info', $lng->txt("survey_clipboard_notice"));
                    }
                    $multi_commands[] = array("cmd" => "clearClipboard", "text" => $lng->txt("survey_dnd_clear_clipboard"));
                }

                // help - see ilPageObjectGUI::insertHelp()
                $lng->loadLanguageModule("content");
                $ttpl->setCurrentBlock("help_section");
                $ttpl->setVariable("TXT_ADD_EL", $lng->txt("cont_add_elements"));
                $ttpl->setVariable("PLUS", ilGlyphGUI::get(ilGlyphGUI::ADD));
                $ttpl->setVariable("DRAG_ARROW", ilGlyphGUI::get(ilGlyphGUI::DRAG));
                $ttpl->setVariable("TXT_DRAG", $lng->txt("cont_drag_and_drop_elements"));
                $ttpl->setVariable("TXT_SEL", $lng->txt("cont_double_click_to_delete"));
                $ttpl->parseCurrentBlock();

                $ttpl->setVariable("DND_INIT_JS", "initDragElements();");


                // tiny mce

                $tags = ilObjAdvancedEditing::_getUsedHTMLTags("survey");

                /**
                 * Alex Killing, 27 July 2018
                 * I removed a line $tpl->addJavascript("./Services/RTE/tiny_mce_.../tiny_mce_src.js"); at the end
                 * of this function. Currently I have no idea when this tiny will be presented...
                 * Maybe a bug will come out of this during 5.4 testing
                 */
                $tiny = new ilTinyMCE();
                $ttpl->setVariable("WYSIWYG_BLOCKFORMATS", $tiny->_buildAdvancedBlockformatsFromHTMLTags($tags));
                $ttpl->setVariable("WYSIWYG_VALID_ELEMENTS", $tiny->_getValidElementsFromHTMLTags($tags));

                $buttons_1 = $tiny->_buildAdvancedButtonsFromHTMLTags(1, $tags);
                $buttons_2 = $tiny->_buildAdvancedButtonsFromHTMLTags(2, $tags) . ',' .
                            $tiny->_buildAdvancedTableButtonsFromHTMLTags($tags) .
                            ($tiny->getStyleSelect() ? ',styleselect' : '');
                $buttons_3 = $tiny->_buildAdvancedButtonsFromHTMLTags(3, $tags);
                $ttpl->setVariable('WYSIWYG_BUTTONS_1', ilTinyMCE::removeRedundantSeparators($buttons_1));
                $ttpl->setVariable('WYSIWYG_BUTTONS_2', ilTinyMCE::removeRedundantSeparators($buttons_2));
                $ttpl->setVariable('WYSIWYG_BUTTONS_3', ilTinyMCE::removeRedundantSeparators($buttons_3));
            }

            // commands
            if (count($multi_commands) > 0) {
                foreach ($multi_commands as $cmd) {
                    $ttpl->setCurrentBlock("multi_cmd");
                    $ttpl->setVariable("ORG_CMD_MULTI", "renderPage");
                    $ttpl->setVariable("MULTI_CMD", $cmd["cmd"]);
                    $ttpl->setVariable("MULTI_CMD_TXT", $cmd["text"]);
                    $ttpl->parseCurrentBlock();
                }

                $ttpl->setCurrentBlock("multi_cmds");
                $ttpl->setVariable("MCMD_ALT", $lng->txt("commands"));
                $ttpl->setVariable("MCMD_IMG", ilUtil::getImagePath("arrow_downright.svg"));
                $ttpl->parseCurrentBlock();
            }

            // nodes
            $ttpl->setVariable("NODES", $this->getPageNodes(
                $pages[$this->current_page - 1] ?? [],
                $this->has_previous_page,
                $this->has_next_page,
                $read_only
            ));

            $tpl->setContent($mess . $ttpl->get());

            // add js to template
            ilYuiUtil::initDragDrop();
            $tpl->addJavaScript("./Modules/Survey/js/SurveyPageView.js");
        }
    }

    /**
     * @return string page nodes html
     * @throws ilCtrlException
     */
    public function getPageNodes(
        array $a_questions,
        bool $a_has_previous_page = false,
        bool $a_has_next_page = false,
        bool $a_readonly = false
    ): string {
        $ilCtrl = $this->ctrl;
        $lng = $this->lng;

        $ttpl = new ilTemplate("tpl.il_svy_svy_page_view_nodes.html", true, true, "Modules/Survey");

        $has_clipboard = !$this->edit_manager->isQuestionClipboardEmpty($this->ref_id);

        // question block ?

        $first_question = $a_questions;
        $first_question = array_shift($first_question);

        $compress_view = false;
        if ($first_question["questionblock_id"] ?? false) {
            $menu = array();

            if (!$a_readonly && !$has_clipboard) {
                $menu[] = array("cmd" => "editBlock", "text" => $lng->txt("edit"));
            }

            if ($first_question["questionblock_show_blocktitle"]) {
                $block_status = $lng->txt("survey_block_visible");
            } else {
                $block_status = $lng->txt("survey_block_hidden");
            }

            $this->renderPageNode(
                $ttpl,
                "block",
                $first_question["questionblock_id"],
                $first_question["questionblock_title"] . " (" . $block_status . ")",
                $menu,
                false,
                false,
                $block_status
            );

            if (count($a_questions) > 1) {
                $compress_view = $first_question["questionblock_compress_view"];
            }
        }


        // questions/headings

        $questiontypes = ilObjSurveyQuestionPool::_getQuestiontypes();
        foreach ($a_questions as $idx => $question) {
            // drop area

            $menu = array();

            if (!$a_readonly) {
                if (!$has_clipboard) {
                    foreach ($questiontypes as $trans => $item) {
                        $menu[] = array("cmd" => "addQuestion_" . $item["questiontype_id"],
                            "text" => sprintf($lng->txt("svy_page_add_question"), $trans));
                    }

                    if ($this->object->getPoolUsage()) {
                        $menu[] = array("cmd" => "addPoolQuestion",
                            "text" => $lng->txt("browse_for_questions"));
                    }
                } else {
                    $menu[] = array("cmd" => "paste", "text" => $lng->txt("survey_dnd_paste"));
                }
            }

            $this->renderPageNode($ttpl, "droparea", $question["question_id"], null, $menu, true);

            // question
            $question_gui = $this->object->getQuestionGUI($question["type_tag"], $question["question_id"]);
            $question_form = $question_gui->getWorkingForm(
                array(),
                $this->object->getShowQuestionTitles(),
                $question["questionblock_show_questiontext"],
                "",
                $this->object->getSurveyId(),
                $compress_view
            );

            $menu = array();

            if (!$a_readonly && !$has_clipboard) {
                $menu[] = array("cmd" => "editQuestion", "text" => $lng->txt("edit"));
                $menu[] = array("cmd" => "cutQuestion", "text" => $lng->txt("cut"));
                $menu[] = array("cmd" => "copyQuestion", "text" => $lng->txt("copy"));

                if (count($a_questions) > 1 && $idx > 0) {
                    $menu[] = array("cmd" => "splitPage", "text" => $lng->txt("survey_dnd_split_page"));
                }
                if ($a_has_next_page) {
                    $menu[] = array("cmd" => "moveNext", "text" => $lng->txt("survey_dnd_move_next"));
                }
                if ($a_has_previous_page) {
                    $menu[] = array("cmd" => "movePrevious", "text" => $lng->txt("survey_dnd_move_previous"));
                }

                $menu[] = array("cmd" => "deleteQuestion", "text" => $lng->txt("delete"));

                // heading
                if ($question["heading"]) {
                    $menu[] = array("cmd" => "editHeading", "text" => $lng->txt("survey_edit_heading"));
                    $menu[] = array("cmd" => "deleteHeading", "text" => $lng->txt("survey_delete_heading"));
                } else {
                    $menu[] = array("cmd" => "addHeading", "text" => $lng->txt("add_heading"));
                }
            }

            if ($first_question["questionblock_show_questiontext"]) {
                $question_title_status = $lng->txt("survey_question_text_visible");
            } else {
                $question_title_status = $lng->txt("survey_question_text_hidden");
            }

            $this->renderPageNode(
                $ttpl,
                "question",
                $question["question_id"],
                $question_form,
                $menu,
                false,
                $question["title"],
                $question_title_status,
                $question["heading"]
            );

            $ilCtrl->setParameter($this, "eqid", "");
        }


        // last position (no question id)

        $menu = array();

        if (!$a_readonly) {
            if (!$has_clipboard) {
                foreach ($questiontypes as $trans => $item) {
                    $menu[] = array("cmd" => "addQuestion_" . $item["questiontype_id"],
                        "text" => sprintf($lng->txt("svy_page_add_question"), $trans));
                }

                if ($this->object->getPoolUsage()) {
                    $menu[] = array("cmd" => "addPoolQuestion",
                        "text" => $lng->txt("browse_for_questions"));
                }
            } else {
                $menu[] = array("cmd" => "paste", "text" => $lng->txt("survey_dnd_paste"));
            }
        }

        $this->renderPageNode($ttpl, "page", "end", null, $menu, true);

        return $ttpl->get();
    }

    /**
     * Render single node of dnd page view
     */
    public function renderPageNode(
        ilTemplate $a_tpl,
        string $a_type,
        string $a_id,
        ?string $a_content = null,
        ?array $a_menu = null,
        bool $a_spacer = false,
        string $a_subtitle = "",
        ?string $a_status = null,
        ?string $a_heading = null
    ): void {
        $ilCtrl = $this->ctrl;
        $lng = $this->lng;

        $node_id = $a_type . "_" . $a_id;

        if ($a_spacer) {
            if ($a_menu) {
                // drop area menu
                foreach ($a_menu as $mcnt => $menu_item) {
                    $ilCtrl->setParameter($this, "il_hform_node", $node_id);
                    $ilCtrl->setParameter($this, "il_hform_subcmd", $menu_item["cmd"]);
                    $url = $ilCtrl->getLinkTarget($this, "renderPage");
                    $ilCtrl->setParameter($this, "il_hform_subcmd", "");
                    $ilCtrl->setParameter($this, "il_hform_node", "");

                    $a_tpl->setCurrentBlock("menu_cmd");
                    $a_tpl->setVariable("TXT_MENU_CMD", $menu_item["text"]);
                    $a_tpl->setVariable("URL_MENU_CMD", $url);
                    $a_tpl->parseCurrentBlock();
                }
            }

            $a_tpl->setCurrentBlock("drop_area");
            $a_tpl->setVariable("ICON_ADD", ilGlyphGUI::get(ilGlyphGUI::ADD));
            $a_tpl->setVariable("DROP_ID", $a_id);
            $a_tpl->parseCurrentBlock();
        } elseif ($a_menu) {
            // question action menu
            foreach ($a_menu as $mcnt => $menu_item) {
                $ilCtrl->setParameter($this, "il_hform_node", $node_id);
                $ilCtrl->setParameter($this, "il_hform_subcmd", $menu_item["cmd"]);
                $url = $ilCtrl->getLinkTarget($this, "renderPage");
                $ilCtrl->setParameter($this, "il_hform_subcmd", "");
                $ilCtrl->setParameter($this, "il_hform_node", "");

                $a_tpl->setCurrentBlock("action_cmd");
                $a_tpl->setVariable("TXT_ACTION_CMD", $menu_item["text"]);
                $a_tpl->setVariable("URL_ACTION_CMD", $url);
                $a_tpl->parseCurrentBlock();
            }
        }

        // add heading to content
        if ($a_content !== null &&
            $a_type === "question" &&
            $a_heading) {
            $a_content = "<div class=\"questionheading\">" . $a_heading . "</div>" .
                $a_content;
        }

        if ($a_menu) {
            $a_tpl->setVariable("TXT_NODE_CONTENT_ACTIONS", $a_content);
        } else {
            $a_tpl->setVariable("TXT_NODE_CONTENT_NO_ACTIONS", $a_content);
        }

        if ($a_content !== null) {
            $drag = "";
            $selectable = false;
            switch ($a_type) {
                case "block":
                    $caption = $lng->txt("questionblock");
                    break;

                case "question":
                    $caption = $lng->txt("question") . ": " . $a_subtitle;
                    $drag = "_drag";
                    $selectable = true;
                    break;

                case "heading":
                    $caption = $lng->txt("heading");
                    break;

                default:
                    return;
            }

            if ($a_status) {
                $caption .= " (" . $a_status . ")";
            }

            $a_tpl->setCurrentBlock("list_item");
            $a_tpl->setVariable("NODE_ID", $node_id);
            $a_tpl->setVariable("NODE_DRAG", $drag);
            $a_tpl->setVariable("TXT_NODE_TYPE", $caption);
            if ($selectable) {
                $a_tpl->setVariable("SELECTABLE", " selectable");
            }
            $a_tpl->parseCurrentBlock();
        }

        $a_tpl->touchBlock("element");
    }

    /**
     * Get name for newly created blocks
     */
    public function getAutoBlockTitle(): string
    {
        $lng = $this->lng;
        return $lng->txt("survey_auto_block_title");
    }

    public function addPoolQuestion(
        string $pos,
        string $node
    ): void {
        $ilCtrl = $this->ctrl;
        $ilUser = $this->user;

        if ($node === "page_end") {
            $pages = $this->object->getSurveyPages();
            $page = array_pop($pages[$this->current_page - 1]);
            $pos = $page["question_id"] . "a";
        } else {
            $pos .= "b";
        }

        $ilCtrl->setParameter($this->editor_gui, "pgov", $this->current_page);
        $ilCtrl->setParameter($this->editor_gui, "pgov_pos", $pos);

        $cmd = ((int) $ilUser->getPref('svy_insert_type') === 1 || ($ilUser->getPref('svy_insert_type') ?? '') === '') ? 'browseForQuestions' : 'browseForQuestionblocks';
        $ilCtrl->redirect($this->editor_gui, $cmd);
    }

    public function printViewSelection(): void
    {
        $view = $this->print->page($this->ref_id);
        $view->sendForm();
    }
}
