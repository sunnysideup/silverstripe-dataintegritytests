<?php

namespace Sunnysideup\DataIntegrityTest;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Director;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DatabaseAdmin;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Versioned\Versioned;

class DataIntegrityTest extends BuildTask
{
    /**
     * standard SS variable
     * @var string
     */
    protected $title = 'Check Database Integrity';
    /**
     * standard SS variable
     * @var string
     */
    private static $segment = 'DataIntegrityTest';

    /**
     * standard SS variable
     * @var string
     */
    protected $description = 'Go through all fields in the database and work out what fields are superfluous.';

    private static $warning = 'are you sure - this step is irreversible! - MAKE SURE TO MAKE A BACKUP OF YOUR DATABASE BEFORE YOU CONFIRM THIS!';

    private static $test_array = [
        'In SiteTree_Live but not in SiteTree' =>
        'SELECT SiteTree.ID, SiteTree.Title FROM SiteTree_Live RIGHT JOIN SiteTree ON SiteTree_Live.ID = SiteTree.ID WHERE SiteTree.ID IS NULL;',
        'ParentID does not exist in SiteTree' =>
            'SELECT SiteTree.ID, SiteTree.Title FROM SiteTree RIGHT JOIN SiteTree Parent ON SiteTree.ParentID = Parent.ID Where SiteTree.ID IS NULL and SiteTree.ParentID <> 0;',
        'ParentID does not exists in SiteTree_Live' =>
            'SELECT SiteTree_Live.ID, SiteTree_Live.Title FROM SiteTree_Live RIGHT JOIN SiteTree_Live Parent ON SiteTree_Live.ParentID = Parent.ID Where SiteTree_Live.ID IS NULL and SiteTree_Live.ParentID <> 0;',
    ];

    private static $global_exceptions = [
        'EditableFormField' => 'Version',
        'EditableOption' => 'Version',
        'OrderItem' => 'Version',
    ];

    /**
     *@param array = should be provided as follows: array("Member.UselessField1", "Member.UselessField2", "SiteTree.UselessField3")
     */
    private static $fields_to_delete = [];

    private static $allowed_actions = [
        'obsoletefields' => 'ADMIN',
        'deleteonefield' => 'ADMIN',
        'deletemarkedfields' => 'ADMIN',
        'deleteobsoletetables' => 'ADMIN',
        'deleteallversions' => 'ADMIN',
        'cleanupdb' => 'ADMIN',
    ];

    public function init()
    {
        //this checks security
        parent::init();
    }

    public function run($request)
    {
        Environment::increaseTimeLimitTo(3000);
        if ($action = $request->getVar('do')) {
            $methodArray = explode('/', $action);
            $method = $methodArray[0];
            $allowedActions = Config::inst()->get(DataIntegrityTest::class, 'allowed_actions');
            if (isset($allowedActions[$method])) {
                if ($method === 'obsoletefields') {
                    $deletesafeones = $fixbrokendataobjects = $deleteall = false;
                    if (isset($_GET['deletesafeones']) && $_GET['deletesafeones']) {
                        $deletesafeones = true;
                    }
                    if (isset($_GET['fixbrokendataobjects']) && $_GET['fixbrokendataobjects']) {
                        $fixbrokendataobjects = true;
                    }
                    if (isset($_GET['deleteall']) && $_GET['deleteall']) {
                        $deleteall = true;
                    }
                    return $this->{$method}($deletesafeones, $fixbrokendataobjects, $deleteall);
                }
                return $this->{$method}();
            }
            user_error("could not find method: ${method}");
        }
        $warning = Config::inst()->get(DataIntegrityTest::class, 'warning');
        echo '<h2>Database Administration Helpers</h2>';
        echo '<p><a href="' . $this->Link() . '?do=obsoletefields">Prepare a list of obsolete fields.</a></p>';
        echo '<p><a href="' . $this->Link() . "?do=obsoletefields&amp;deletesafeones=1\" onclick=\"return confirm('" . $warning . "');\">Prepare a list of obsolete fields and DELETE! obsolete fields without any data.</a></p>";
        echo '<p><a href="' . $this->Link() . "?do=obsoletefields&amp;fixbrokendataobjects=1\" onclick=\"return confirm('" . $warning . "');\">Fix broken dataobjects.</a></p>";
        echo '<p><a href="' . $this->Link() . "?do=obsoletefields&amp;deleteall=1\" onclick=\"return confirm('" . $warning . "');\">Delete all obsolete fields now!</a></p>";
        echo '<hr />';
        echo '<p><a href="' . $this->Link() . "?do=deletemarkedfields\" onclick=\"return confirm('" . $warning . "');\">Delete fields listed in _config.</a></p>";
        echo '<hr />';
        echo '<p><a href="' . $this->Link() . "?do=deleteobsoletetables\" onclick=\"return confirm('" . $warning . "');\">Delete all tables that are marked as obsolete</a></p>";
        echo '<hr />';
        echo '<p><a href="' . $this->Link() . "?do=deleteallversions\" onclick=\"return confirm('" . $warning . "');\">Delete all versioned data</a></p>";
        echo '<hr />';
        echo '<p><a href="' . $this->Link() . "?do=cleanupdb\" onclick=\"return confirm('" . $warning . "');\">Clean up Database (remove obsolete records)</a></p>";
        echo '<hr />';
        echo "<p><a href=\"/dev/tasks/DataIntegrityTestInnoDB/\" onclick=\"return confirm('" . $warning . "');\">Set all tables to innoDB</a></p>";
        echo "<p><a href=\"/dev/tasks/DataIntegrityTestUTF8/\" onclick=\"return confirm('" . $warning . "');\">Set all tables to utf-8</a></p>";
    }

