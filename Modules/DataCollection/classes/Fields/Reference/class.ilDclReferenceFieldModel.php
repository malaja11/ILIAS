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

class ilDclReferenceFieldModel extends ilDclBaseFieldModel
{
    public const PROP_REFERENCE = 'table_id';
    public const PROP_N_REFERENCE = 'multiple_selection';

    /**
     * Returns a query-object for building the record-loader-sql-query
     * @param string  $direction
     * @param boolean $sort_by_status The specific sort object is a status field
     * @return null|ilDclRecordQueryObject
     */
    public function getRecordQuerySortObject(
        string $direction = "asc",
        bool $sort_by_status = false
    ): ?ilDclRecordQueryObject {
        global $DIC;
        $ilDB = $DIC['ilDB'];

        if (
            $this->hasProperty(self::PROP_N_REFERENCE) ||
            $this->getProperty(self::PROP_REFERENCE) === null ||
            ilDclCache::getFieldCache((int) $this->getProperty(self::PROP_REFERENCE))->getTableId() === 0
        ) {
            return null;
        }

        $ref_field = ilDclCache::getFieldCache((int) $this->getProperty(self::PROP_REFERENCE));

        $select_str = "stloc_{$this->getId()}_joined.value AS field_{$this->getId()},";
        $join_str = "LEFT JOIN il_dcl_record_field AS record_field_{$this->getId()} ON (record_field_{$this->getId()}.record_id = record.id AND record_field_{$this->getId()}.field_id = "
            . $ilDB->quote($this->getId(), 'integer') . ") ";
        $join_str .= "LEFT JOIN il_dcl_stloc{$this->getStorageLocation()}_value AS stloc_{$this->getId()} ON (stloc_{$this->getId()}.record_field_id = record_field_{$this->getId()}.id) ";
        $join_str .= "LEFT JOIN il_dcl_record_field AS record_field_{$this->getId()}_joined ON (record_field_{$this->getId()}_joined.record_id = stloc_{$this->getId()}.value AND record_field_{$this->getId()}_joined.field_id = "
            . $ilDB->quote($ref_field->getId(), 'integer') . ") ";
        $join_str .= "LEFT JOIN il_dcl_stloc{$ref_field->getStorageLocation()}_value AS stloc_{$this->getId()}_joined ON (stloc_{$this->getId()}_joined.record_field_id = record_field_{$this->getId()}_joined.id) ";

        $sql_obj = new ilDclRecordQueryObject();
        $sql_obj->setSelectStatement($select_str);
        $sql_obj->setJoinStatement($join_str);
        $sql_obj->setOrderStatement("field_{$this->getId()} " . $direction . ", ID ASC");

        return $sql_obj;
    }

    public function getRecordQueryFilterObject(
        $filter_value = "",
        ?ilDclBaseFieldModel $sort_field = null
    ): ?ilDclRecordQueryObject {
        global $DIC;
        $ilDB = $DIC['ilDB'];

        $n_ref = $this->getProperty(ilDclBaseFieldModel::PROP_N_REFERENCE);

        $join_str
            = " LEFT JOIN il_dcl_record_field AS filter_record_field_{$this->getId()} ON (filter_record_field_{$this->getId()}.record_id = record.id AND filter_record_field_{$this->getId()}.field_id = "
            . $ilDB->quote($this->getId(), 'integer') . ") ";
        $join_str .= " LEFT JOIN il_dcl_stloc{$this->getStorageLocation()}_value AS filter_stloc_{$this->getId()} ON (filter_stloc_{$this->getId()}.record_field_id = filter_record_field_{$this->getId()}.id) ";

        $where_str = " AND ";

        if ($filter_value == 'none') {
            $where_str .= "("
                . "filter_stloc_{$this->getId()}.value IS NULL "
                . " OR filter_stloc_{$this->getId()}.value = " . $ilDB->quote("", 'text')
                . " OR filter_stloc_{$this->getId()}.value = " . $ilDB->quote("[]", 'text')
                . ") ";
        } else {
            if ($n_ref) {
                $where_str
                    .= " filter_stloc_{$this->getId()}.value LIKE "
                    . $ilDB->quote("%$filter_value%", 'text');
            } else {
                $where_str
                    .= " filter_stloc_{$this->getId()}.value = "
                    . $ilDB->quote($filter_value, 'integer');
            }
        }

        $sql_obj = new ilDclRecordQueryObject();
        $sql_obj->setJoinStatement($join_str);
        $sql_obj->setWhereStatement($where_str);

        return $sql_obj;
    }

    public function getValidFieldProperties(): array
    {
        return [ilDclBaseFieldModel::PROP_REFERENCE,
                ilDclBaseFieldModel::PROP_REFERENCE_LINK,
                ilDclBaseFieldModel::PROP_N_REFERENCE
        ];
    }

    public function allowFilterInListView(): bool
    {
        //A reference-field is not filterable if the referenced field is of datatype MOB or File
        $ref_field = $this->getFieldRef();

        return !($ref_field->getDatatypeId() == ilDclDatatype::INPUTFORMAT_MOB
            || $ref_field->getDatatypeId() == ilDclDatatype::INPUTFORMAT_FILE);
    }

    public function getFieldRef(): ilDclBaseFieldModel
    {
        return ilDclCache::getFieldCache((int) $this->getProperty(ilDclBaseFieldModel::PROP_REFERENCE));
    }

    public function afterClone(array $records): void
    {
        /** @var ilDclReferenceFieldModel $clone */
        $clone = ilDclCache::getCloneOf($this->getId(), ilDclCache::TYPE_FIELD);
        $reference_clone = ilDclCache::getCloneOf(
            (int) $clone->getProperty(ilDclBaseFieldModel::PROP_REFERENCE),
            ilDclCache::TYPE_FIELD
        );
        if ($reference_clone) {
            $this->setProperty(ilDclBaseFieldModel::PROP_REFERENCE, $reference_clone->getId());
            $this->updateProperties();
        }
        parent::afterClone($records);
    }
}
