<?php

namespace Sunnysideup\DataIntegrityTest\Api;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;

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
                // the value is passed as a parameter (prepared query): no need to escape it
                $sql = sprintf('UPDATE "%s" SET "%s"."%s" = ?', $table, $table, $field);
                $where = [];
                if ($id) {
                    $where[] = sprintf('  "%s"."ID" = ', $table) . $id;
                }

                if (! $replace) {
                    $where[] = sprintf(" \"%s\".\"%s\" IS NULL OR \"%s\".\"%s\" = '' OR \"%s\".\"%s\" = 0 ", $table, $field, $table, $field, $table, $field);
                }

                $wherePhrase = '';
                if ($where !== []) {
                    $wherePhrase = ' WHERE ( ' . implode(') AND (', $where) . ' )';
                }

                $result = DB::query(sprintf('SELECT COUNT("%s"."ID") C FROM "%s" ', $table, $table) . $wherePhrase);
                if ($result && $result->value()) {
                    $sql .= $wherePhrase;
                    DB::prepared_query($sql, [$value]);
                    DB::alteration_message(sprintf('Updated %s in %s to %s ', $field, $table, $value), 'added');
                }
            }
        }
    }
}