    public function deletemarkedfields()
    {
        $fieldsToDelete = Config::inst()->get(DataIntegrityTest::class, 'fields_to_delete');
        if (is_array($fieldsToDelete)) {
            if (count($fieldsToDelete)) {
                // no need for key
                foreach ($fieldsToDelete as $tableDotField) {
                    $tableFieldArray = explode('.', $tableDotField);
                    $this->deleteField($tableFieldArray[0], $tableFieldArray[1]);
                }
            } else {
                DB::alteration_message('there are no fields to delete', 'created');
            }
        } else {
            user_error('you need to select these fields to be deleted first (DataIntegrityTest.fields_to_delete)');
        }
        echo '<a href="' . Director::absoluteURL('/dev/tasks/DataIntegrityTest/') . '">back to main menu.</a>';
    }

    public function deleteonefield()
    {
        $requestExploded = explode('/', $_GET['do']);
        if (! isset($requestExploded[1])) {
            user_error('no table has been specified', E_USER_WARNING);
        }
        if (! isset($requestExploded[2])) {
            user_error('no field has been specified', E_USER_WARNING);
        }
        $table = $requestExploded[1];
        $field = $requestExploded[2];
        if ($this->deleteField($table, $field)) {
            DB::alteration_message("successfully deleted ${field} from ${table} now");
        } else {
            DB::alteration_message("COULD NOT delete ${field} from ${table} now", 'deleted');
        }
        DB::alteration_message('<a href="' . Director::absoluteURL('dev/tasks/DataIntegrityTest/?do=obsoletefields') . '">return to list of obsolete fields</a>', 'created');
        echo '<a href="' . Director::absoluteURL('/dev/tasks/DataIntegrityTest/') . '">back to main menu.</a>';
    }

    protected function Link()
    {
        return '/dev/tasks/DataIntegrityTest/';
    }

