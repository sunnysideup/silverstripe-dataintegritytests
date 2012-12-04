<?php


class DataIntegrityTest extends DevelopmentAdmin {

	protected static $warning = "are you sure - this step is irreversible! - MAKE SURE TO MAKE A BACKUP OF YOUR DATABASE FIRST";

	protected static $test_array = array(
		"In SiteTree_Live but not in SiteTree" =>
    	"SELECT SiteTree.ID, SiteTree.Title FROM SiteTree_Live RIGHT JOIN SiteTree ON SiteTree_Live.ID = SiteTree.ID WHERE SiteTree.ID IS NULL;",
		"ParentID does not exist in SiteTree" =>
			"SELECT SiteTree.ID, SiteTree.Title FROM SiteTree RIGHT JOIN SiteTree Parent ON SiteTree.ParentID = Parent.ID Where SiteTree.ID IS NULL and SiteTree.ParentID <> 0;",
		"ParentID does not exists in SiteTree_Live" =>
			"SELECT SiteTree_Live.ID, SiteTree_Live.Title FROM SiteTree_Live RIGHT JOIN SiteTree_Live Parent ON SiteTree_Live.ParentID = Parent.ID Where SiteTree_Live.ID IS NULL and SiteTree_Live.ParentID <> 0;",
	);

	protected static $global_exceptions = array(
		"EditableFormField" => "Version",
		"EditableOption" => "Version",
		"OrderItem" => "Version"
	);

	/**
	*@param array = should be provided as follows: array("Member.UselessField1", "Member.UselessField2", "SiteTree.UselessField3")
	*/
	protected static $fields_to_delete = array();
		static function set_fields_to_delete($array) {self::$fields_to_delete = $array;}

	function init() {
		//this checks security
		parent::init();
	}

	function index() {
		echo "<h2>Database Administration Helpers</h2>";
		echo "<p><a href=\"".Director::absoluteBaseURL()."dbintegritycheck/obsoletefields/\">Prepare a list of obsolete fields.</a></p>";
		echo "<p><a href=\"".Director::absoluteBaseURL()."dbintegritycheck/deletemarkedfields/\" onclick=\"return confirm('".self::$warning."');\">Delete fields listed in _config.</a></p>";
		echo "<p><a href=\"".Director::absoluteBaseURL()."dbintegritycheck/obsoletefields/immediately/destroyed/\" onclick=\"return confirm('".self::$warning."');\">Delete obsolete fields now!</a></p>";
	}

