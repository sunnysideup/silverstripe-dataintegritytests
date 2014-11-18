<?php


class DataIntegrityTestInnoDB extends BuildTask {

	/**
	 * standard SS variable
	 * @var String
	 */
	protected $title = "Convert all tables to InnoDB.";

	/**
	 * standard SS variable
	 * @var String
	 */
	protected $description = "Converts table to innoDB. CAREFUL: replaces all tables in Database to innoDB - not just the Silverstripe ones.";

	function run($request) {
		ini_set('max_execution_time', 3000);
		$tables = DB::query('SHOW TABLE STATUS WHERE ENGINE <>  \'InnoDB\'');
		foreach ($tables as $table) {
			$table = $table["Name"];
			DB::alteration_message("Updating $table to innoDB", "created");$this->flushNow();
			$indexRows = DB::query("SHOW INDEX FROM \"$table\" WHERE Index_type = 'FULLTEXT'");
			unset($done);$done = array();
			foreach($indexRows as $indexRow) {
				$key = $indexRow["Key_name"];
				if(!isset($done[$key])) {
					DB::alteration_message("Deleting INDEX $key in $table (FullText Index)", "deleted");$this->flushNow();
					DB::query("ALTER TABLE \"$table\" DROP INDEX $key;");
					$done[$key] = $key;
				}
			}
			$sql = "ALTER TABLE \"$table\" ENGINE=INNODB";
			DB::query($sql);
		}
		//$rows = DB::query("SHOW GLOBAL STATUS LIKE  'Innodb_page_size'");
		$currentInnoDBSetting = DB::query("SELECT @@innodb_buffer_pool_size as V;")->Value();
		$innoDBBufferUsed = DB::query("

SELECT (PagesData*PageSize)/POWER(1024,3) DataGB FROM
(SELECT variable_value PagesData
FROM information_schema.global_status
WHERE variable_name='Innodb_buffer_pool_pages_data') A,
(SELECT variable_value PageSize
FROM information_schema.global_status
WHERE variable_name='Innodb_page_size') B;

		")->value();
		$innoBDBufferRecommended = DB::query( "
SELECT CEILING(Total_InnoDB_Bytes*1.6/POWER(1024,3)) RIBPS FROM
 (SELECT SUM(data_length+index_length) Total_InnoDB_Bytes
 FROM information_schema.tables WHERE engine='InnoDB') A;
"
		)->value();
		DB::alteration_message("<hr /><hr /><hr /><hr /><hr /><hr /><hr />COMPLETED
		<br />
		Please check your MYSQL innodb_buffer_pool_size setting.
		It is currently using ".round($innoDBBufferUsed, 3)."G,
		but it should be set to ".round($innoBDBufferRecommended, 3)."G.
		The current setting is: ".round($currentInnoDBSetting / (1042 * 1024* 1024))."G
		<hr /><hr /><hr /><hr /><hr /><hr /><hr />");
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
