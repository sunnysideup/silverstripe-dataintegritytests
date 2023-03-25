<?php

namespace Sunnysideup\DataIntegrityTest;

use db;

use SilverStripe\CMS\Model\SiteTree;

use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\BuildTask;
use Spyc;

class DataIntegrityTestYML extends BuildTask
{
    /**
     * standard SS variable
     * @var string
     */
    protected $title = 'Check your config files for rogue entries.';

    /**
     * standard SS variable
     * @var string
     */
    protected $description = 'Checks a selection of yml files to see if there are any entries that may be incorrect.';

    /**
     * list of files you want to check
     * @var array
     */
    private static $config_files = ['app/_config/config.yml'];

    /**
     * list of classes that do not need to be checked
     * NB: they are all lowercase, as we test for them only lowercase!
     * @var array
     */
    private static $classes_to_skip = ['name', 'before', 'only', 'after'];

    /**
     * list of variables that do not need checking...
     * @var array
     */
    private static $variables_to_skip = [];

    public function run($request)
    {
        ini_set('max_execution_time', 3000);

        require_once 'thirdparty/spyc/spyc.php';
        $filesArray = Config::inst()->get(DataIntegrityTestYML::class, 'config_files');
        $classesToSkip = Config::inst()->get(DataIntegrityTestYML::class, 'classes_to_skip');
        $variablesToSkip = Config::inst()->get(DataIntegrityTestYML::class, 'variables_to_skip');
        foreach ($filesArray as $folderAndFileLocation) {
            DB::alteration_message("<h2>Checking ${folderAndFileLocation}</h2>");
            $fixtureFolderAndFile = Director::baseFolder() . '/' . $folderAndFileLocation;
            if (!file_exists($fixtureFolderAndFile)) {
                user_error('No custom configuration has been setup here : "' . $fixtureFolderAndFile . '" set the files here: DataIntegrityTestYML::config_files', E_USER_NOTICE);
            }
            $parser = new Spyc();
            $arrayOfSettings = $parser->loadFile($fixtureFolderAndFile);

            foreach ($arrayOfSettings as $className => $variables) {
                if (in_array(strtolower($className), $classesToSkip, true)) {
                    DB::alteration_message("$className : skipped");
                } else {
                    echo '<br /><br />';
                    if (!class_exists($className)) {
                        DB::alteration_message("$className does not exist", 'deleted');
                    } else {
                        DB::alteration_message("$className", 'created');
                        foreach ($variables as $variable => $setting) {
                            if ($variable === 'icon') {
                                $fileLocationForOthers = Director::baseFolder() . '/' . $setting;
                                $fileLocationForSiteTree = Director::baseFolder() . '/' . $setting . '-file.gif';

                                if ($className::create() instanceof SiteTree) {
                                    if (!file_exists($fileLocationForSiteTree)) {
                                        DB::alteration_message("&nbsp; &nbsp; &nbsp; <u>$className.${variable}</u> icon ${fileLocationForSiteTree} can not be found", 'deleted');
                                    } else {
                                        DB::alteration_message("&nbsp; &nbsp; &nbsp; <u>$className.${variable}</u> icon ${fileLocationForSiteTree} exists", 'created');
                                    }
                                } else {
                                    if (!file_exists($fileLocationForOthers)) {
                                        DB::alteration_message("&nbsp; &nbsp; &nbsp; <u>$className.${variable}</u> icon ${fileLocationForOthers} can not be found", 'deleted');
                                    } else {
                                        DB::alteration_message("&nbsp; &nbsp; &nbsp; <u>$className.${variable}</u> icon ${fileLocationForOthers} exists", 'created');
                                    }
                                }
                            } elseif ($variable === 'extensions') {
                                if (!is_array($setting)) {
                                    DB::alteration_message("&nbsp; &nbsp; &nbsp; <u>$className.${variable}</u> extensions should be set as an array.", 'deleted');
                                } else {
                                    foreach ($setting as $extensionClassName) {
                                        if (!class_exists($extensionClassName)) {
                                            DB::alteration_message("&nbsp; &nbsp; &nbsp; <u>$className.${variable}</u> extension class <u>${extensionClassName}</u> does not exist", 'deleted');
                                        } else {
                                            DB::alteration_message("&nbsp; &nbsp; &nbsp; <u>$className.${variable}</u> extension class <u>${extensionClassName}</u> found", 'created');
                                        }
                                    }
                                }
                            } elseif (in_array($variable, $variablesToSkip, true)) {
                                DB::alteration_message("&nbsp; &nbsp; &nbsp; <u>$className.${variable}</u> skipped");
                            } else {
                                if (!property_exists($className, $variable)) {
                                    DB::alteration_message("&nbsp; &nbsp; &nbsp; <u>$className.${variable}</u> does not exist", 'deleted');
                                } else {
                                    DB::alteration_message("&nbsp; &nbsp; &nbsp; <u>$className.${variable}</u> found", 'created');
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