	public function obsoletefields(SS_HTTPRequest $request) {
		$check1 = $request->param("ID");
		$check2 = $request->param("OtherID");
		$deleteNow = false;
		if($check1 == "immediately" && $check2 = "destroyed") {
			$deleteNow = true;
			increase_time_limit_to(600);
		}
		$dataClasses = ClassInfo::subclassesFor('DataObject');
		$notCheckedArray = array();
		//remove dataobject
		array_shift($dataClasses);
		$rows = DB::query("SHOW TABLES;");
		$actualTables = array();
		if($rows) {
			foreach($rows as $key => $item) {
				foreach($item as $table) {
					$actualTables[$table] = $table;
				}
			}
		}
		echo "<h1>Report of fields that may not be required.</h1>";
		echo "<p>NOTE: it may contain fields that are actually required (e.g. versioning or many-many relationships) and it may also leave out some obsolete fields.  Use as a guide only.</p>";
		foreach($dataClasses as $dataClass) {
			// Check if class exists before trying to instantiate - this sidesteps any manifest weirdness
			if(class_exists($dataClass)) {
				$dataObject = singleton($dataClass);
				$requiredFields = $this->swapArray(DataObject::database_fields($dataObject->ClassName));
				if(count($requiredFields)) {
					foreach($requiredFields as $field) {
						if(!$dataObject->hasOwnTableDatabaseField($field)) {
							DB::alteration_message ("  **** $dataClass.$field DOES NOT EXIST BUT IT SHOULD BE THERE!", "deleted");
						}
					}
					$actualFields = $this->swapArray(DB::fieldList($dataClass));
					if($actualFields) {
						foreach($actualFields as $actualField) {
							if($deleteNow) {
								$link = " !!!!!!!!!!! DELETED !!!!!!!!!";
							}
							else {
								$link = "<a href=\"".Director::absoluteBaseURL()."dbintegritycheck/deleteonefield/".$dataClass."/".$actualField."/\" onclick=\"return confirm('".self::$warning."');\">delete field</a><br /><br />";
							}
							if(!in_array($actualField, array("ID", "Version"))) {
								if(!in_array($actualField, $requiredFields)) {
									DB::alteration_message ("$dataClass.$actualField $link", "deleted");
									if($deleteNow) {
										$this->deleteField($dataClass, $actualField);
									}
								}
							}
							if($actualField == "Version" && !in_array($actualField, $requiredFields)) {
								$versioningPresent = $dataObject->hasVersioning();
								if(!$versioningPresent) {
									DB::alteration_message ("$dataClass.$actualField $link", "deleted");
									if($deleteNow) {
										$this->deleteField($dataClass, $actualField);
									}
								}
							}
						}
					}
					$rawCount = DB::query("SELECT COUNT(\"ID\") FROM \"$dataClass\"")->value();
					if($rawCount < 1000){
						Versioned::set_reading_mode("Stage");
						$realCount = 0;
						$objects = DataObject::get($dataClass, "\"$dataClass\".ID > 0");
						if($objects) {
							$realCount = $objects->count();
						}
						if($rawCount != $realCount) {
							DB::alteration_message("The DB Table Row Count ($rawCount) does not seem to match the DataObject Count ($realCount) for $dataClass.  This could indicate an error as generally these numbers should match.", "deleted");
						}
						if($realCount > $rawCount) {
							$objects = DataObject::get($dataClass);
							foreach($objects as $object) {
								if(DB::query("SELECT COUNT(\"ID\") FROM \"$dataClass\" WHERE \"ID\" = ".$object->ID.";")->value() != 1) {
									DB::alteration_message("Now trying to recreate missing items....", "created");
									$object->write(true, false, true, false);
								}
							}
						}
					}
					else {
						DB::alteration_message("<span style=\"color: orange\">We cant fully check $dataClass because it as more than 1000 records</span>");
					}
					unset($actualTables[$dataClass]);
				}
				else {
					if( mysql_num_rows( mysql_query("SHOW TABLES LIKE '".$dataClass."'"))) {
						DB::alteration_message ("  **** The $dataClass table exists, but according to the data-scheme it should not be there ", "deleted");
					}
					else {
						$notCheckedArray[] = $dataClass;
					}
				}
			}
		}
		if(count($notCheckedArray)) {
			echo "<h3>Did not check the following classes as no fields appear to be required and hence there is no database table.</h3>";
			foreach($notCheckedArray as $table) {
				if(mysql_num_rows( mysql_query("SHOW TABLES LIKE '".$table."'"))) {
					DB::alteration_message ($table ." - NOTE: a table exists for this Class, this is an unexpected result", "deleted");
				}
				DB::alteration_message ($table, "created");
			}
		}
		if(count($actualTables)) {
			echo "<h3>Other Tables in Database not directly linked to a Silverstripe DataObject:</h3>";
			foreach($actualTables as $table) {
				$show = true;
				if(class_exists($table)) {
					$classExistsMessage = " a PHP class with this name exists.";
					$obj = singleton($table);
					//to do: make this more reliable - checking for versioning rather than SiteTree
					if($obj instanceof SiteTree) {
						$show = false;
					}
				}
				else {
					$classExistsMessage = " NO PHP class with this name exists.";
					if(substr($table, -5) == "_Live") {
						$show = false;
					}
					if(substr($table, -9) == "_versions") {
						$show = false;
					}
					//many 2 many tables...
					$array = explode("_", $table);
					if(count($array) == 2) {
						if(class_exists($array[0]) && class_exists($array[1])) {
							$show = false;
						}
					}
				}
				if($show) {
					DB::alteration_message ($table." - ".$classExistsMessage, "created");
				}
			}
		}

		echo "<a href=\"".Director::absoluteURL("/dbintegritycheck")."\">back to main menu.</a>";
	}

