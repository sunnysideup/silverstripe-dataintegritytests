<?php

namespace Sunnysideup\DataIntegrityTest;

use db;

use Spyc;

use SilverStripe\Core\Config\Config;
use Sunnysideup\DataIntegrityTest\DataIntegrityTestYML;
use SilverStripe\Control\Director;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\BuildTask;

class DataIntegrityTestYML extends BuildTask
{

    /**
     * list of files you want to check
     * @var array
     */
    private static $config_files = array("app/_config/config.yml");

    /**
     * list of classes that do not need to be checked
     * NB: they are all lowercase, as we test for them only lowercase!
     * @var array
     */
    private static $classes_to_skip = array("name", "before", "only", "after");

    /**
     * list of variables that do not need checking...
     * @var array
     */
    private static $variables_to_skip = [];

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

    public function run($request)
    {
        ini_set('max_execution_time', 3000);

        /**
          * ### @@@@ START REPLACEMENT @@@@ ###
          * WHY: upgrade to SS4
          * OLD: require_once ' (case sensitive)
          * NEW: require_once ' (COMPLEX)
          * EXP: This should probably be replaced by PSR-4 autoloading!
          * ### @@@@ STOP REPLACEMENT @@@@ ###
          */
        require_once 'thirdparty/spyc/spyc.php';
        $filesArray = Config::inst()->get(DataIntegrityTestYML::class, "config_files");
        $classesToSkip = Config::inst()->get(DataIntegrityTestYML::class, "classes_to_skip");
        $variablesToSkip = Config::inst()->get(DataIntegrityTestYML::class, "variables_to_skip");
        foreach ($filesArray as $folderAndFileLocation) {
            db::alteration_message("<h2>Checking $folderAndFileLocation</h2>");
            $fixtureFolderAndFile = Director::baseFolder().'/'. $folderAndFileLocation;
            if (!file_exists($fixtureFolderAndFile)) {
                user_error('No custom configuration has been setup here : "' . $fixtureFolderAndFile . '" set the files here: DataIntegrityTestYML::config_files', E_USER_NOTICE);
            }
            $parser = new Spyc();
            $arrayOfSettings = $parser->loadFile($fixtureFolderAndFile);

            /**
              * ### @@@@ START REPLACEMENT @@@@ ###
              * WHY: upgrade to SS4
              * OLD: $className (case sensitive)
              * NEW: $className (COMPLEX)
              * EXP: Check if the class name can still be used as such
              * ### @@@@ STOP REPLACEMENT @@@@ ###
              */
            foreach ($arrayOfSettings as $className => $variables) {

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
                if (in_array(strtolower($className), $classesToSkip)) {

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
                    db::alteration_message("$className : skipped");
                } else {
                    echo "<br /><br />";

                    /**
                      * ### @@@@ START REPLACEMENT @@@@ ###
                      * WHY: upgrade to SS4
                      * OLD: $className (case sensitive)
                      * NEW: $className (COMPLEX)
                      * EXP: Check if the class name can still be used as such
                      * ### @@@@ STOP REPLACEMENT @@@@ ###
                      */
                    if (!class_exists($className)) {

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
                        db::alteration_message("$className does not exist", "deleted");
                    } else {

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
                        db::alteration_message("$className", "created");
                        foreach ($variables as $variable => $setting) {
                            if ($variable == "icon") {
                                $fileLocationForOthers = Director::baseFolder().'/'.$setting;
                                $fileLocationForSiteTree = Director::baseFolder().'/'.$setting.'-file.gif';

                                /**
                                  * ### @@@@ START REPLACEMENT @@@@ ###
                                  * WHY: upgrade to SS4
                                  * OLD: $className (case sensitive)
                                  * NEW: $className (COMPLEX)
                                  * EXP: Check if the class name can still be used as such
                                  * ### @@@@ STOP REPLACEMENT @@@@ ###
                                  */
                                if ($className::create() instanceof SiteTree) {
                                    if (!file_exists($fileLocationForSiteTree)) {

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
                                        db::alteration_message("&nbsp; &nbsp; &nbsp; <u>$className.$variable</u> icon $fileLocationForSiteTree can not be found", "deleted");
                                    } else {

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
                                        db::alteration_message("&nbsp; &nbsp; &nbsp; <u>$className.$variable</u> icon $fileLocationForSiteTree exists", "created");
                                    }
                                } else {
                                    if (!file_exists($fileLocationForOthers)) {

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
                                        db::alteration_message("&nbsp; &nbsp; &nbsp; <u>$className.$variable</u> icon $fileLocationForOthers can not be found", "deleted");
                                    } else {

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
                                        db::alteration_message("&nbsp; &nbsp; &nbsp; <u>$className.$variable</u> icon $fileLocationForOthers exists", "created");
                                    }
                                }
                            } elseif ($variable == "extensions") {
                                if (!is_array($setting)) {

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
                                    db::alteration_message("&nbsp; &nbsp; &nbsp; <u>$className.$variable</u> extensions should be set as an array.", "deleted");
                                } else {
                                    foreach ($setting as $extensionClassName) {
                                        if (!class_exists($extensionClassName)) {

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
                                            db::alteration_message("&nbsp; &nbsp; &nbsp; <u>$className.$variable</u> extension class <u>$extensionClassName</u> does not exist", "deleted");
                                        } else {

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
                                            db::alteration_message("&nbsp; &nbsp; &nbsp; <u>$className.$variable</u> extension class <u>$extensionClassName</u> found", "created");
                                        }
                                    }
                                }
                            } elseif (in_array($variable, $variablesToSkip)) {

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
                                db::alteration_message("&nbsp; &nbsp; &nbsp; <u>$className.$variable</u> skipped");
                            } else {

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
                                if (!property_exists($className, $variable)) {

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
                                    db::alteration_message("&nbsp; &nbsp; &nbsp; <u>$className.$variable</u> does not exist", "deleted");
                                } else {

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
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
