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
 * @ilCtrl_Calls ilDclEditViewDefinitionGUI: ilPageEditorGUI, ilEditClipboardGUI, ilMediaPoolTargetSelector
 * @ilCtrl_Calls ilDclEditViewDefinitionGUI: ilPublicUserProfileGUI, ilPageObjectGUI
 */
class ilDclEditViewDefinitionGUI extends ilPageObjectGUI
{
    public ilDclTableView $tableview;
    protected ilDclEditViewTableGUI $table_gui;
    protected ILIAS\HTTP\Services $http;
    protected ILIAS\Refinery\Factory $refinery;
    protected int $tableview_id;

    public function __construct(int $tableview_id)
    {
        global $DIC;
        $this->tableview_id = $tableview_id;
        $this->tableview = ilDclTableView::findOrGetInstance($tableview_id);
        $this->http = $DIC->http();
        $this->refinery = $DIC->refinery();

        // we always need a page object - create on demand
        if (!ilPageObject::_exists('dclf', $tableview_id)) {
            $ref_id = $this->http->wrapper()->query()->retrieve('ref_id', $this->refinery->kindlyTo()->int());

            $viewdef = new ilDclEditViewDefinition();
            $viewdef->setId($tableview_id);
            $viewdef->setParentId(ilObject2::_lookupObjectId($ref_id));
            $viewdef->setActive(false);
            $viewdef->create();
        }

        parent::__construct("dclf", $tableview_id);

        $table = new ilDclEditViewTableGUI($this);
        $this->table_gui = $table;
        $this->tpl->setContent($table->getHTML());
    }

    public function executeCommand(): string
    {
        global $DIC;
        $ilLocator = $DIC['ilLocator'];
        $lng = $DIC['lng'];

        $next_class = $this->ctrl->getNextClass($this);

        $viewdef = $this->getPageObject();
        if ($viewdef) {
            $this->ctrl->setParameter($this, "dclv", $viewdef->getId());
            $title = $lng->txt("dcl_view_viewdefinition");
        }

        switch ($next_class) {
            case "ilpageobjectgui":
                throw new ilCOPageException("Deprecated. ilDclDetailedViewDefinitionGUI gui forwarding to ilpageobject");
            default:
                if ($viewdef) {
                    $this->setPresentationTitle($title);
                    $ilLocator->addItem($title, $this->ctrl->getLinkTarget($this, "preview"));
                }

                return parent::executeCommand();
        }
    }

    protected function activate(): void
    {
        $page = $this->getPageObject();
        $page->setActive(true);
        $page->update();
        $this->ctrl->redirect($this, 'edit');
    }

    protected function deactivate(): void
    {
        $page = $this->getPageObject();
        $page->setActive(false);
        $page->update();
        $this->ctrl->redirect($this, 'edit');
    }

    public function confirmDelete(): void
    {
        global $DIC;
        $ilCtrl = $DIC['ilCtrl'];
        $lng = $DIC['lng'];
        $tpl = $DIC['tpl'];

        $conf = new ilConfirmationGUI();
        $conf->setFormAction($ilCtrl->getFormAction($this));
        $conf->setHeaderText($lng->txt('dcl_confirm_delete_detailed_view_title'));

        $conf->addItem('tableview', $this->tableview_id, $lng->txt('dcl_confirm_delete_detailed_view_text'));

        $conf->setConfirm($lng->txt('delete'), 'deleteView');
        $conf->setCancel($lng->txt('cancel'), 'cancelDelete');

        $tpl->setContent($conf->getHTML());
    }

    public function cancelDelete(): void
    {
        global $DIC;
        $ilCtrl = $DIC['ilCtrl'];

        $ilCtrl->redirect($this, "edit");
    }

