<?php

namespace Sunnysideup\DataIntegrityTest\Api;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\View\ViewableData;


class DataIntegrityTestDefaultEntries
{
    public static function update($baseTable, $field, $value, $id = 0, $replace = false, $addLive = false)
    {
        $object = DataObject::get_one($baseTable);
        if ($object) {
            $tableArray = [$baseTable];
            if ($object instanceof SiteTree) {
                $tableArray[] = $baseTable . '_Live';
            }
            foreach ($tableArray as $table) {
                $value = Convert::raw2sql($value);
                $sql = "UPDATE \"$table\" SET \"$table\".\"$field\" = '$value'";
                $where = [];
                if ($id) {
                    $where[] = "  \"$table\".\"ID\" = " . $id;
                }
                if (!$replace) {
                    $where[] = " \"$table\".\"$field\" IS NULL OR \"$table\".\"$field\" = '' OR \"$table\".\"$field\" = 0 ";
                }
                $wherePhrase = '';
                if (count($where)) {
                    $wherePhrase = ' WHERE ( ' . implode(') AND (', $where) . ' )';
                }
                $result = DB::query("SELECT COUNT(\"$table\".\"ID\") C FROM \"$table\" " . $wherePhrase);
                if ($result && $result->value()) {
                    $sql .= $wherePhrase;
                    DB::query($sql);
                    DB::alteration_message("Updated $field in $table to ${value} ", 'added');
                }
            }
        }
    }
}
