<?php


class DataIntegrityTestUTF8 extends BuildTask {

	private static $replacement_array = array(
		'Â' => '',
		'Â' => '',
		'Â' => '',
		'â€™' => '&#39;',
		'Ââ€“' => '&mdash;',
		'â€¨' => '',
		'â€œ' => '&quot;',
		'â€^Ý' => '&quot;',
		'<br>' => '<br />',
		'â€¢' => '&#8226',
		'Ý' => '- '
	);

	/**
	 * standard SS variable
	 * @var String
	 */
	protected $title = "Convert tables to utf-8 and replace funny characters.";

	/**
	 * standard SS variable
	 * @var String
	 */
	protected $description = "Converts table to utf-8 by replacing a bunch of characters that show up in the Silverstripe Conversion. CAREFUL: replaces all tables in Database to utf-8!";

	function run($request) {
		ini_set('max_execution_time', 3000);
		$tables = DB::query('SHOW tables');
		$unique = array();
		$arrayOfReplacements = Config::inst()->get("DataIntegrityTestUTF8", "replacement_array");
		foreach ($tables as $table) {
			$table = array_pop($table);
			DB::query("ALTER TABLE \"$table\" CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci");
			DB::alteration_message("<h2>Resetting $table to utf8</h2>");
			$this->flushNow();
			$originatingClass = str_replace("_Live", "", $table);
			if(class_exists($originatingClass) && !class_exists($table)) {
				
			}
			else {
				$originatingClass = $table;
			}
			if(class_exists($originatingClass)) {
				$fields = Config::inst()->get($originatingClass, "db", $uninherited = 1);
				if($fields && count($fields)) {
					$unusedFields = array();
					foreach($fields as $fieldName => $type) {
						$usedFieldsChanged = array("CHECKING $table.$fieldName : ");
						if(substr($type, 0, 4) == "HTML") {
							foreach($arrayOfReplacements as $from => $to) {
								DB::query("UPDATE \"$table\" SET \"$fieldName\" = REPLACE(\"$fieldName\", '$from', '$to');");
								$count = DB::getConn()->affectedRows();
								$toWord = $to;
								if($to == '') {
									$toWord = '[NOTHING]';
								}
								$usedFieldsChanged[] = "$count Replacements <strong>$from</strong> with <strong>$toWord</strong>";
							}
						}
						else {
							$unusedFields[] = $fieldName;
						}
						if(count($usedFieldsChanged )) {
							DB::alteration_message (implode("<br /> &nbsp;&nbsp;&nbsp;&nbsp; - ", $usedFieldsChanged) );
							$this->flushNow();
						}
					}
					if(count($unusedFields)) {
						DB::alteration_message("Skipped the following fields: ".implode(",", $unusedFields));
					}
				}
				else {
					DB::alteration_message("No fields for $originatingClass");
				}
			}
			else {
				DB::alteration_message("Skipping $originatingClass - class can not be found");
			}
		}
		DB::alteration_message("<hr /><hr /><hr /><hr /><hr /><hr /><hr />COMPLETED<hr /><hr /><hr /><hr /><hr /><hr /><hr />");
	}

	private function flushNow(){
		// check that buffer is actually set before flushing
		if (ob_get_length()){
			@ob_flush();
			@flush();
			@ob_end_flush();
		}
		@ob_start();
	}

}
