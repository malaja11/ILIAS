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

class ilDclBooleanRecordFieldModel extends ilDclBaseRecordFieldModel
{
    /**
     * @param int|string $value
     */
    public function parseValue($value): int
    {
        return $value ? 1 : 0;
    }

    /**
     * Function to parse incoming data from form input value $value. returns the string/number/etc. to store in the database.
     * @param int|string $value
     */
    public function parseExportValue($value): int
    {
        return $value ? 1 : 0;
    }
}