	public function deletemarkedfields() {
		if(is_array(self::$fields_to_delete)) {
			if(count(self::$fields_to_delete)) {
				foreach(self::$fields_to_delete as $key => $tableDotField) {
					$tableFieldArray = explode(".", $tableDotField);
					$this->deleteField($tableFieldArray[0], $tableFieldArray[1]);
				}
			}
			else {
				DB::alteration_message("there are no fields to delete", "created");
			}
		}
		else {
			user_error("you need to select these fields to be deleted first (DataIntegrityTest::set_fields_to_delete)");
		}
	}

	public function deleteonefield(SS_HTTPRequest $request) {
		$table = $request->param("ID");
		$field = $request->param("OtherID");
		if(!$table) {
			user_error("no table has been specified", E_USER_WARNING);
		}
		if(!$field) {
			user_error("no field has been specified", E_USER_WARNING);
		}
		if($this->deleteField($table, $field)) {
			DB::alteration_message("successfully deleted $field from $table now", "deleted");
		}
		DB::alteration_message("<a href=\"".Director::absoluteURL("dbintegritycheck/obsoletefields")."\">return to list of obsolete fields</a>", "created");

	}

	protected function deleteField($table, $field) {
		$fields = $this->swapArray(DB::fieldList($table));
		if(count(self::$global_exceptions)) {
			foreach(self::$global_exceptions as $exceptionTable => $exceptionField) {
				if($exceptionTable == $table && $exceptionField == $field) {
					DB::alteration_message ("tried to delete $table.$field but this is listed as a global exception and can not be deleted", "created");
					return false;
				}
			}
		}
		if(!mysql_num_rows( mysql_query("SHOW TABLES LIKE '".$table."'"))) {
			DB::alteration_message ("tried to delete $table.$field but TABLE does not exist", "created");
			return false;
		}
		if(!class_exists($table)){
			DB::alteration_message ("tried to delete $table.$field but CLASS does not exist", "created");
			return false;
		}
		if(!in_array($field, $fields)) {
			DB::alteration_message ("tried to delete $table.$field but FIELD does not exist", "created");
			return false;
		}
		else {
			DB::query('ALTER TABLE `'.$table.'` DROP `'.$field.'`;');
			DB::alteration_message ("Deleted $field in $table", "deleted");
			$obj = singleton($table);
			//to do: make this more reliable - checking for versioning rather than SiteTree
			if($obj instanceof SiteTree) {
				DB::query('ALTER TABLE `'.$table.'_Live` DROP `'.$field.'`;');
				DB::alteration_message ("Deleted $field in {$table}_Live", "deleted");
				DB::query('ALTER TABLE `'.$table.'_versions` DROP `'.$field.'`;');
				DB::alteration_message ("Deleted $field in {$table}_versions", "deleted");
			}
			return true;
		}
	}

	protected function swapArray($array) {
		$newArray = array();
		if(is_array($array)) {
			foreach($array as $key => $value) {
				$newArray[] = $key;
			}
		}
		return $newArray;
	}

	protected function hasVersioning($dataObject) {
		$versioningPresent = false;
		$array = $dataObject->stat('extensions');
		if(is_array($array) && count($array)) {
			if(in_array("Versioned('Stage', 'Live')", $array)) {
				$versioningPresent = true;
			}
		}
		if($dataObject->stat('versioning')) {
			$versioningPresent = true;
		}
		return $versioningPresent;
	}

}
