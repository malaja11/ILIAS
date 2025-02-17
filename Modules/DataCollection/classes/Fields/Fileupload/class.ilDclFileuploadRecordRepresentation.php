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

class ilDclFileuploadRecordRepresentation extends ilDclBaseRecordRepresentation
{
    /**
     * Outputs html of a certain field
     */
    public function getHTML(bool $link = true, array $options = []): string
    {
        $value = $this->getRecordField()->getValue();

        // the file is only temporary uploaded. Still need to be confirmed before stored
        $has_ilfilehash = $this->http->wrapper()->post()->has('ilfilehash');
        if (is_array($value) && $has_ilfilehash) {
            $ilfilehash = $this->http->wrapper()->post()->retrieve('ilfilehash', $this->refinery->kindlyTo()->string());
            $this->ctrl->setParameterByClass("ildclrecordlistgui", "ilfilehash", $ilfilehash);
            $this->ctrl->setParameterByClass(
                "ildclrecordlistgui",
                "field_id",
                $this->getRecordField()->getField()->getId()
            );

            return '<a href="' . $this->ctrl->getLinkTargetByClass(
                "ildclrecordlistgui",
                "sendFile"
            ) . '">' . $value['name'] . '</a>';
        } else {
            if (!ilObject2::_exists((int) $value) || ilObject2::_lookupType((int) $value, false) !== 'file') {
                return "";
            }
        }

        $file_obj = new ilObjFile($value, false);
        $this->ctrl->setParameterByClass(
            "ildclrecordlistgui",
            "record_id",
            $this->getRecordField()->getRecord()->getId()
        );
        $this->ctrl->setParameterByClass(
            "ildclrecordlistgui",
            "field_id",
            $this->getRecordField()->getField()->getId()
        );

        $html = '<a href="' . $this->ctrl->getLinkTargetByClass(
            "ildclrecordlistgui",
            "sendFile"
        ) . '">' . $file_obj->getFileName() . '</a>';
        if (ilPreview::hasPreview($file_obj->getId())) {
            ilPreview::createPreview($file_obj); // Create preview if not already existing

            $ref_id = $this->http->wrapper()->query()->retrieve('ref_id', $this->refinery->kindlyTo()->int());

            $preview = new ilPreviewGUI(
                $ref_id,
                ilPreviewGUI::CONTEXT_REPOSITORY,
                $file_obj->getId(),
                $this->access
            );
            $preview_status = ilPreview::lookupRenderStatus($file_obj->getId());
            $preview_status_class = "";
            $preview_text_topic = "preview_show";
            if ($preview_status == ilPreview::RENDER_STATUS_NONE) {
                $preview_status_class = "ilPreviewStatusNone";
                $preview_text_topic = "preview_none";
            }
            $wrapper_html_id = 'record_field_' . $this->getRecordField()->getId();
            $script_preview_click = $preview->getJSCall($wrapper_html_id);
            $preview_title = $this->lng->txt($preview_text_topic);
            $preview_icon = ilUtil::getImagePath("preview.png");
            $html = '<div id="' . $wrapper_html_id . '">' . $html;
            $html .= '<span class="il_ContainerItemPreview ' . $preview_status_class . '"><a href="javascript:void(0);" onclick="'
                . $script_preview_click . '" title="' . $preview_title . '"><img src="' . $preview_icon
                . '" height="16" width="16"></a></span></div>';
        }

        return $html;
    }

    /**
     * function parses stored value to the variable needed to fill into the form for editing.
     * @param array|string $value
     * @return array|string
     */
    public function parseFormInput($value)
    {
        if (is_array($value)) {
            return $value;
        }

        if (!ilObject2::_exists((int) $value) || ilObject2::_lookupType((int) $value) !== 'file') {
            return "";
        }

        $file_obj = new ilObjFile($value, false);

        //$input = ilObjFile::_lookupAbsolutePath($value);
        return $file_obj->getFileName();
    }
}
