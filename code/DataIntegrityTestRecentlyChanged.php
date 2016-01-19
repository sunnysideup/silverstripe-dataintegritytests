<?php


class DataIntegrityTestRecentlyChanged extends BuildTask {


	/**
	 * standard SS variable
	 * @var String
	 */
	protected $title = "Check what records have been changed in the last xxx minutes";

	/**
	 * standard SS variable
	 * @var String
	 */
	protected $description = "Go through all tables in the database and see what records have been edited in the last xxx minutes.  You can set the minutes using a GET variable (http://www.mysite.co.nz/dev/tasks/DataIntegrityTestRecentlyChanged/?x=123 where 123 is the number of minutes).";


	function run($request) {
		ini_set('max_execution_time', 3000);
		echo "<style>table {width: 100%;} th, td {padding: 5px; font-size: 12px; border: 1px solid #ccc; vertical-align: top;}</style>";
		if($minutes = intval($request->getVar("m"))-0) {
			$whereStatementFixed = "UNIX_TIMESTAMP(\"LastEdited\") > ".strtotime($minutes." minutes ago")." ";
			$dataClasses = ClassInfo::subclassesFor('DataObject');
			array_shift($dataClasses);
			foreach($dataClasses as $dataClass) {
				// Check if class exists before trying to instantiate - this sidesteps any manifest weirdness
				if(class_exists($dataClass) && $this->tableExists($dataClass)) {
					$whereStatement = $whereStatementFixed." AND \"ClassName\" = '".$dataClass."' ";
					$singleton = Injector::inst()->get($dataClass);
					$count = $dataClass::get()->where($whereStatement)->count();
					$fields = Config::inst()->get($dataClass, "db", Config::INHERITED);
					if(!is_array($fields)) {
						$fields = array();
					}
					$fields = array("ID" => "Int", "Created" => "SS_DateAndTime", "LastEdited" => "SS_DateAndTime")+$fields;
					if($count) {
						echo "<h2>".$singleton->singular_name()."(".$count.")</h2><table><thead>";
						echo "<tr><th>".implode("</th><th>",array_keys($fields))."</th></tr></thead><tbody>";
						$objects = $dataClass::get()->where($whereStatement)->limit(1000);
						foreach($objects as $object) {
							if(count($fields)) {
								$array = array();
								foreach($fields as $field => $typeOfField) {
									$array[] = substr(strip_tags($object->$field), 0, 100);
								}
								echo "<tr><td>".implode("</td><td>", $array)."</td></li>";
							}
						}
						echo "</tbody></table>";
					}
				}
			}
		}
		echo "<h1>-------- END --------</h1>";
	}


	private function tableExists($table){
		$db = DB::getConn();
		return $db->hasTable($table);
	}

}