    protected function obsoletefields($deleteSafeOnes = false, $fixBrokenDataObject = false, $deleteAll = false)
    {
        set_time_limit(600);
        $dataClasses = ClassInfo::subclassesFor(DataObject::class);
        $notCheckedArray = [];
        $canBeSafelyDeleted = [];
        //remove dataobject
        array_shift($dataClasses);
        $rows = DB::query('SHOW TABLES;');
        $actualTables = [];
        if ($rows) {
            foreach ($rows as $item) {
                foreach ($item as $table) {
                    $actualTables[$table] = $table;
                }
            }
        }
        echo '<h1>Report of fields that may not be required.</h1>';
        echo '<p>NOTE: it may contain fields that are actually required (e.g. versioning or many-many relationships) and it may also leave out some obsolete fields.  Use as a guide only.</p>';
        foreach ($dataClasses as $dataClass) {
            // Check if class exists before trying to instantiate - this sidesteps any manifest weirdness
            if (class_exists($dataClass)) {
                $dataObject = $dataClass::create();
                if ($dataObject instanceof TestOnly) {
                    continue;
                }

                $schema = $dataObject->getSchema();
                $tableName = $schema->tableName($dataClass);

                $requiredFields = array_keys($schema->databaseFields($dataObject->ClassName, false));
                if (!count($requiredFields)) {
                    continue;
                }

                $existingFields = array_keys(DB::field_list($tableName));
                echo 'Checking <b>'.$tableName.'</b> ...';

                $diff = array_diff($existingFields, $requiredFields);
                foreach ($diff as $field) {
                    DB::alteration_message(
                        "<span style='color:red'>**** $tableName.$field EXIST BUT IT SHOULD BE THERE!</span>",
                        'deleted'
                    );
                }

                $diff2 = array_diff($requiredFields, $existingFields);
                foreach ($diff2 as $field) {
                    if (in_array($field, $requiredFields)) {
                        DB::alteration_message(
                            "<span style='color:red'>**** $tableName.$field DOES NOT EXIST BUT IT SHOULD BE THERE!</span>",
                            'deleted'
                        );
                    }
                }

                if (!count($diff) && !count($diff2)) {
                    echo "<span style='color:green'>OK</span><br/>";
                }
                continue;

                // not implemented yet
                $requiredFields = $this->swapArray(DataObject::getSchema()->databaseFields($dataObject->ClassName));
                if (count($requiredFields)) {
                    foreach ($requiredFields as $field) {
                        if (! $dataObject->hasOwnTableDatabaseField($field)) {
                            DB::alteration_message("  **** ${dataClass}.${field} DOES NOT EXIST BUT IT SHOULD BE THERE!", 'deleted');
                        }
                    }
                    $actualFields = $this->swapArray(DB::fieldList($dataClass));
                    if ($actualFields) {
                        foreach ($actualFields as $actualField) {
                            if ($deleteAll) {
                                $link = ' !!!!!!!!!!! DELETED !!!!!!!!!';
                            } else {
                                $warning = Config::inst()->get(DataIntegrityTest::class, 'warning');
                                $link = '<a href="' . Director::absoluteBaseURL() . 'dev/tasks/DataIntegrityTest/?do=deleteonefield/' . $dataClass . '/' . $actualField . "/\" onclick=\"return confirm('" . $warning . "');\">delete field</a>";
                            }
                            if (! in_array($actualField, ['ID', 'Version'], true)) {
                                if (! in_array($actualField, $requiredFields, true)) {
                                    $distinctCount = DB::query("SELECT COUNT(DISTINCT \"${actualField}\") FROM \"${dataClass}\" WHERE \"${actualField}\" IS NOT NULL AND \"${actualField}\" <> '' AND \"${actualField}\" <> '0';")->value();
                                    DB::alteration_message("<br /><br />\n\n${dataClass}.${actualField} ${link} - unique entries: ${distinctCount}", 'deleted');
                                    if ($distinctCount) {
                                        $rows = DB::query("
                                            SELECT \"${actualField}\" as N, COUNT(\"${actualField}\") as C
                                            FROM \"${dataClass}\"
                                            GROUP BY \"${actualField}\"
                                            ORDER BY C DESC
                                            LIMIT 7");
                                        if ($rows) {
                                            foreach ($rows as $row) {
                                                DB::alteration_message(' &nbsp; &nbsp; &nbsp; ' . $row['C'] . ': ' . $row['N']);
                                            }
                                        }
                                    } else {
                                        if (! isset($canBeSafelyDeleted[$dataClass])) {
                                            $canBeSafelyDeleted[$dataClass] = [];
                                        }
                                        $canBeSafelyDeleted[$dataClass][$actualField] = "${dataClass}.${actualField}";
                                    }
                                    if ($deleteAll || ($deleteSafeOnes && $distinctCount === 0)) {
                                        $this->deleteField($dataClass, $actualField);
                                    }
                                }
                            }
                            if ($actualField === 'Version' && ! in_array($actualField, $requiredFields, true)) {
                                $versioningPresent = $dataObject->hasVersioning();
                                if (! $versioningPresent) {
                                    DB::alteration_message("${dataClass}.${actualField} ${link}", 'deleted');
                                    if ($deleteAll) {
                                        $this->deleteField($dataClass, $actualField);
                                    }
                                }
                            }
                        }
                    }
                    $rawCount = DB::query("SELECT COUNT(\"ID\") FROM \"${dataClass}\"")->value();
                    Versioned::set_reading_mode('Stage.Stage');
                    $realCount = 0;
                    $allSubClasses = array_unique([$dataClass] + ClassInfo::subclassesFor($dataClass));
                    $objects = $dataClass::get()->filter(['ClassName' => $allSubClasses]);
                    if ($objects->count()) {
                        $realCount = $objects->count();
                    }
                    if ($rawCount !== $realCount) {
                        echo '<hr />';
                        $sign = ' > ';
                        if ($rawCount < $realCount) {
                            $sign = ' < ';
                        }
                        DB::alteration_message("The DB Table Row Count does not seem to match the DataObject Count for <strong>${dataClass} (${rawCount} ${sign} ${realCount})</strong>.  This could indicate an error as generally these numbers should match.", 'deleted');
                        if ($fixBrokenDataObject) {
                            $objects = $dataClass::get()->where('LinkedTable.ID IS NULL')->leftJoin($dataClass, "${dataClass}.ID = LinkedTable.ID", 'LinkedTable');
                            if ($objects->count() > 500) {
                                DB::alteration_message("It is recommended that you manually fix the difference in real vs object count in ${dataClass}. There are more than 500 records so it would take too long to do it now.", 'deleted');
                            } else {
                                DB::alteration_message('Now trying to recreate missing items... COUNT = ' . $objects->count(), 'created');
                                foreach ($objects as $object) {
                                    if (DB::query("SELECT COUNT(\"ID\") FROM \"${dataClass}\" WHERE \"ID\" = " . $object->ID . ';')->value() !== 1) {
                                        Config::modify()->update(DataObject::class, 'validation_enabled', false);
                                        $object->write(true, false, true, false);
                                        Config::modify()->update(DataObject::class, 'validation_enabled', true);
                                    }
                                }
                                $objectCount = $dataClass::get()->count();
                                DB::alteration_message("Consider deleting superfluous records from table ${dataClass} .... COUNT =" . ($rawCount - $objectCount));
                                $ancestors = ClassInfo::ancestry($dataClass, true);
                                if ($ancestors && is_array($ancestors) && count($ancestors)) {
                                    foreach ($ancestors as $ancestor) {
                                        if ($ancestor !== $dataClass) {
                                            echo "DELETE `${dataClass}`.* FROM `${dataClass}` LEFT JOIN `${ancestor}` ON `${dataClass}`.`ID` = `${ancestor}`.`ID` WHERE `${ancestor}`.`ID` IS NULL;";
                                            DB::query("DELETE `${dataClass}`.* FROM `${dataClass}` LEFT JOIN `${ancestor}` ON `${dataClass}`.`ID` = `${ancestor}`.`ID` WHERE `${ancestor}`.`ID` IS NULL;");
                                        }
                                    }
                                }
                            }
                        }
                        echo '<hr />';
                    }
                    unset($actualTables[$dataClass]);
                } else {
                    $db = DB::get_conn();
                    if ($db->hasTable($dataClass)) {
                        DB::alteration_message("  **** The ${dataClass} table exists, but according to the data-scheme it should not be there ", 'deleted');
                    } else {
                        $notCheckedArray[] = $dataClass;
                    }
                }
            }
        }
        die('Not implemented yet ...');

        if (count($canBeSafelyDeleted)) {
            DB::alteration_message('<h2>Can be safely deleted: </h2>');
            foreach ($canBeSafelyDeleted as $table => $fields) {
                DB::alteration_message($table . ': ' . implode(', ', $fields));
            }
        }

        if (count($notCheckedArray)) {
            echo '<h3>Did not check the following classes as no fields appear to be required and hence there is no database table.</h3>';
            foreach ($notCheckedArray as $table) {
                if (DB::query("SHOW TABLES LIKE '" . $table . "'")->value()) {
                    DB::alteration_message($table . ' - NOTE: a table exists for this Class, this is an unexpected result', 'deleted');
                } else {
                    DB::alteration_message($table, 'created');
                }
            }
        }

        if (count($actualTables)) {
            echo '<h3>Other Tables in Database not directly linked to a Silverstripe DataObject:</h3>';
            foreach ($actualTables as $table) {
                $remove = true;
                if (class_exists($table)) {
                    $classExistsMessage = ' a PHP class with this name exists.';
                    $obj = singleton($table);
                    //not sure why we have this.
                    if ($obj instanceof DataExtension) {
                        $remove = false;
                    } elseif (class_exists(Versioned::class) && $obj->hasExtension(Versioned::class)) {
                        $remove = false;
                    }
                } else {
                    $classExistsMessage = ' NO PHP class with this name exists.';
                    if (substr($table, -5) === '_Live') {
                        $remove = false;
                    }
                    if (substr($table, -9) === '_versions') {
                        $remove = false;
                    }
                    //many 2 many tables...
                    if (strpos($table, '_')) {
                        // $class = explode('_', $table);
                        $manyManyClass = substr($table, 0, strrpos($table, '_'));
                        $manyManyExtension = substr($table, strrpos($table, '_') + 1 - strlen($table));
                        if (class_exists($manyManyClass)) {
                            $manyManys = Config::inst()->get($manyManyClass, 'many_many');
                            if (isset($manyManys[$manyManyExtension])) {
                                $remove = false;
                            }
                        }
                    }
                }
                if ($remove) {
                    if (substr($table, 0, strlen('_obsolete_')) !== '_obsolete_') {
                        $rowCount = DB::query("SELECT COUNT(*) FROM ${table}")->value();
                        DB::alteration_message($table . ', rows ' . $rowCount);
                        $obsoleteTableName = '_obsolete_' . $table;
                        if (! $this->tableExists($obsoleteTableName)) {
                            DB::alteration_message("We recommend deleting ${table} or making it obsolete by renaming it to " . $obsoleteTableName, 'deleted');
                            if ($deleteAll) {
                                DB::get_conn()->renameTable($table, $obsoleteTableName);
                            } else {
                                DB::alteration_message($table . ' - ' . $classExistsMessage . ' It can be moved to _obsolete_' . $table . '.', 'created');
                            }
                        } else {
                            DB::alteration_message("I'd recommend to move <strong>${table}</strong> to <strong>" . $obsoleteTableName . '</strong>, but that table already exists', 'deleted');
                        }
                    }
                }
            }
        }

        echo '<a href="' . Director::absoluteURL('/dev/tasks/DataIntegrityTest/') . '">back to main menu.</a>';
    }

    protected function hasVersioning($dataObject)
    {
        $versioningPresent = false;
        $array = $dataObject->stat('extensions');
        if (is_array($array) && count($array)) {
            if (in_array("Versioned('Stage', 'Live')", $array, true)) {
                $versioningPresent = true;
            }
        }
        if ($dataObject->stat('versioning')) {
            $versioningPresent = true;
        }
        return $versioningPresent;
    }

    private function cleanupdb()
    {
        $obj = new DatabaseAdmin();
        $obj->cleanup();
        DB::alteration_message('============= COMPLETED =================', '');
        echo '<a href="' . Director::absoluteURL('/dev/tasks/DataIntegrityTest/') . '">back to main menu.</a>';
    }

    private function deleteField($table, $field)
    {
        $fields = $this->swapArray(DB::fieldList($table));
        $globalExeceptions = Config::inst()->get(DataIntegrityTest::class, 'global_exceptions');
        if (count($globalExeceptions)) {
            foreach ($globalExeceptions as $exceptionTable => $exceptionField) {
                if ($exceptionTable === $table && $exceptionField === $field) {
                    DB::alteration_message("tried to delete ${table}.${field} but this is listed as a global exception and can not be deleted", 'created');
                    return false;
                }
            }
        }
        if (! DB::query("SHOW TABLES LIKE '" . $table . "'")->value()) {
            DB::alteration_message("tried to delete ${table}.${field} but TABLE does not exist", 'deleted');
            return false;
        }
        if (! class_exists($table)) {
            DB::alteration_message("tried to delete ${table}.${field} but CLASS does not exist", 'deleted');
            return false;
        }
        if (! in_array($field, $fields, true)) {
            DB::alteration_message("tried to delete ${table}.${field} but FIELD does not exist", 'deleted');
            return false;
        }
        DB::alteration_message("Deleting ${field} in ${table}", 'deleted');
        DB::query('ALTER TABLE "' . $table . '" DROP "' . $field . '";');
        $obj = singleton($table);
        //to do: make this more reliable - checking for versioning rather than SiteTree
        if ($obj instanceof SiteTree) {
            DB::query('ALTER TABLE "' . $table . '_Live" DROP "' . $field . '";');
            DB::alteration_message("Deleted ${field} in {$table}_Live", 'deleted');
            DB::query('ALTER TABLE "' . $table . '_versions" DROP "' . $field . '";');
            DB::alteration_message("Deleted ${field} in {$table}_versions", 'deleted');
        }
        return true;
    }

    private function swapArray($array)
    {
        $newArray = [];
        if (is_array($array)) {
            foreach (array_keys($array) as $key) {
                $newArray[] = $key;
            }
        }
        return $newArray;
    }

    private function deleteobsoletetables()
    {
        $tables = DB::query('SHOW tables');
        foreach ($tables as $table) {
            $table = array_pop($table);
            if (substr($table, 0, 10) === '_obsolete_') {
                DB::alteration_message("Removing table ${table}", 'deleted');
                DB::query("DROP TABLE \"${table}\" ");
            }
        }
        echo '<a href="' . Director::absoluteURL('/dev/tasks/DataIntegrityTest/') . '">back to main menu.</a>';
    }

    private function deleteallversions()
    {
        $tables = DB::query('SHOW tables');
        foreach ($tables as $table) {
            $table = array_pop($table);
            $endOfTable = substr($table, -9);
            if ($endOfTable === '_versions') {
                /**
                 * ### @@@@ START REPLACEMENT @@@@ ###
                 * WHY: upgrade to SS4
                 * OLD: $className (case sensitive)
                 * NEW: $className (COMPLEX)
                 * EXP: Check if the class name can still be used as such
                 * ### @@@@ STOP REPLACEMENT @@@@ ###
                 */
                $className = substr($table, 0, strlen($table) - 9);

                /**
                 * ### @@@@ START REPLACEMENT @@@@ ###
                 * WHY: upgrade to SS4
                 * OLD: $className (case sensitive)
                 * NEW: $className (COMPLEX)
                 * EXP: Check if the class name can still be used as such
                 * ### @@@@ STOP REPLACEMENT @@@@ ###
                 */
                if (class_exists($className)) {
                    /**
                     * ### @@@@ START REPLACEMENT @@@@ ###
                     * WHY: upgrade to SS4
                     * OLD: $className (case sensitive)
                     * NEW: $className (COMPLEX)
                     * EXP: Check if the class name can still be used as such
                     * ### @@@@ STOP REPLACEMENT @@@@ ###
                     */
                    $obj = DataObject::get_one($className);
                    if ($obj) {
                        if ($obj->hasExtension(Versioned::class)) {
                            DB::alteration_message("Removing all records from ${table}", 'created');
                            DB::query("DELETE FROM \"${table}\" ");
                        }
                    }
                } else {
                    /**
                     * ### @@@@ START REPLACEMENT @@@@ ###
                     * WHY: upgrade to SS4
                     * OLD: $className (case sensitive)
                     * NEW: $className (COMPLEX)
                     * EXP: Check if the class name can still be used as such
                     * ### @@@@ STOP REPLACEMENT @@@@ ###
                     */
                    DB::alteration_message("Could not find ${className} class... the ${table} may be obsolete", 'deleted');
                }
            }
        }
        echo '<a href="' . Director::absoluteURL('/dev/tasks/DataIntegrityTest/') . '">back to main menu.</a>';
    }

    private function tableExists($table)
    {
        $db = DB::get_conn();
        return $db->hasTable($table);
    }
}
