<?php

namespace Sunnysideup\DataIntegrityTest;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Director;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
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
    private static $segment = 'dataintegritytest';

    protected $debug = false;

    /**
     * standard SS variable
     * @var string
     */
    protected $description = 'Go through all fields in the database and work out what fields are superfluous / obsolete.';

    private static $warning = 'are you sure - this step is irreversible! - MAKE SURE TO MAKE A BACKUP OF YOUR DATABASE BEFORE YOU CONFIRM THIS!';

    protected array $canBeSafelyDeleted = [];
    protected array $notCheckedArray = [];
    protected array $actualTables = [];
    private static array $tables_to_skip = [];

    private static array $global_exceptions = [];


    /**
     *@param array = should be provided as follows: array("Member.UselessField1", "Member.UselessField2", "SiteTree.UselessField3")
     */
    private static array $fields_to_delete = [];

    private static array $allowed_actions = [
        'obsoletefields' => 'ADMIN',
        'tablereview' => 'ADMIN',
        'deleteonefield' => 'ADMIN',
        'deletemarkedfields' => 'ADMIN',
        'deleteobsoletetables' => 'ADMIN',
        'deleteallversions' => 'ADMIN',
        'cleanupdb' => 'ADMIN',
        'removeorphanedmanymany' => 'ADMIN',
    ];


    public function run($request)
    {
        Environment::increaseTimeLimitTo(3000);
        Environment::increaseMemoryLimitTo('1024M');

        if ($this->debug) {
            $this->printHeader('DEBUG MODE ---- NO DELETIONS ARE MADE', 2, 'deleted');
        } else {
            $this->printHeader('NOT RUNNING DEBUG MODE ---- ACTUAL DELETIONS ARE MADE', 2, 'deleted');
        }
        if ($action = $request->getVar('do')) {
            $methodArray = explode('/', $action);
            $method = $methodArray[0];
            $allowedActions = Config::inst()->get(DataIntegrityTest::class, 'allowed_actions');
            if (isset($allowedActions[$method])) {
                if ($method === 'obsoletefields') {
                    $deletesafeones = (int) ($_GET['deletesafeones'] ?? 0) === 1;
                    $deleteall = (int) ($_GET['deleteall'] ?? 0) === 1;
                    $this->obsoleteFields($deletesafeones, $deleteall);
                } elseif ($method === 'tablereview') {
                    $makeobsolete = (int) ($_GET['makeobsolete'] ?? 0) === 1;
                    $fixbrokendataobjects = (int) ($_GET['fixbrokendataobjects'] ?? 0) === 1;
                    $deletetablealltogether = (int) ($_GET['deletetablealltogether'] ?? 0) === 1;
                    $this->tablereview($makeobsolete, $deletetablealltogether, $fixbrokendataobjects);
                } else {
                    $this->{$method}();
                }
            } else {
                user_error("could not find method: {$method}");
            }
        }
        $this->makeMenu();
    }

    protected function makeMenu()
    {
        $this->printHeader('Database Administration Helpers');
        $this->printLink('?do=obsoletefields', 'Prepare a list of obsolete fields');
        $this->printLink('?do=obsoletefields&deletesafeones=1', 'Prepare a list of obsolete fields and delete obsolete fields without data', true);
        $this->printLink('?do=obsoletefields&deleteall=1', 'Delete all obsolete fields', true);
        $this->printHr();
        $this->printLink('?do=tablereview', 'Prepare a list of obsolete tables.');
        $this->printLink('?do=tablereview&makeobsolete=1', 'Prepare a list of obsolete tables and move them to obsolete!');
        $this->printLink('?do=tablereview&makeobsolete=1&deletetablealltogether=1', 'Delete obsolete tables altogether!', true);
        $this->printLink('?do=tablereview&fixbrokendataobjects=1', 'Fix broken data objects!', true);
        $this->printLink('?do=deleteobsoletetables', 'Delete all tables with _obsolete_ at the start of their name!', true);
        $this->printHr();
        $this->printLink('?do=deletemarkedfields', 'Delete fields listed in DataIntegrityTest::fields_to_delete!', true);
        $this->printHr();
        $this->printLink('?do=deleteallversions', 'Delete all versioned data!', true);
        $this->printHr();
        $this->printLink('?do=cleanupdb', 'Clean up Database (remove orphaned records)!', true);
        $this->printLink('?do=removeorphanedmanymany', 'Remove orphaned many-many!', true);
        $this->printHr();
        $this->printLink('/dev/tasks/checkformysqlpaginationissuesbuildtask', 'Look for pagination issues');
        $this->printLink('/dev/tasks/dataintegritytestinnodb', 'Set all tables to InnoDB!', true);
        $this->printLink('/dev/tasks/dataintegritytestutf8', 'Set all tables to UTF-8!', true);
        $this->printHr();
        $this->printLink('/dev/tasks/cleanoldchangesetstask', 'Review old change sets', false);
        $this->printLink('/dev/tasks/cleanoldchangesetstask?forreal=1&days=90', 'Delete change sets older than 3 months', false);
    }

    protected function printLink(string $action, string $label, bool $confirm = false, $returnString = false): ?string
    {
        $link = $this->Link();
        if ($action) {
            if (strpos($action, '/dev/tasks') === 0) {
                $link = $action;
            } else {
                $link .= $action;
            }
        }
        $confirmAttribute = '';
        if ($confirm) {
            $warning = Config::inst()->get(DataIntegrityTest::class, 'warning');
            $confirmAttribute = ' onclick="return confirm(\'' . $warning . '\');"';
        }
        if (Director::is_cli()) {
            $link = str_replace('?', ' ', $link);
            $link = str_replace('&', ' ', $link);
            $link = ltrim($link, '');
            $link = 'vendor/bin/sake ' . $link;
            $string = PHP_EOL . $label . ':' . PHP_EOL . ' ... ' . $link . PHP_EOL;
        } else {
            $string = '<a href="' . htmlspecialchars($link) . '"' . $confirmAttribute . '>' . $label . '</a>';
        }
        if ($returnString) {
            return $string;
        }
        $this->printString($string);
        return null;
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
                $this->printString('there are no fields to delete', 'created');
            }
        } else {
            user_error('you need to select these fields to be deleted first (DataIntegrityTest.fields_to_delete)');
        }
        $this->printLink('', 'back to main menu');
    }

    public function deleteonefield()
    {
        $requestExploded = explode('/', $_GET['tablefield']);
        $table = $requestExploded[0] ?? '';
        $field = $requestExploded[1] ?? '';
        if (empty($table)) {
            user_error('no table has been specified', E_USER_WARNING);
        }
        if (empty($field)) {
            user_error('no field has been specified', E_USER_WARNING);
        }

        if ($this->deleteField($table, $field)) {
            $this->printString("successfully deleted {$field} from {$table} now");
        } else {
            $this->printString("COULD NOT delete {$field} from {$table} now", 'deleted');
        }
        $this->printString('<a href="' . Director::absoluteURL('dev/tasks/dataintegritytest/?do=obsoletefields') . '">return to list of obsolete fields</a>', 'created');
        $this->printLink('', 'back to main menu.');
    }

    protected function Link()
    {
        return '/dev/tasks/' . $this->Config()->get('segment');
    }

    protected function obsoletefields($deleteSafeOnes = false, $deleteAll = false)
    {
        $dataClasses = ClassInfo::subclassesFor(DataObject::class);
        //remove dataobject
        array_shift($dataClasses);
        $rows = DB::query('SHOW TABLES;');
        $this->actualTables = [];
        if ($rows) {
            foreach ($rows as $item) {
                foreach ($item as $table) {
                    $this->actualTables[$table] = $table;
                }
            }
        }
        $this->printHeader('Report of fields that may not be required.');
        $this->printString('NOTE: it may contain fields that are actually required (e.g. versioning or many-many relationships) and it may also leave out some obsolete fields.  Use as a guide only', 'deleted');
        foreach ($dataClasses as $dataClass) {
            // Check if class exists before trying to instantiate - this sidesteps any manifest weirdness
            if (class_exists($dataClass)) {
                $dataObject = $dataClass::create();
                if ($dataObject instanceof TestOnly) {
                    continue;
                }

                $schema = $dataObject->getSchema();
                $tableName = $schema->tableName($dataClass);
                $this->actualTables[$tableName] = $dataClass;
                $requiredFields = array_keys($schema->databaseFields($dataObject->ClassName, false));
                if (!count($requiredFields)) {
                    continue;
                }

                $existingFields = array_keys(DB::field_list($tableName));

                $diff = array_diff($existingFields, $requiredFields);
                $diff2 = array_diff($requiredFields, $existingFields);

                if (!count($diff) && !count($diff2)) {
                    $this->printString('<b style="color: #000">' . $tableName . '</b> ... OK', 'created');
                } else {
                    $this->printString('<b style="color: #000">' . $tableName . '</b> ...');
                    foreach ($diff as $field) {
                        $this->printString(
                            "**** $tableName.$field EXIST BUT IT SHOULD NOT BE THERE!",
                            'deleted'
                        );
                        $this->printLink(
                            '?do=deleteonefield&tablefield=' . $tableName . '/' . $field,
                            'delete field',
                            true,
                            false
                        );
                        if ($deleteAll) {
                            $this->deleteField($tableName, $field);
                        }
                    }

                    foreach ($diff2 as $field) {
                        if (in_array($field, $requiredFields)) {
                            $this->printString(
                                "**** $tableName.$field DOES NOT EXIST BUT IT SHOULD BE THERE!",
                                'deleted'
                            );
                        }
                    }
                }
            }
            $this->checkFieldsExtra($dataObject, $dataClass, $deleteSafeOnes, $deleteAll);
        }

        if (count($this->canBeSafelyDeleted)) {
            $this->printHeader('Can be safely deleted:', 2);
            foreach ($this->canBeSafelyDeleted as $table => $fields) {
                $this->printString($table . ': ' . implode(', ', $fields));
            }
        }

        if (count($this->notCheckedArray)) {
            $this->printHeader('Did not check the following classes as no fields appear to be required and hence there is no database table.', 3);
            foreach ($this->notCheckedArray as $table) {
                if (DB::query("SHOW TABLES LIKE '" . $table . "'")->value()) {
                    $this->printString($table . ' - NOTE: a table exists for this Class, this is an unexpected result', 'deleted');
                } else {
                    $this->printString($table, 'created');
                }
            }
        }

        $this->printLink('', 'back to main menu.');
    }

    public function tablereview(?bool $makeObsolete = false, ?bool $removeTableAltogether = false, ?bool $fixBrokenDataObjects = false)
    {
        $this->obsoletefields();
        if (count($this->actualTables)) {
            $this->printHeader('Tables in Database not directly linked to a Silverstripe DataObject');
            foreach ($this->actualTables as $tmpTable => $tmpDataClass) {
                if (in_array($tmpTable, Config::inst()->get(DataIntegrityTest::class, 'tables_to_skip'))) {
                    continue;
                }
                $remove = true;
                if (class_exists($tmpDataClass)) {
                    $classExistsMessage = '... a PHP class with this name exists.';
                    $obj = singleton($tmpDataClass);
                    //not sure why we have this.
                    if ($obj instanceof DataObject) {
                        $remove = false;
                    } elseif (class_exists(Versioned::class) && $obj->hasExtension(Versioned::class)) {
                        $remove = false;
                    }
                    $this->fixBrokenDataObject($tmpDataClass, $tmpTable, $fixBrokenDataObjects);
                } elseif ($this->isMarkedAsObsolete($tmpTable)) {
                    $remove = true;
                    $classExistsMessage = '... Table already marked as obsolete.';
                } else {
                    $classExistsMessage = '... NO PHP class with this name exists.';
                    if (substr($tmpTable, -5) === '_Live' && !$this->isMarkedAsObsolete($tmpTable)) {
                        $remove = false;
                    }
                    if (substr($tmpTable, -9) === '_Versions' && !$this->isMarkedAsObsolete($tmpTable)) {
                        $remove = false;
                    }
                    //many 2 many tables...
                    if (strpos($tmpTable, '_')) {
                        // $class = explode('_', $tmpTable);
                        $manyManyClassShort = substr($tmpTable, 0, strrpos($tmpTable, '_'));
                        $manyManyRelName = substr($tmpTable, strrpos($tmpTable, '_') + 1 - strlen($tmpTable));
                        $manyManyClass = '';
                        if (class_exists($manyManyClassShort)) {
                            $manyManyClass = $manyManyClassShort;
                        } else {
                            $manyManyClass = $this->actualTables[$manyManyClassShort] ?? $manyManyClassShort;
                        }
                        if (class_exists($manyManyClass)) {
                            $singleton = Injector::inst()->get($manyManyClass);
                            $manyManys = $singleton->config()->get('many_many');
                            if (isset($manyManys[$manyManyRelName])) {
                                $remove = false;
                            }
                        } else {
                            echo 'ERROR: could not find class "' . $manyManyClass . '"';
                        }
                    }
                }
                if ($remove) {
                    $this->printHeader($tmpTable . ' ' . $tmpDataClass, 2);
                    if (!$this->isMarkedAsObsolete($tmpTable)) {
                        $rowCount = DB::query("SELECT COUNT(*) FROM \"{$tmpTable}\"")->value();
                        $this->printString($tmpTable . ', rows ' . $rowCount);
                        $obsoleteTableName = '_obsolete_' . $tmpTable;
                        if (! $this->tableExists($obsoleteTableName)) {
                            $this->printString("... We recommend deleting {$tmpTable} or making it obsolete by renaming it to " . $obsoleteTableName, 'deleted');
                            if ($makeObsolete) {
                                if ($removeTableAltogether) {
                                    $this->printString("... Deleting {$tmpTable} altogether", 'deleted');
                                    if (! $this->debug) {
                                        DB::query("DROP TABLE \"{$tmpTable}\" ");
                                    }
                                } else {
                                    if (! $this->debug) {
                                        DB::get_schema()->renameTable($tmpTable, $obsoleteTableName);
                                    }
                                }
                            } else {
                                $this->printString($tmpTable . ' - ' . $classExistsMessage . ' It can be moved to _obsolete_' . $tmpTable . '.', 'created');
                            }
                        } else {
                            $this->printString("... We recommend to move <strong>{$tmpTable}</strong> to <strong>" . $obsoleteTableName . '</strong>, but that table already exists', 'deleted');
                        }
                    } elseif ($removeTableAltogether) {
                        $this->printString("... Deleting {$tmpTable} altogether", 'deleted');
                        if (! $this->debug) {
                            DB::query("DROP TABLE \"{$tmpTable}\" ");
                        }
                    } else {
                        $this->printString($tmpTable . ' - ' . $classExistsMessage . ' It can be moved to _obsolete_' . $tmpTable . '.', 'created');
                    }
                } else {
                    $this->printString('<strong>' . $tmpTable . '</strong> based on ' . $tmpDataClass . ' ... OK', 'created');
                }
            }
        }
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
        $this->printString('============= COMPLETED =================', '');
        $this->printLink('', 'back to main menu.');
    }

    private function removeorphanedmanymany()
    {
        $schema = DataObject::getSchema();
        $allClasses = ClassInfo::subclassesFor(DataObject::class);

        foreach ($allClasses as $class) {
            if (!$schema->classHasTable($class)) {
                continue;
            }

            /** @var DataObject $singleton */
            $singleton = singleton($class);
            $manyMany = $singleton->config()->get('many_many') ?? [];
            $belongsManyMany = $singleton->config()->get('belongs_many_many') ?? [];

            $relations = array_merge($manyMany, $belongsManyMany);
            if (empty($relations)) {
                continue;
            }

            foreach ($relations as $relName => $relDef) {
                $isThrough = is_array($relDef);

                if ($isThrough) {
                    $throughClass = $relDef['through'] ?? null;
                    $fromField = $relDef['from'] ?? null;
                    $toField = $relDef['to'] ?? null;

                    if (!$throughClass || !$fromField || !$toField) {
                        DB::alteration_message("⚠️ Skipping $class.$relName — incomplete many_many_through definition", 'deleted');
                        continue;
                    }

                    $joinTable   = $schema->tableName($throughClass);
                    $parentField = "{$fromField}ID";
                    $childField  = "{$toField}ID";

                    // try to infer related class
                    $relClass = singleton($throughClass)->getRelationClass($toField)
                        ?? $relDef['to'] ?? null;
                } else {
                    $relClass = explode('.', $relDef)[0];
                    $component = $schema->manyManyComponent($class, $relName);
                    if (empty($component['join'])) {
                        continue;
                    }
                    $joinTable   = $component['join'];
                    $parentField = $component['parentField'];
                    $childField  = $component['childField'];
                }

                if (!DB::get_schema()->hasTable($joinTable)) {
                    continue;
                }

                $parentTable = $schema->baseDataTable($class);
                $childTable  = $schema->baseDataTable($relClass);

                DB::alteration_message("Checking $joinTable ($class ⇄ $relClass)");

                // --- Delete orphaned parent links ---
                $sql1 = <<<SQL
DELETE FROM "{$joinTable}"
WHERE "{$parentField}" NOT IN (SELECT "ID" FROM "{$parentTable}")
SQL;
                $this->debug ? print("$sql1\n") : DB::query($sql1);
                $removed1 = DB::affected_rows();
                if ($removed1 > 0) {
                    DB::alteration_message("- Removed $removed1 orphaned parent links");
                }

                // --- Delete orphaned child links ---
                $sql2 = <<<SQL
DELETE FROM "{$joinTable}"
WHERE "{$childField}" NOT IN (SELECT "ID" FROM "{$childTable}")
SQL;
                $this->debug ? print("$sql2\n") : DB::query($sql2);
                $removed2 = DB::affected_rows();
                if ($removed2 > 0) {
                    DB::alteration_message("- Removed $removed2 orphaned child links");
                }
            }
        }
    }

    private function deleteField(string $table, string $field)
    {
        $databaseSchema = DB::get_schema();
        $fields = $this->swapArray($databaseSchema->fieldList($table));
        $globalExeceptions = Config::inst()->get(DataIntegrityTest::class, 'global_exceptions');
        if (count($globalExeceptions)) {
            foreach ($globalExeceptions as $exceptionTable => $exceptionField) {
                if ($exceptionTable === $table && $exceptionField === $field) {
                    $this->printString("Listed {$table}.{$field} to be deleted, but this is listed as a global exception and can not be deleted", 'created');
                    return false;
                }
            }
        }
        if (! DB::query("SHOW TABLES LIKE '" . $table . "'")->value()) {
            $this->printString("tried to delete {$table}.{$field} but TABLE does not exist", 'deleted');
            return false;
        }
        if (! in_array($field, $fields, true)) {
            $this->printString("tried to delete {$table}.{$field} but FIELD does not exist", 'deleted');
            return false;
        }
        $this->printString("Deleting {$field} in {$table}", 'deleted');
        foreach (['', '_Live', '_Versions'] as $suffix) {
            $tableNameFinal = $table . $suffix;
            if ($this->tableExists($tableNameFinal)) {
                if ($this->fieldExists($tableNameFinal, $field)) {
                    if (! $this->debug) {
                        DB::query('ALTER TABLE "' . $tableNameFinal . '" DROP "' . $field . '";');
                    }
                }
            }
        }
        return true;
    }

    private function swapArray($array): array
    {
        return is_array($array) ? array_keys($array) : [];
    }

    private function deleteobsoletetables()
    {
        $tables = DB::query('SHOW tables');
        foreach ($tables as $table) {
            $table = array_pop($table);
            if (substr($table, 0, 10) === '_obsolete_') {
                $this->printString("Removing table {$table}", 'deleted');
                if (!$this->debug) {
                    DB::query("DROP TABLE \"{$table}\" ");
                }
            }
        }
        $this->printLink('', 'back to main menu.');
    }

    private function deleteallversions()
    {
        $tables = DB::query('SHOW tables');
        foreach ($tables as $table) {
            $table = array_pop($table);
            $endOfTable = substr($table, -9);
            if ($endOfTable === '_Versions') {
                $this->printString("Removing all records from {$table}", 'created');
                DB::query("TRUNCATE \"{$table}\" ");
            }
        }

        DB::query('TRUNCATE TABLE "ChangeSet";');
        DB::query('TRUNCATE TABLE "ChangeSetItem";');


        $this->printLink('', 'back to main menu.');
    }

    private function tableExists($table)
    {
        $db = DB::get_schema();
        return $db->hasTable($table);
    }

    protected function checkFieldsExtra($dataObject, $dataClass, $deleteSafeOnes = false, $deleteAll = false)
    {

        // not implemented yet
        $databaseSchema = DB::get_schema();
        $requiredFields = $this->swapArray(DataObject::getSchema()->databaseFields($dataObject->ClassName));
        if (count($requiredFields)) {
            foreach ($requiredFields as $field) {
                if (! $dataObject->hasDatabaseField($field)) {
                    $this->printString("  **** {$dataClass}.{$field} DOES NOT EXIST BUT IT SHOULD BE THERE!", 'deleted');
                }
            }
            $schema = $dataObject->getSchema();
            $tableName = $schema->tableName($dataClass);
            $actualFields = $this->swapArray($databaseSchema->fieldList($tableName));
            if ($actualFields) {
                foreach ($actualFields as $actualField) {
                    if ($deleteAll) {
                        $link = ' !!!!!!!!!!! DELETED !!!!!!!!!';
                    } else {
                        $link = $this->printLink(
                            '?do=deleteonefield&tablefield=' . $tableName . '/' . $actualField,
                            'delete field',
                            true,
                            true
                        );
                    }
                    if (! in_array($actualField, ['ID', 'Version'], true)) {
                        if (! in_array($actualField, $requiredFields, true)) {
                            $distinctCount = DB::query("SELECT COUNT(DISTINCT \"{$actualField}\") FROM \"{$tableName}\" WHERE \"{$actualField}\" IS NOT NULL ;")->value();
                            $this->printString("<br /><br />\n\n{$dataClass}.{$actualField} {$link} - unique entries: {$distinctCount}", 'deleted');
                            if ($distinctCount) {
                                $rows = DB::query("
                                            SELECT \"{$actualField}\" as N, COUNT(\"{$actualField}\") as C
                                            FROM \"{$tableName}\"
                                            GROUP BY \"{$actualField}\"
                                            ORDER BY C DESC
                                            LIMIT 7");
                                if ($rows) {
                                    foreach ($rows as $row) {
                                        $this->printString(' &nbsp; &nbsp; &nbsp; ' . $row['C'] . ': ' . $row['N']);
                                    }
                                }
                            } else {
                                if (! isset($this->canBeSafelyDeleted[$dataClass])) {
                                    $this->canBeSafelyDeleted[$dataClass] = [];
                                }
                                $this->canBeSafelyDeleted[$dataClass][$actualField] = "{$dataClass}.{$actualField}";
                            }
                            if ($deleteAll || ($deleteSafeOnes && $distinctCount === 0)) {
                                $this->deleteField($tableName, $actualField);
                            }
                        }
                    }
                    if ($actualField === 'Version' && ! in_array($actualField, $requiredFields, true)) {
                        $versioningPresent = $dataObject->hasVersioning();
                        if (! $versioningPresent) {
                            $this->printString("{$dataClass}.{$actualField} {$link}", 'deleted');
                            if ($deleteAll) {
                                $this->deleteField($dataClass, $actualField);
                            }
                        }
                    }
                }
            }
        } else {
            $databaseSchema = DB::get_schema();
            if ($databaseSchema->hasTable($dataClass)) {
                $this->printString("  **** The {$dataClass} table exists, but according to the data-scheme it should not be there ", 'deleted');
            } else {
                $this->notCheckedArray[] = $dataClass;
            }
        }
    }

    protected function printHr()
    {
        $this->printString('<hr />');
    }

    protected function printHeader($string, $headerNumber = 1, $style = '')
    {
        $this->printString($string, $style, $headerNumber);
    }

    protected function printString($string, $type = '', ?int $headerNumber = 0, $isInline = false)
    {
        match ($type) {
            'error' => $style = 'red',
            'deleted' => $style = 'red',
            'warning' => $style = 'orange',
            'created' => $style = 'green',
            'info' => $style = 'blue',
            default => $style = 'black'
        };
        if ($isInline) {
            $v = '<span style="color:' . $style . '">' . $string . '</span>';
        } elseif ($headerNumber) {
            $v = '<h' . $headerNumber . ' style="color:' . $style . '">' . $string . '</h' . $headerNumber . '>';
        } else {
            $v = '<p style="color:' . $style . '">' . $string . '</p>';
        }
        if (Director::is_cli()) {
            echo strip_tags($string) . "\n";
        } else {
            echo $v;
        }
    }

    protected function isMarkedAsObsolete(string $table): bool
    {
        return substr($table, 0, 10) === '_obsolete_';
    }

    protected function fixBrokenDataObject(string $dataClass, string $tableName, ?bool $tryToFix = false)
    {
        if (! $this->tableExists($tableName)) {
            return;
        }
        $rawIds = DB::query("SELECT \"ID\" FROM \"{$tableName}\"")->column();
        Versioned::set_reading_mode('Stage.Stage');
        $realCount = 0;
        $objects = $dataClass::get();
        $realIds = $objects->columnUnique();
        $rawCount = count($rawIds);
        $realCount = count($realIds);
        $diff = array_diff($rawIds, $realIds);
        if (count($diff)) {
            $this->printHr();
            $sign = ' > ';
            if ($rawCount < $realCount) {
                $sign = ' < ';
            }
            $this->printString("The DB Table Row Count != DataObject Count for <strong>{$dataClass} ({$rawCount} {$sign} {$realCount})</strong>.", 'deleted');
            $this->printHr();
            if ($tryToFix) {
                foreach ($diff as $id) {
                    /**
                     * @var  DataObject $object
                     */
                    $object = $dataClass::get()->byID($id);
                    if ($object) {
                        $this->printString("Now trying to recreate missing item {$id} ...", 'created');
                        Config::modify()->set(DataObject::class, 'validation_enabled', false);
                        $object->write(true, true, true, false);
                        Config::modify()->set(DataObject::class, 'validation_enabled', true);
                    }
                }
                $ancestors = ClassInfo::ancestry($dataClass, true);
                if ($ancestors && is_array($ancestors) && count($ancestors)) {
                    foreach ($ancestors as $ancestor) {
                        $ancestorObject = Injector::inst()->get($ancestor);
                        $ancestorSchema = $ancestorObject->getSchema();
                        $ancestorTable = $ancestorSchema->tableName($ancestor);
                        if ($ancestor !== $dataClass) {
                            $this->printString("Deleting record without data in {$ancestorTable} ...", 'deleted');
                            DB::query(
                                "
                                DELETE \"{$tableName}\".* FROM \"{$tableName}\"
                                LEFT JOIN \"{$ancestorTable}\"
                                    ON \"{$tableName}\".\"ID\" = \"{$ancestorTable}\".\"ID\"
                                WHERE \"{$ancestorTable}\".\"ID\" IS NULL;"
                            );
                        }
                    }
                }
            }
        }
    }

    protected function fieldExists(string $table, string $field): bool
    {
        $fields = DB::field_list($table);
        return isset($fields[$field]);
    }
}
