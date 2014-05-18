<?php


class DataIntegrityTestYML extends BuildTask {

	/**
	 * list of files you want to check
	 * @var array
	 */
	private static $config_files = array("mysite/_config/config.yml");

	/**
	 * standard SS variable
	 * @var String
	 */
	protected $title = "Check your config files for rogue entries.";

	/**
	 * standard SS variable
	 * @var String
	 */
	protected $description = "Checks a selection of yml files to see if there are any entries that may be incorrect.";

	function run($request) {
		ini_set('max_execution_time', 3000);
		require_once 'thirdparty/spyc/spyc.php';
		$filesArray = Config::inst()->get("DataIntegrityTestYML", "config_files");
		foreach($filesArray as $folderAndFileLocation){
			$fixtureFolderAndFile = Director::baseFolder().'/'. $folderAndFileLocation;
			if(!file_exists($fixtureFolderAndFile)) {
				user_error('No custom configuration has been setup for Ecommerce - I was looking for: "' . $fixtureFolderAndFile . '"', E_USER_NOTICE);
			}
			$parser = new Spyc();
			$arrayOfSettings = $parser->loadFile($fixtureFolderAndFile);
			foreach($arrayOfSettings as $className => $variables) {
				echo "<br /><br />";
				if(!class_exists($className)) {
					db::alteration_message("$className does not exist", "deleted");
				}
				else {
					db::alteration_message("$className", "created");
					foreach($variables as $variable => $setting) {
						if(!property_exists($className, $variable)) {
							db::alteration_message("&nbsp;&nbsp;&nbsp;STATIC VARIABLE <u>$className.$variable</u> does not exist", "deleted");
						}
						else {
							db::alteration_message("&nbsp;&nbsp;&nbsp;STATIC VARIABLE <u>$className.$variable</u> found", "created");
						}
					}
				}
			}

		}
	}

}
