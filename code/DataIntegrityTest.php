<?php


class DataIntegrityTest extends BuildTask {


	/**
	 * standard SS variable
	 * @var String
	 */
	protected $title = "Check Database Integrity";

	/**
	 * standard SS variable
	 * @var String
	 */
	protected $description = "Go through all fields in the database and work out what fields are superfluous.";

	private static $warning = "are you sure - this step is irreversible! - MAKE SURE TO MAKE A BACKUP OF YOUR DATABASE BEFORE YOU CONFIRM THIS!";

	private static $test_array = array(
		"In SiteTree_Live but not in SiteTree" =>
    	"SELECT SiteTree.ID, SiteTree.Title FROM SiteTree_Live RIGHT JOIN SiteTree ON SiteTree_Live.ID = SiteTree.ID WHERE SiteTree.ID IS NULL;",
		"ParentID does not exist in SiteTree" =>
			"SELECT SiteTree.ID, SiteTree.Title FROM SiteTree RIGHT JOIN SiteTree Parent ON SiteTree.ParentID = Parent.ID Where SiteTree.ID IS NULL and SiteTree.ParentID <> 0;",
		"ParentID does not exists in SiteTree_Live" =>
			"SELECT SiteTree_Live.ID, SiteTree_Live.Title FROM SiteTree_Live RIGHT JOIN SiteTree_Live Parent ON SiteTree_Live.ParentID = Parent.ID Where SiteTree_Live.ID IS NULL and SiteTree_Live.ParentID <> 0;",
	);

	private static $global_exceptions = array(
		"EditableFormField" => "Version",
		"EditableOption" => "Version",
		"OrderItem" => "Version"
	);

	/**
	*@param array = should be provided as follows: array("Member.UselessField1", "Member.UselessField2", "SiteTree.UselessField3")
	*/
	private static $fields_to_delete = array();

	private static $allowed_actions = array(
		"obsoletefields" => "ADMIN",
		"deleteonefield" => "ADMIN",
		"deletemarkedfields" => "ADMIN",
		"deleteobsoletefields" => "ADMIN",
		"resetutf8" => "ADMIN",
		"deleteobsoletetables" => "ADMIN",
		"deleteallversions" => "ADMIN"
	);


	function init() {
		//this checks security
		parent::init();
	}

	function run($request) {
		ini_set('max_execution_time', 3000);
		if($action = $request->getVar("do")) {
			$methodArray = explode("/", $action);
			$method = $methodArray[0];
			$allowedActions = Config::inst()->get("DataIntegrityTest", "allowed_actions");
			if(isset($allowedActions[$method])) {
				return $this->$method();
			}
			else {
				user_error("could not find method: $method");
			}
		}
		$warning = Config::inst()->get("DataIntegrityTest", "warning");
		echo "<h2>Database Administration Helpers</h2>";
		echo "<p><a href=\"".$this->Link()."?do=obsoletefields\">Prepare a list of obsolete fields.</a></p>";
		echo "<p><a href=\"".$this->Link()."?do=deletemarkedfields\" onclick=\"return confirm('".$warning."');\">Delete fields listed in _config.</a></p>";
		echo "<p><a href=\"".$this->Link()."?do=deleteobsoletefields\" onclick=\"return confirm('".$warning."');\">Delete obsolete fields now!</a></p>";
		echo "<p><a href=\"".$this->Link()."?do=resetutf8\" onclick=\"return confirm('".$warning."');\">fix funny characters (due to utf-8 conversion) in ALL TABLES IN DATABASE utf-8!</a></p>";
		echo "<p><a href=\"".$this->Link()."?do=deleteobsoletetables\" onclick=\"return confirm('".$warning."');\">delete all tables that are marked as obsolete</a></p>";
		echo "<p><a href=\"".$this->Link()."?do=deleteallversions\" onclick=\"return confirm('".$warning."');\">delete all versioned data</a></p>";
	}

	protected function Link(){
		return "/dev/tasks/DataIntegrityTest/";
	}

	protected function deleteobsoletefields(){
		return $this->obsoletefields(true);
	}

