<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 */

/**
 * Container start objects page GUI class
 *
 * @author Jörg Lützenkirchen <luetzenkirchen@leifos.com>
 *
 * @ilCtrl_Calls ilContainerStartObjectsPageGUI: ilPageEditorGUI, ilEditClipboardGUI, ilMDEditorGUI
 * @ilCtrl_Calls ilContainerStartObjectsPageGUI: ilPublicUserProfileGUI, ilNoteGUI
 * @ilCtrl_Calls ilContainerStartObjectsPageGUI: ilPropertyFormGUI, ilInternalLinkGUI, ilPageMultiLangGUI
 */
class ilContainerStartObjectsPageGUI extends ilPageObjectGUI
{
    /**
    * Constructor
    */
    public function __construct($a_id = 0, $a_old_nr = 0, $a_lang = "")// TODO PHP8-REVIEW Type hints are missing
    {
        parent::__construct("cstr", $a_id, $a_old_nr, false, $a_lang);
    }
}
