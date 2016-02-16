<?php


class DataIntegrityMoveFieldUpOrDownClassHierarchy extends BuildTask {


	/**
	 * standard SS variable
	 * @var String
	 */
	protected $title = "Move field up or down class hierarchy";

	/**
	 * standard SS variable
	 * @var String
	 */
	protected $description = "This is useful in case you change the hierarchy of classes and data ends up in the wrong field. You first need to run a dev/build - after that all the eligible fields will be move from old Table to new Table";


	function run($request) {
		ini_set('max_execution_time', 3000);
		$oldTable = $request->getVar("oldtable");
		$newTable = $request->getVar("newtable");
		if(!$oldTable && !$newTable) {
			$tables = DB::query('SHOW tables');
			$array = array();
			$completed = array();
			foreach ($tables as $table) {
				$table = array_pop($table);
				$fields = $this->swapArray(DB::fieldList($table));
				$fields = array_diff($fields, array("ID"));
				$array[$table] = $fields;
			}
			$testArray1 = $array;
			$testArray2 = $array;
			$link = array();
			foreach($testArray1 as $testTable1 => $testFields1) {
				foreach($testArray2 as $testTable2 => $testFields2) {
					if(class_exists($testTable1)) {
						$parentArray1 = class_parents($testTable1);
					}
					else {
						$parentArray1 = array("MATCH");
					}
					if(class_exists($testTable2)) {
						$parentArray2 = class_parents($testTable2);
					}
					else {
						$parentArray2 = array("MATCH");
					}
					if(in_array($testTable2, $parentArray1) || in_array($testTable1, $parentArray2)) {
						$interSect = array_intersect($testFields1, $testFields2);
						if(count($interSect)) {
							if(
								(
									isset($completed[$testTable1."_".$testTable2]) ||
									isset($completed[$testTable2."_".$testTable1])
								)
								&& (
									(isset($completed[$testTable1."_".$testTable2]) ? count($completed[$testTable1."_".$testTable2]) : rand(0,9999999)) == count($interSect) ||
									(isset($completed[$testTable2."_".$testTable1]) ? count($completed[$testTable2."_".$testTable1]) : rand(0,9999999)) == count($interSect)
								)
							) {
								//do nothing
							}
							else {

								$completed[$testTable1."_".$testTable2] = $interSect;

								$link["backward"] = "<a href=\"".$this->Link()."?oldtable=$testTable2&newtable=$testTable1\">move $testTable2 fields into $testTable1</a>";
								if(in_array("DataObject", $parentArray1)) {
									$modelFields1 = array_keys((array)Config::inst()->get($testTable1, "db", Config::UNINHERITED )) +
									$hasOneArray = array_keys((array)Config::inst()->get($testTable1, "has_one", Config::UNINHERITED ));
									$hasOneArray = array_map(
										function($val) {return $val."ID";},
										$hasOneArray
									);
									$modelFields1 + $hasOneArray;
									//$modelFields1 = array_keys((array)Injector::inst()->get($testTable1)->db()) + array_keys((array)Injector::inst()->get($testTable1)->has_one());
									foreach($interSect as $moveableField) {
										if(!in_array($moveableField, $modelFields1)) {
											$link["backward"] = "";
										}
									}
								}
								$link["forward"] = "<a href=\"".$this->Link()."?oldtable=$testTable1&newtable=$testTable2\">move $testTable1 fields into $testTable2</a>";
								if(in_array("DataObject", $parentArray1)) {
									$modelFields2 = array_keys((array)Config::inst()->get($testTable2, "db", Config::UNINHERITED )) + array_keys((array)Config::inst()->get($testTable2, "has_one", Config::UNINHERITED ));
									$hasOneArray = array_keys((array)Config::inst()->get($testTable2, "has_one", Config::UNINHERITED ));
									$hasOneArray = array_map(
										function($val) {return $val."ID";},
										$hasOneArray
									);
									$modelFields2 + $hasOneArray;
									//$modelFields2 = array_keys((array)Injector::inst()->get($testTable2)->db()) + array_keys((array)Injector::inst()->get($testTable2)->has_one());
									foreach($interSect as $moveableField) {
										if(!in_array($moveableField, $modelFields2)) {
											$link["forward"] = "";
										}
									}
								}
								$str = array();
								if($link["backward"]){
									$str[] = $link["backward"];
								}
								if($link["forward"]){
									$str[] = $link["forward"];
								}
								if(!count($str)) {
									$str[] = "fields missing in both $testTable1 and $testTable2";
								}
								DB::alteration_message(implode(" ||| ", $str)."<br /><ul><li>".implode("</li><li>", $interSect)."</li></ul>");
							}
						}
					}
				}
			}
		}
		else {
			if(class_exists($oldTable)) {
				if(class_exists($newTable)) {
					$oldFields = $this->swapArray(DB::fieldList($oldTable));
					$newFields = $this->swapArray(DB::fieldList($newTable));
					$fields = array_intersect($oldFields, $newFields);
					foreach($fields as $field) {
						DB::alteration_message("Moving $field from $oldTable to $newTable");
						$sql = "
							UPDATE \"".$newTable."\"
								INNER JOIN
								 ON \"".$newTable."\".\"ID\" = \"".$oldTable."\".\"ID\"
							SET \"".$newTable."\".\"".$field."\" = \"".$oldTable."\".\"".$field."\"
							WHERE
								\"".$newTable."\".\"".$field."\" = 0 OR
								\"".$newTable."\".\"".$field."\" IS NULL OR
								\"".$newTable."\".\"".$field."\" = ''
								;";
						$this->deleteField($oldTable, $field);
					}
				}
				else {
					user_error("Specificy valid newtable using get var");
				}
			}
			else {
				user_error("Specificy valid oldtable using get var");
			}
		}
		echo "<h1>======================== THE END ====================== </h1>";
	}

	protected function Link(){
		return "/dev/tasks/DataIntegrityMoveFieldUpOrDownClassHierarchy/";
	}



	private function deleteField($table, $field) {
		$fields = $this->swapArray(DB::fieldList($table));
		if(!DB::query("SHOW TABLES LIKE '".$table."'")->value()) {
			DB::alteration_message ("tried to delete $table.$field but TABLE does not exist", "deleted");
			return false;
		}
		if(!class_exists($table)){
			DB::alteration_message ("tried to delete $table.$field but CLASS does not exist", "deleted");
			return false;
		}
		if(!in_array($field, $fields)) {
			DB::alteration_message ("tried to delete $table.$field but FIELD does not exist", "deleted");
			return false;
		}
		else {
			DB::alteration_message ("Deleting $field in $table", "deleted");
			DB::query('ALTER TABLE "'.$table.'" DROP "'.$field.'";');
			$obj = singleton($table);
			//to do: make this more reliable - checking for versioning rather than SiteTree
			if($obj instanceof SiteTree) {
				DB::query('ALTER TABLE "'.$table.'_Live" DROP "'.$field.'";');
				DB::alteration_message ("Deleted $field in {$table}_Live", "deleted");
				DB::query('ALTER TABLE "'.$table.'_versions" DROP "'.$field.'";');
				DB::alteration_message ("Deleted $field in {$table}_versions", "deleted");
			}
			return true;
		}
	}

	private function swapArray($array) {
		$newArray = array();
		if(is_array($array)) {
			foreach($array as $key => $value) {
				$newArray[] = $key;
			}
		}
		return $newArray;
	}


}
