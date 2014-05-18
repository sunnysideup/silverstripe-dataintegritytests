<?php


class DataIntegrityTestYML extends BuildTask {

	/**
	 * list of files you want to check
	 * @var array
	 */
	private static $config_files = array("mysite/_config/config.yml");

	/**
	 * list of classes that do not need to be checked
	 * @var array
	 */
	private static $classes_to_skip = array("Name", "Before", "Only", "After");

	/**
	 * list of variables that do not need checking...
	 * @var array
	 */
	private static $variables_to_skip = array("extensions", "icon");

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
		$classesToSkip = Config::inst()->get("DataIntegrityTestYML", "classes_to_skip");
		$variablesToSkip = Config::inst()->get("DataIntegrityTestYML", "variables_to_skip");
		foreach($filesArray as $folderAndFileLocation){
			$fixtureFolderAndFile = Director::baseFolder().'/'. $folderAndFileLocation;
			if(!file_exists($fixtureFolderAndFile)) {
				user_error('No custom configuration has been setup for Ecommerce - I was looking for: "' . $fixtureFolderAndFile . '"', E_USER_NOTICE);
			}
			$parser = new Spyc();
			$arrayOfSettings = $parser->loadFile($fixtureFolderAndFile);
			foreach($arrayOfSettings as $className => $variables) {
				if(in_array($className, $classesToSkip )) {
					db::alteration_message("$className : skipped");
				}
				else {
					echo "<br /><br />";
					if(!class_exists($className)) {
						db::alteration_message("$className does not exist", "deleted");
					}
					else {
						db::alteration_message("$className", "created");
						foreach($variables as $variable => $setting) {
							if(in_arrary($variable, $variablesToSkip)) {
								db::alteration_message("&nbsp; &nbsp; &nbsp; <u>$className.$variable</u> skipped");
							}
							else {
								if(!property_exists($className, $variable)) {
									db::alteration_message("&nbsp; &nbsp; &nbsp; <u>$className.$variable</u> does not exist", "deleted");
								}
								else {
									db::alteration_message("&nbsp; &nbsp; &nbsp; <u>$className.$variable</u> found", "created");
								}
							}
						}
					}
				}
			}

		}
	}

}
