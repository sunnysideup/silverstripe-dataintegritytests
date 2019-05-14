<?php

namespace Sunnysideup\DataIntegrityTest;

use BuildTask;
use DB;
use Config;
use SiteTree;



class DataIntegrityMoveFieldUpOrDownClassHierarchy extends BuildTask
{


    /**
     * standard SS variable
     * @var String
     */
    protected $title = "Move data field up or down class (table) hierarchy.";

    /**
     * standard SS variable
     * @var String
     */
    protected $description = "
		This is useful in case you change the hierarchy of classes
		and as a consequence your data ends up in the wrong table.
		To run this task you will first need to run a dev/build -
		after that all the eligible fields will be listed
		and the task gives you the ability to move each field individually as required.
	";


    public function run($request)
    {
        ini_set('max_execution_time', 3000);
        $oldTable = $request->getVar("oldtable");
        $newTable = $request->getVar("newtable");
        $field = $request->getVar("field");
        $forreal = $request->getVar("forreal");
        if ($oldTable && $newTable && $field) {
            if (class_exists($oldTable)) {
                if (class_exists($newTable)) {
                    $oldFields = array_keys(DB::fieldList($oldTable));
                    $newFields = array_keys(DB::fieldList($newTable));
                    $jointFields = array_intersect($oldFields, $newFields);
                    if (in_array($field, $jointFields)) {
                        if ($forreal) {
                            DB::alteration_message("Moving $field from $oldTable to $newTable", "deleted");
                            $sql = "
								UPDATE \"".$newTable."\"
									INNER JOIN \"".$oldTable."\"
									 ON \"".$newTable."\".\"ID\" = \"".$oldTable."\".\"ID\"
								SET \"".$newTable."\".\"".$field."\" = \"".$oldTable."\".\"".$field."\"
								WHERE
									\"".$newTable."\".\"".$field."\" = 0 OR
									\"".$newTable."\".\"".$field."\" IS NULL OR
									\"".$newTable."\".\"".$field."\" = '0.00' OR
									\"".$newTable."\".\"".$field."\" = ''
									;";
                            DB::query($sql);
                            $sql = "
								INSERT IGNORE INTO \"".$newTable."\" (ID, \"$field\")
								SELECT \"".$oldTable."\".ID, \"".$oldTable."\".\"$field\"
								FROM \"".$oldTable."\"
									LEFT JOIN \"".$newTable."\"
									 ON \"".$newTable."\".\"ID\" = \"".$oldTable."\".\"ID\"
								WHERE
									\"".$newTable."\".\"ID\" IS NULL
									;";
                            DB::query($sql);
                            $this->deleteField($oldTable, $field);
                        } else {
                            DB::alteration_message("TESTING a move of $field from $oldTable to $newTable");
                            $sql = "
								SELECT 
									COUNT(\"".$newTable."\".\"ID\") AS C
									FROM \"".$oldTable."\"
										INNER JOIN \"".$newTable."\"
										ON \"".$newTable."\".\"ID\" = \"".$oldTable."\".\"ID\"
									;";
                            $matchingRowCount = DB::query($sql)->value();
                            $sql = "
								SELECT 
									\"".$newTable."\".\"ID\"
									FROM \"".$oldTable."\"
										INNER JOIN \"".$newTable."\"
										ON \"".$newTable."\".\"ID\" = \"".$oldTable."\".\"ID\"
									;";
                            $rows = DB::query($sql);
                            $matchingRows = [];
                            foreach ($rows as $row) {
                                $matchingRows[$row["ID"]] = $row["ID"];
                            }
                            
                            $sql = "
								SELECT 
									\"".$newTable."\".\"ID\",
									\"".$newTable."\".\"".$field."\" AS NEW".$field.",
									\"".$oldTable."\".\"".$field."\" AS OLD".$field."
									FROM \"".$oldTable."\"
										INNER JOIN \"".$newTable."\"
										ON \"".$newTable."\".\"ID\" = \"".$oldTable."\".\"ID\"
								WHERE
									(
										\"".$newTable."\".\"".$field."\" <> \"".$oldTable."\".\"".$field."\"
									)
									OR
									(
										(\"".$newTable."\".\"".$field."\" IS NULL AND \"".$oldTable."\".\"".$field."\" IS NOT NULL)
										 OR
										(\"".$newTable."\".\"".$field."\" IS NOT NULL AND \"".$oldTable."\".\"".$field."\" IS NULL)
									)
									;";
                            $rows = DB::query($sql);
                            if ($rows->numRecords()) {
                                echo "<h3>DIFFERENCES in MATCHING ROWS ($matchingRowCount)</h3><table border=\"1\"><thead><tr><th>ID</th><th>OLD</th><th>NEW</th><th>ACTION</th></tr></thead><tbody>";
                                foreach ($rows as $row) {
                                    $action = "do nothing";
                                    if (!$row["NEW".$field] || $row["NEW".$field] == '0.00') {
                                        $action = "override";
                                    }
                                    echo "<tr><td>".$row["ID"]."</td><td>".$row["OLD".$field]."</td><td>".$row["NEW".$field]."</td><td>".$action."</td></tr>";
                                }
                                echo "</tbody></table>";
                            } else {
                                echo "<p>No differences!</p>";
                            }
                            $sql = "
								SELECT 
									COUNT(\"".$oldTable."\".\"ID\") AS C
									FROM \"".$oldTable."\"
										LEFT JOIN \"".$newTable."\"
										ON \"".$newTable."\".\"ID\" = \"".$oldTable."\".\"ID\"
									WHERE \"".$newTable."\".\"ID\" IS NULL;
									;";
                            $nonMatchingRowCount = DB::query($sql)->value();
                            echo "<h3>Number of rows to insert: ".$nonMatchingRowCount."</h3>";
                            echo "<h2><a href=\"".$this->Link()."?oldtable=$oldTable&newtable=$newTable&field=$field&forreal=1\">move now!</a></h2>";
                        }
                    }
                } else {
                    user_error("Field is not in both tables.  We recommend that you run a <em>dev/build</em> first as this may solve the problem....");
                }
            } else {
                user_error("Specificy valid oldtable using get var");
            }
        }
        echo "<hr />";
        $tablesToCheck = DB::query('SHOW tables');
        $array = [];
        $completed = [];
        foreach ($tablesToCheck as $tableToCheck) {
            $tableToCheck = array_pop($tableToCheck);
            $fieldsToCheck = array_keys(DB::fieldList($tableToCheck));
            $fieldsToCheck = array_diff($fieldsToCheck, array("ID"));
            $array[$tableToCheck] = $fieldsToCheck;
        }
        $testArray1 = $array;
        $testArray2 = $array;
        $link = [];
        foreach ($testArray1 as $testTable1 => $testFields1) {
            foreach ($testArray2 as $testTable2 => $testFields2) {
                if (class_exists($testTable1)) {
                    $parentArray1 = class_parents($testTable1);
                } else {
                    $parentArray1 = array("MATCH");
                }
                if (class_exists($testTable2)) {
                    $parentArray2 = class_parents($testTable2);
                } else {
                    $parentArray2 = array("MATCH");
                }
                if (in_array($testTable2, $parentArray1) || in_array($testTable1, $parentArray2)) {
                    $interSect = array_intersect($testFields1, $testFields2);
                    if (count($interSect)) {
                        if (
                            (
                                isset($completed[$testTable1."_".$testTable2]) ||
                                isset($completed[$testTable2."_".$testTable1])
                            )
                            && (
                                (isset($completed[$testTable1."_".$testTable2]) ? count($completed[$testTable1."_".$testTable2]) : rand(0, 9999999)) == count($interSect) ||
                                (isset($completed[$testTable2."_".$testTable1]) ? count($completed[$testTable2."_".$testTable1]) : rand(0, 9999999)) == count($interSect)
                            )
                        ) {
                            //do nothing
                        } else {
                            $completed[$testTable1."_".$testTable2] = $interSect;

                            $link["movetoparent"] = [];
                            if (in_array("DataObject", $parentArray1)) {
                                $modelFields1 = array_keys((array)Config::inst()->get($testTable1, "db", Config::UNINHERITED)) +
                                $hasOneArray = array_keys((array)Config::inst()->get($testTable1, "has_one", Config::UNINHERITED));
                                $hasOneArray = array_map(
                                    function ($val) {
                                        return $val."ID";
                                    },
                                    $hasOneArray
                                );
                                $modelFields1 + $hasOneArray;
                                //$modelFields1 = array_keys((array)Injector::inst()->get($testTable1)->db()) + array_keys((array)Injector::inst()->get($testTable1)->has_one());
                                foreach ($interSect as $moveableField) {
                                    if (in_array($moveableField, $modelFields1)) {
                                        $link["movetoparent"][$moveableField] = "<a href=\"".$this->Link()."?oldtable=$testTable2&newtable=$testTable1&field=$moveableField\">move from $testTable2 into $testTable1</a>";
                                        ;
                                    }
                                }
                            }
                            $link["movetochild"] = [];
                            if (in_array("DataObject", $parentArray1)) {
                                $modelFields2 = array_keys((array)Config::inst()->get($testTable2, "db", Config::UNINHERITED)) + array_keys((array)Config::inst()->get($testTable2, "has_one", Config::UNINHERITED));
                                $hasOneArray = array_keys((array)Config::inst()->get($testTable2, "has_one", Config::UNINHERITED));
                                $hasOneArray = array_map(
                                    function ($val) {
                                        return $val."ID";
                                    },
                                    $hasOneArray
                                );
                                $modelFields2 + $hasOneArray;
                                //$modelFields2 = array_keys((array)Injector::inst()->get($testTable2)->db()) + array_keys((array)Injector::inst()->get($testTable2)->has_one());
                                foreach ($interSect as $moveableField) {
                                    if (in_array($moveableField, $modelFields2)) {
                                        $link["movetochild"][$moveableField] = "<a href=\"".$this->Link()."?oldtable=$testTable1&newtable=$testTable2&field=$moveableField\">move from $testTable1  into $testTable2</a>";
                                    }
                                }
                            }
                            $str = "$testTable1 &lt;&gt; $testTable2<br /><ul>";
                            foreach ($interSect as $moveableField) {
                                $str .= "<li>$moveableField: ";
                                
                                if (isset($link["movetoparent"][$moveableField])) {
                                    $str .= $link["movetoparent"][$moveableField];
                                }
                                if (isset($link["movetoparent"][$moveableField]) && isset($link["movetochild"][$moveableField])) {
                                    $str .= " ||| ";
                                }
                                if (isset($link["movetochild"][$moveableField])) {
                                    $str .= $link["movetochild"][$moveableField];
                                }
                                $str .= "</li>";
                            }
                            $str .= "</ul>";
                            DB::alteration_message($str);
                        }
                    }
                }
            }
        }
        echo "<h1>======================== THE END ====================== </h1>";
    }

    /**
     *
     *
     * @return string
     */
    protected function Link()
    {
        return "/dev/tasks/DataIntegrityMoveFieldUpOrDownClassHierarchy/";
    }

    /**
     *
     * @param string $table
     * @param string $field
     *
     * @return boolean
     */
    private function deleteField($table, $field)
    {
        $fields = array_keys(DB::fieldList($table));
        if (!DB::query("SHOW TABLES LIKE '".$table."'")->value()) {
            DB::alteration_message("tried to delete $table.$field but TABLE does not exist", "deleted");
            return false;
        }
        if (!class_exists($table)) {
            DB::alteration_message("tried to delete $table.$field but CLASS does not exist", "deleted");
            return false;
        }
        if (!in_array($field, $fields)) {
            DB::alteration_message("tried to delete $table.$field but FIELD does not exist", "deleted");
            return false;
        } else {
            DB::alteration_message("Deleting $field in $table", "deleted");
            DB::query('ALTER TABLE "'.$table.'" DROP "'.$field.'";');
            $obj = singleton($table);
            //to do: make this more reliable - checking for versioning rather than SiteTree
            if ($obj instanceof SiteTree) {
                DB::query('ALTER TABLE "'.$table.'_Live" DROP "'.$field.'";');
                DB::alteration_message("Deleted $field in {$table}_Live", "deleted");
                DB::query('ALTER TABLE "'.$table.'_versions" DROP "'.$field.'";');
                DB::alteration_message("Deleted $field in {$table}_versions", "deleted");
            }
            return true;
        }
    }
}