    public function deleteView(): void
    {
        global $DIC;
        $ilCtrl = $DIC['ilCtrl'];
        $lng = $DIC['lng'];

        if ($this->tableview_id && ilDclDetailedViewDefinition::exists($this->tableview_id)) {
            $pageObject = new ilDclDetailedViewDefinition($this->tableview_id);
            $pageObject->delete();
        }

        $this->tpl->setOnScreenMessage('success', $lng->txt("dcl_empty_detailed_view_success"), true);

        // Bug fix for mantis 22537: Redirect to settings-tab instead of fields-tab. This solves the problem and is more intuitive.
        $ilCtrl->redirectByClass("ilDclTableViewEditGUI", "editGeneralSettings");
    }

    /**
     * Finalizing output processing
     * @param string $a_output
     * @return string
     */
    public function postOutputProcessing(string $a_output): string
    {
        // You can use this to parse placeholders and the like before outputting

        if ($this->getOutputMode() == ilPageObjectGUI::PREVIEW) {
            //page preview is not being used inside DataCollections - if you are here, something's probably wrong

            //
            //			// :TODO: find a suitable presentation for matched placeholders
            //			$allp = ilDataCollectionRecordViewViewdefinition::getAvailablePlaceholders($this->table_id, true);
            //			foreach ($allp as $id => $item) {
            //				$parsed_item = new ilTextInputGUI("", "fields[" . $item->getId() . "]");
            //				$parsed_item = $parsed_item->getToolbarHTML();
            //
            //				$a_output = str_replace($id, $item->getTitle() . ": " . $parsed_item, $a_output);
            //			}
        } // editor
        else {
            if ($this->getOutputMode() == ilPageObjectGUI::EDIT) {
                $allp = $this->getPageObject()->getAvailablePlaceholders();

                // :TODO: find a suitable markup for matched placeholders
                foreach ($allp as $item) {
                    $a_output = str_replace($item, "<span style=\"color:green\">" . $item . "</span>", $a_output);
                }
            }
        }

        return $a_output;
    }

    /**
     * Save table entries
     */
    public function saveTable(): void
    {
        /**
         * @var ilDclTableViewFieldSetting $setting
         */
        foreach ($this->tableview->getFieldSettings() as $setting) {
            if (!$setting->getFieldObject()->isStandardField() || $setting->getFieldObject()->getId() === 'owner') {

                // Radio Inputs
                foreach (array("RadioGroup") as $attribute) {
                    $selection_key = $attribute . '_' . $setting->getField();
                    $selection = $this->http->wrapper()->post()->retrieve(
                        $selection_key,
                        $this->refinery->kindlyTo()->string()
                    );
                    $selected_radio_attribute = explode("_", $selection)[0];

                    foreach (array("LockedEdit", "RequiredEdit", "VisibleEdit", "NotVisibleEdit") as $radio_attribute) {
                        $result = false;

                        if ($selected_radio_attribute === $radio_attribute) {
                            $result = true;
                        }

                        $setting->{'set' . $radio_attribute}($result);
                    }
                }

                // Text Inputs
                foreach (array("DefaultValue") as $attribute) {
                    $key = $attribute . '_' . $setting->getField();
                    if ($this->http->wrapper()->post()->has($key)) {
                        $attribute_value = $this->http->wrapper()->post()->retrieve(
                            $key,
                            $this->refinery->kindlyTo()->string()
                        );
                    } else {
                        $attribute_value = "";
                    }

                    $setting->{'set' . $attribute}($attribute_value);
                }

                $setting->update();
            }
        }

        // Set Workflow flag to true
        $view = ilDclTableView::getCollection()->where(array("id" => filter_input(INPUT_GET, "tableview_id")))->first();
        if (!is_null($view)) {
            $view->setStepE(true);
            $view->save();
        }

        $this->tpl->setOnScreenMessage('success', $this->lng->txt('dcl_msg_tableview_updated'), true);
        $this->ctrl->saveParameter($this, 'tableview_id');
        $this->ctrl->redirect($this, 'presentation');
    }
}