	protected function obsoletefields($deleteNow = false) {
		increase_time_limit_to(600);
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
				$dataObject = $dataClass::create();
				if(!($dataObject instanceof TestOnly)) {
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
									$warning = Config::inst()->get("DataIntegrityTest", "warning");
									$link = "<a href=\"".Director::absoluteBaseURL()."dev/tasks/DataIntegrityTest/?do=deleteonefield/".$dataClass."/".$actualField."/\" onclick=\"return confirm('".$warning."');\">delete field</a><br /><br />";
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
						if($rawCount < 10000){
							Versioned::set_reading_mode("Stage.Stage");
							$realCount = 0;
							$allSubClasses = array_unique(array($dataClass)+ClassInfo::subclassesFor($dataClass));
							$objects = $dataClass::get()->filter(array("ClassName" =>  $allSubClasses));
							if($objects->count()) {
								$realCount = $objects->count();
							}
							if($rawCount != $realCount) {
								DB::alteration_message("The DB Table Row Count ($rawCount) does not seem to match the DataObject Count ($realCount) for $dataClass.  This could indicate an error as generally these numbers should match.", "deleted");
								if($deleteNow) {
									if($realCount > 500) {
										DB::alteration_message("It is recommended that you manually fix the difference in real vs object count in $dataClass. There are more than 500 records so it would take too long to do it now.", "deleted");
									}
									else {
										$objects = $dataClass::get()->where("LinkedTable.ID IS NULL")->leftJoin($dataClass, "$dataClass.ID = LinkedTable.ID", "LinkedTable");
										DB::alteration_message("Now trying to recreate missing items... COUNT = ".$objects->count(), "created");
										foreach($objects as $object) {
											if(DB::query("SELECT COUNT(\"ID\") FROM \"$dataClass\" WHERE \"ID\" = ".$object->ID.";")->value() != 1) {
												$object->write(true, false, true, false);
											}
										}
										$objects = $dataClass::get();
										$idArray = $objects->map("ID", "ID")->toArray();
										DB::alteration_message("Consider deleting superfluous records from table.... COUNT =".($rawCount - count($idArray)));
									}
								}
							}
						}
						else {
							DB::alteration_message("<span style=\"color: orange\">We cant fully check $dataClass because it as more than 10000 records</span>");
						}
						unset($actualTables[$dataClass]);
					}
					else {
						if( mysql_query("SHOW TABLES LIKE '".$dataClass."'")) {
							DB::alteration_message ("  **** The $dataClass table exists, but according to the data-scheme it should not be there ", "deleted");
						}
						else {
							$notCheckedArray[] = $dataClass;
						}
					}
				}
			}
		}

		if(count($notCheckedArray)) {
			echo "<h3>Did not check the following classes as no fields appear to be required and hence there is no database table.</h3>";
			foreach($notCheckedArray as $table) {
				if( DB::query("SHOW TABLES LIKE '".$table."'")->value()) {
					DB::alteration_message ($table ." - NOTE: a table exists for this Class, this is an unexpected result", "deleted");
				}
				else {
					DB::alteration_message ($table, "created");
				}
			}
		}

		if(count($actualTables)) {
			echo "<h3>Other Tables in Database not directly linked to a Silverstripe DataObject:</h3>";
			foreach($actualTables as $table) {
				$remove = true;
				if(class_exists($table)) {
					$classExistsMessage = " a PHP class with this name exists.";
					$obj = singleton($table);
					//not sure why we have this.
					if($obj instanceof DataExtension) {
						$remove = false;
					}
					elseif(class_exists("Versioned") && $obj->hasExtension("Versioned")) {
						$remove = false;
					}
				}
				else {
					$classExistsMessage = " NO PHP class with this name exists.";
					if(substr($table, -5) == "_Live") {
						$remove = false;
					}
					if(substr($table, -9) == "_versions") {
						$remove = false;
					}
					//many 2 many tables...
					if(strpos($table, "_")) {
						$class = explode("_", $table);
						$manyManyClass = substr($table, 0, strrpos($table, '_') );
						$manyManyExtension = substr($table, strrpos($table, '_') + 1 - strlen($table));
						if(class_exists($manyManyClass)) {
							$manyManys = Config::inst()->get($manyManyClass, "many_many");
							if(isset($manyManys[$manyManyExtension])) {
								$remove = false;
							}
						}
					}
				}
				if($remove) {
					if(substr($table, 0, strlen("_obsolete_")) != "_obsolete_") {
						if($deleteNow) {
							DB::alteration_message ($table." making it obsolete by renaming it to _obsolete_".$table, "deleted");
							DB::getConn()->renameTable($table, "_obsolete_".$table);
						}
						else {
							DB::alteration_message ($table." - ".$classExistsMessage." It can be moved to _obsolete_".$table."." , "created");
						}
					}
				}
			}
		}
		echo "<a href=\"".Director::absoluteURL("/dev/tasks/DataIntegrityTest/")."\">back to main menu.</a>";
	}

	public function deletemarkedfields() {
		$fieldsToDelete = Config::inst()->get("DataIntegrityTest", "fields_to_delete");
		if(is_array($fieldsToDelete)) {
			if(count($fieldsToDelete)) {
				foreach($fieldsToDelete as $key => $tableDotField) {
					$tableFieldArray = explode(".", $tableDotField);
					$this->deleteField($tableFieldArray[0], $tableFieldArray[1]);
				}
			}
			else {
				DB::alteration_message("there are no fields to delete", "created");
			}
		}
		else {
			user_error("you need to select these fields to be deleted first (DataIntegrityTest.fields_to_delete)");
		}
		echo "<a href=\"".Director::absoluteURL("/dev/tasks/DataIntegrityTest/")."\">back to main menu.</a>";
	}

	public function deleteonefield() {
		$requestExploded = explode("/", $_GET["do"]);
		if(!isset($requestExploded[1])) {
			user_error("no table has been specified", E_USER_WARNING);
		}
		if(!isset($requestExploded[2])) {
			user_error("no field has been specified", E_USER_WARNING);
		}
		$table = $requestExploded[1];
		$field = $requestExploded[2];
		if($this->deleteField($table, $field)) {
			DB::alteration_message("successfully deleted $field from $table now");
		}
		else {
			DB::alteration_message("COULD NOT delete $field from $table now", "deleted");
		}
		DB::alteration_message("<a href=\"".Director::absoluteURL("dev/tasks/DataIntegrityTest/?do=obsoletefields")."\">return to list of obsolete fields</a>", "created");
		echo "<a href=\"".Director::absoluteURL("/dev/tasks/DataIntegrityTest/")."\">back to main menu.</a>";
	}

	private function deleteField($table, $field) {
		$fields = $this->swapArray(DB::fieldList($table));
		$globalExeceptions = Config::inst()->get("DataIntegrityTest", "global_exceptions");
		if(count($globalExeceptions)) {
			foreach($globalExeceptions as $exceptionTable => $exceptionField) {
				if($exceptionTable == $table && $exceptionField == $field) {
					DB::alteration_message ("tried to delete $table.$field but this is listed as a global exception and can not be deleted", "created");
					return false;
				}
			}
		}
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
			DB::query('ALTER TABLE "'.$table.'" DROP "'.$field.'";');
			DB::alteration_message ("Deleted $field in $table", "deleted");
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

	private function resetutf8(){
		$tables = DB::query('SHOW tables');
		$unique = array();
		foreach ($tables as $table) {
			$table = array_pop($table);
			DB::query("ALTER TABLE \"$table\" CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci");
			DB::alteration_message("Resetting $table to utf8");
			$fields = DB::query("SHOW COLUMNS FROM \"$table\";");

			foreach($fields as $fieldArray) {
				$field = $fieldArray["Field"];
				$type = $fieldArray["Type"];
				if(
					strtolower(substr($type, 0, 7)) == "varchar" ||
					strtolower(substr($type, 0, 10)) == "mediumtext"

				) {

					DB::alteration_message("Removing Â characters from $table.$field");
					DB::query("
						UPDATE \"$table\"
						SET \"$field\" = REPLACE(\"$field\", 'Â', '');
					");

					DB::alteration_message("Changing â€“ to ' in $table.$field");
					DB::query("
						UPDATE \"$table\"
						SET \"$field\" = REPLACE(\"$field\", 'â€™', '\'');
					");

					DB::alteration_message("Changing â€“ to &mdash; in $table.$field");
					DB::query("
						UPDATE \"$table\"
						SET \"$field\" = REPLACE(\"$field\", 'â€“', '&mdash;');
					");

					DB::alteration_message("Changing â€¨ to [NOTHING]; in $table.$field");
					DB::query("
						UPDATE \"$table\"
						SET \"$field\" = REPLACE(\"$field\", 'â€¨', '');
					");

					DB::alteration_message("Changing Â to [NOTHING]; in $table.$field");
					DB::query("
						UPDATE \"$table\"
						SET \"$field\" = REPLACE(\"$field\", 'Â', '');
					");

					DB::alteration_message("Changing â€œ to ' in $table.$field");
					DB::query("
						UPDATE \"$table\"
						SET \"$field\" = REPLACE(\"$field\", 'â€œ', '&quot;');
					");

					DB::alteration_message("Changing â€^Ý to \" in $table.$field");
					DB::query("
						UPDATE \"$table\"
						SET \"$field\" = REPLACE(\"$field\", 'â€^Ý', '&quot;');
					");


				}
			}
		}
	}

	private function deleteobsoletetables(){
		$tables = DB::query('SHOW tables');
		$unique = array();
		foreach ($tables as $table) {
			$table = array_pop($table);
			if(substr($table, 0, 10) == "_obsolete_") {
				DB::alteration_message("Removing table $table", "deleted");
				DB::query("DROP TABLE \"$table\" ");
			}
		}
		echo "<a href=\"".Director::absoluteURL("/dev/tasks/DataIntegrityTest/")."\">back to main menu.</a>";
	}

	private function deleteallversions(){
		$tables = DB::query('SHOW tables');
		$unique = array();
		foreach ($tables as $table) {
			$table = array_pop($table);
			$endOfTable = substr($table, -9);
			if($endOfTable == "_versions") {
				$className = substr($table, 0, strlen($table) - 9);
				if(class_exists($className)) {
					$obj = $className::get()->first();
					if($obj) {
						if($obj->hasExtension("Versioned")) {
							DB::alteration_message("Removing all records from $table", "created");
							DB::query("DELETE FROM \"$table\" ");
						}
					}
				}
				else {
					DB::alteration_message("Could not find $className class... the $table may be obsolete", "deleted");
				}
			}
		}
		echo "<a href=\"".Director::absoluteURL("/dev/tasks/DataIntegrityTest/")."\">back to main menu.</a>";
	}

}
