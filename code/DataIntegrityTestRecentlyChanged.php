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
	protected $description = "Go through all tables in the database and see what records have been edited in the last xxx minutes.  You can set the minutes using a GET variable (http://www.sunnysideup.co.nz/dev/tasks/DataIntegrityTestRecentlyChanged/?x=123 where 123 is the number of minutes).";

	/**
	 * runs the task and outputs directly to the screen
	 */
	function run($request) {
		echo "<style>table {width: 100%;} th, td {padding: 5px; font-size: 12px; border: 1px solid #ccc; vertical-align: top;}</style>";

		if($minutes = intval($request->getVar("m"))-0) {
			//do nothing
		}
		else {
			$tsFrom = strtotime($request->getVar("m"));
			if($time) {
				$tsUntil = strtotime("NOW");
				$minutes = round(($tsUntil - $tsFrom) / 60);
			}
		}
		if($minutes) {
			$ts = strtotime($minutes." minutes ago");
			$date =  date(DATE_RFC2822, $ts);
			echo "<hr /><h3>changes in the last ".$this->minutesToTime($minutes)."<br />from: ".$date."<br />make sure you see THE END at the bottom of this list</h3><hr />";
			$whereStatementFixed = "UNIX_TIMESTAMP(\"LastEdited\") > ".$ts." ";
			$dataClasses = ClassInfo::subclassesFor('DataObject');
			array_shift($dataClasses);
			foreach($dataClasses as $dataClass) {
				if(class_exists($dataClass)) {
					$singleton = Injector::inst()->get($dataClass);
					if($singleton instanceof TestOnly) {
						//do nothing
					}
					else {
						$whereStatement = $whereStatementFixed." AND \"ClassName\" = '".$dataClass."' ";
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
										$array[] = substr(strip_tags($object->$field), 0, 1000);
									}
									echo "<tr><td>".implode("</td><td>", $array)."</td></li>";
								}
							}
							echo "</tbody></table>";
						}
					}
				}
			}
			echo "<hr /><h1>-------- THE END --------</h1>";
		}
		if(empty($_GET["m"])) {
			$_GET["m"] = 0;
		}
		echo "
			<form method=\"get\" action=\"".Director::absoluteURL("dev/tasks/".$this->class."/")."\">
				<label for=\"m\">minutes ago</label>
				<input name=\"m\" id=\"m\" value=\"".$_GET["m"]."\">
			</form>";
	}

	protected function minutesToTime($minutes) {
		$seconds = $minutes * 60;
		$dtF = new DateTime("@0");
		$dtT = new DateTime("@$seconds");
		return $dtF->diff($dtT)->format('%a days, %h hours,  and %i minutes');
	}

}
