<?php

namespace Sunnysideup\DataIntegrityTest;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Versioned\Versioned;

class DataIntegrityTest extends BuildTask
{
    /**
     * standard SS variable
     * @var string
     */
    protected string $title = 'Check Database Integrity';

    /**
     * standard SS variable
     * @var string
     */
    protected static string $commandName = 'dataintegritytest';

    protected $debug = false;

    /**
     * standard SS variable
     * @var string
     */
    protected static string $description = 'Go through all fields in the database and work out what fields are superfluous / obsolete.';

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
        'deleteliveonlyrecords' => 'ADMIN',
        'removeorphanedmanymany' => 'ADMIN',
    ];

    /**
     * Stored PolyOutput instance for use in helper methods.
     */
    protected PolyOutput $polyOutput;

    public function getOptions(): array
    {
        return [
            new InputOption('do', null, InputOption::VALUE_REQUIRED, 'Action to perform: obsoletefields, tablereview, deletemarkedfields, deleteonefield, deleteobsoletetables, deleteallversions, cleanupdb, deleteliveonlyrecords, removeorphanedmanymany', ''),
            new InputOption('deletesafeones', null, InputOption::VALUE_NONE, 'Delete obsolete fields that have no data (used with --do=obsoletefields)'),
            new InputOption('deleteall', null, InputOption::VALUE_NONE, 'Delete all obsolete fields (used with --do=obsoletefields)'),
            new InputOption('makeobsolete', null, InputOption::VALUE_NONE, 'Rename obsolete tables with _obsolete_ prefix (used with --do=tablereview)'),
            new InputOption('fixbrokendataobjects', null, InputOption::VALUE_NONE, 'Attempt to fix broken data objects (used with --do=tablereview)'),
            new InputOption('deletetablealltogether', null, InputOption::VALUE_NONE, 'Delete obsolete tables entirely (used with --do=tablereview)'),
            new InputOption('tablefield', null, InputOption::VALUE_REQUIRED, 'Table/field to delete in format TableName/FieldName (used with --do=deleteonefield)', ''),
        ];
    }

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $this->polyOutput = $output;

        Environment::increaseTimeLimitTo(3000);
        Environment::increaseMemoryLimitTo('1024M');
        if ($this->debug) {
            $this->printHeader('DEBUG MODE ---- NO DELETIONS ARE MADE', 2, 'deleted');
        } else {
            $this->printHeader('NOT RUNNING DEBUG MODE ---- ACTUAL DELETIONS ARE MADE', 2, 'deleted');
        }

        $action = (string) ($input->getOption('do') ?? '');
        if ($action) {
            $methodArray = explode('/', $action);
            $method = $methodArray[0];
            $allowedActions = Config::inst()->get(DataIntegrityTest::class, 'allowed_actions');
            if (isset($allowedActions[$method])) {
                if ($method === 'obsoletefields') {
                    $deletesafeones = (bool) $input->getOption('deletesafeones');
                    $deleteall = (bool) $input->getOption('deleteall');
                    $this->obsoleteFields($deletesafeones, $deleteall);
                } elseif ($method === 'tablereview') {
                    $makeobsolete = (bool) $input->getOption('makeobsolete');
                    $fixbrokendataobjects = (bool) $input->getOption('fixbrokendataobjects');
                    $deletetablealltogether = (bool) $input->getOption('deletetablealltogether');
                    $this->tablereview($makeobsolete, $deletetablealltogether, $fixbrokendataobjects);
                } elseif ($method === 'deleteonefield') {
                    $tablefield = (string) ($input->getOption('tablefield') ?? '');
                    if ($tablefield) {
                        $requestExploded = explode('/', $tablefield);
                        $table = $requestExploded[0] ?? '';
                        $field = $requestExploded[1] ?? '';
                        if ($table && $field) {
                            if ($this->deleteField($table, $field)) {
                                $this->printString(sprintf('successfully deleted %s from %s now', $field, $table));
                            } else {
                                $this->printString(sprintf('COULD NOT delete %s from %s now', $field, $table), 'deleted');
                            }
                        } else {
                            $this->printString('Please supply --tablefield=TableName/FieldName');
                        }
                    } else {
                        $this->printString('Please supply --tablefield=TableName/FieldName');
                    }
                } else {
                    $this->{$method}();
                }
            } else {
                $this->printString('could not find method: ' . $method);
            }
        }

        $this->makeMenu();
        return Command::SUCCESS;
    }

    protected function makeMenu()
    {
        $this->printHeader('Database Administration Helpers');
        $this->printString('Run with --do=<action> to perform an action. Available actions:');
        $this->printString('sake tasks:' . static::$commandName . ' --do=obsoletefields');
        $this->printString('sake tasks:' . static::$commandName . ' --do=obsoletefields --deletesafeones');
        $this->printString('sake tasks:' . static::$commandName . ' --do=obsoletefields --deleteall');
        $this->printString('sake tasks:' . static::$commandName . ' --do=tablereview');
        $this->printString('sake tasks:' . static::$commandName . ' --do=tablereview --makeobsolete');
        $this->printString('sake tasks:' . static::$commandName . ' --do=tablereview --makeobsolete --deletetablealltogether');
        $this->printString('sake tasks:' . static::$commandName . ' --do=tablereview --fixbrokendataobjects');
        $this->printString('sake tasks:' . static::$commandName . ' --do=deleteobsoletetables');
        $this->printString('sake tasks:' . static::$commandName . ' --do=deletemarkedfields');
        $this->printString('sake tasks:' . static::$commandName . ' --do=deleteallversions');
        $this->printString('sake tasks:' . static::$commandName . ' --do=cleanupdb');
        $this->printString('sake tasks:' . static::$commandName . ' --do=deleteliveonlyrecords');
        $this->printString('sake tasks:' . static::$commandName . ' --do=removeorphanedmanymany');
        $this->printString('sake tasks:checkformysqlpaginationissuesbuildtask');
        $this->printString('sake tasks:dataintegritytestinnodb');
        $this->printString('sake tasks:dataintegritytestutf8');
        $this->printString('sake tasks:cleanoldchangesetstask');
        $this->printString('sake tasks:cleanoldchangesetstask --forreal --days=90');
    }

    protected function printLink(string $action, string $label, bool $confirm = false, $returnString = false): ?string
    {
        // @TODO (SS6 upgrade): printLink now outputs CLI sake commands rather than HTML links.
        $link = 'sake tasks:' . static::$commandName;
        if ($action !== '' && $action !== '0') {
            if (str_starts_with($action, '/dev/tasks')) {
                // extract task segment from /dev/tasks/xxx
                $link = str_replace('/dev/tasks/', 'sake tasks:', $action);
            } else {
                // Convert query string style (?do=xxx&foo=bar) to sake args (--do=xxx --foo=bar)
                $action = ltrim($action, '?');
                $action = str_replace('&', ' --', $action);
                $action = str_replace('=', '=', $action);
                $link .= ' --' . $action;
            }
        }

        $string = PHP_EOL . $label . ':' . PHP_EOL . ' ... ' . $link . PHP_EOL;

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
            if ($fieldsToDelete !== []) {
                // no need for key
                foreach ($fieldsToDelete as $tableDotField) {
                    $tableFieldArray = explode('.', (string) $tableDotField);
                    $this->deleteField($tableFieldArray[0], $tableFieldArray[1]);
                }
            } else {
                $this->printString('there are no fields to delete', 'created');
            }
        } else {
            $this->printString('you need to select these fields to be deleted first (DataIntegrityTest.fields_to_delete)');
        }

        $this->printLink('', 'back to main menu');
    }

    public function deleteonefield()
    {
        // @TODO (SS6 upgrade): In SS6 use --tablefield option instead; this method kept for back-compat.
        $tablefield = '';
        $requestExploded = explode('/', (string) $tablefield);
        $table = $requestExploded[0] ?? '';
        $field = $requestExploded[1] ?? '';
        if ($table === '' || $table === '0') {
            $this->printString('no table has been specified');
            return;
        }

        if ($field === '' || $field === '0') {
            $this->printString('no field has been specified');
            return;
        }

        if ($this->deleteField($table, $field)) {
            $this->printString(sprintf('successfully deleted %s from %s now', $field, $table));
        } else {
            $this->printString(sprintf('COULD NOT delete %s from %s now', $field, $table), 'deleted');
        }

        $this->printLink('', 'back to main menu.');
    }

    protected function Link()
    {
        return '/dev/tasks/' . static::$commandName;
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
                if ($requiredFields === []) {
                    continue;
                }

                $existingFields = array_keys(DB::field_list($tableName));

                $diff = array_diff($existingFields, $requiredFields);
                $diff2 = array_diff($requiredFields, $existingFields);

                if ($diff === [] && $diff2 === []) {
                    $this->printString($tableName . ' ... OK', 'created');
                } else {
                    $this->printString($tableName . ' ...');
                    foreach ($diff as $field) {
                        $this->printString(
                            sprintf('**** %s.%s EXIST BUT IT SHOULD NOT BE THERE!', $tableName, $field),
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
                                sprintf('**** %s.%s DOES NOT EXIST BUT IT SHOULD BE THERE!', $tableName, $field),
                                'deleted'
                            );
                        }
                    }
                }
            }

            $this->checkFieldsExtra($dataObject, $dataClass, $deleteSafeOnes, $deleteAll);
        }

        if ($this->canBeSafelyDeleted !== []) {
            $this->printHeader('Can be safely deleted:', 2);
            foreach ($this->canBeSafelyDeleted as $table => $fields) {
                $this->printString($table . ': ' . implode(', ', $fields));
            }
        }

        if ($this->notCheckedArray !== []) {
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
        if ($this->actualTables !== []) {
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
                    if (str_ends_with((string) $tmpTable, '_Live') && ! $this->isMarkedAsObsolete($tmpTable)) {
                        $remove = false;
                    }

                    if (str_ends_with((string) $tmpTable, '_Versions') && ! $this->isMarkedAsObsolete($tmpTable)) {
                        $remove = false;
                    }

                    //many 2 many tables...
                    if (strpos((string) $tmpTable, '_')) {
                        $manyManyClassShort = substr((string) $tmpTable, 0, strrpos((string) $tmpTable, '_'));
                        $manyManyRelName = substr((string) $tmpTable, strrpos((string) $tmpTable, '_') + 1 - strlen((string) $tmpTable));
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
                            $this->printString('ERROR: could not find class "' . $manyManyClass . '"');
                        }
                    }
                }

                if ($remove) {
                    $this->printHeader($tmpTable . ' ' . $tmpDataClass, 2);
                    if (! $this->isMarkedAsObsolete($tmpTable)) {
                        $rowCount = DB::query(sprintf('SELECT COUNT(*) FROM "%s"', $tmpTable))->value();
                        $this->printString($tmpTable . ', rows ' . $rowCount);
                        $obsoleteTableName = '_obsolete_' . $tmpTable;
                        if (! $this->tableExists($obsoleteTableName)) {
                            $this->printString(sprintf('... We recommend deleting %s or making it obsolete by renaming it to ', $tmpTable) . $obsoleteTableName, 'deleted');
                            if ($makeObsolete) {
                                if ($removeTableAltogether) {
                                    $this->printString(sprintf('... Deleting %s altogether', $tmpTable), 'deleted');
                                    if (! $this->debug) {
                                        DB::query(sprintf('DROP TABLE "%s" ', $tmpTable));
                                    }
                                } elseif (! $this->debug) {
                                    DB::get_schema()->renameTable($tmpTable, $obsoleteTableName);
                                }
                            } else {
                                $this->printString($tmpTable . ' - ' . $classExistsMessage . ' It can be moved to _obsolete_' . $tmpTable . '.', 'created');
                            }
                        } else {
                            $this->printString(sprintf('... We recommend to move %s to %s, but that table already exists', $tmpTable, $obsoleteTableName), 'deleted');
                        }
                    } elseif ($removeTableAltogether) {
                        $this->printString(sprintf('... Deleting %s altogether', $tmpTable), 'deleted');
                        if (! $this->debug) {
                            DB::query(sprintf('DROP TABLE "%s" ', $tmpTable));
                        }
                    } else {
                        $this->printString($tmpTable . ' - ' . $classExistsMessage . ' It can be moved to _obsolete_' . $tmpTable . '.', 'created');
                    }
                } else {
                    $this->printString($tmpTable . ' based on ' . $tmpDataClass . ' ... OK', 'created');
                }
            }
        }
    }

    protected function hasVersioning($dataObject)
    {
        $versioningPresent = false;
        $array = $dataObject->stat('extensions');
        if (is_array($array) && count($array) && in_array("Versioned('Stage', 'Live')", $array, true)) {
            $versioningPresent = true;
        }

        if ($dataObject->stat('versioning')) {
            $versioningPresent = true;
        }

        return $versioningPresent;
    }

    private function cleanupdb()
    {
        // @TODO (SS6 upgrade): DatabaseAdmin::create()->cleanup() — check if DatabaseAdmin still exists.
        $obj = \SilverStripe\Dev\DatabaseAdmin::create();
        $obj->cleanup();
        $this->printString('============= COMPLETED =================', '');
        $this->printLink('', 'back to main menu.');
    }

    private function deleteliveonlyrecords()
    {

        $dryRun = $this->debug;
        $schema = DB::get_schema();
        $tables = $schema->tableList();

        $liveTables = array_values(array_filter($tables, static fn(string $tableName): bool => str_ends_with($tableName, '_Live')));

        $this->printString('Found ' . count($liveTables) . ' *_Live table(s)');
        $this->printString($dryRun ? 'Mode: dry-run' : 'Mode: DELETE');

        $totalDeleted = 0;

        foreach ($liveTables as $liveTable) {
            $baseTable = substr((string) $liveTable, 0, -5); // remove "_Live"

            if (! in_array($baseTable, $tables, true)) {
                $this->printString(sprintf('Skip: %s (no base table %s)', $liveTable, $baseTable));
                continue;
            }

            if (! $this->tableHasIdColumn($baseTable) || ! $this->tableHasIdColumn($liveTable)) {
                $this->printString(sprintf('Skip: %s (missing ID column)', $liveTable));
                continue;
            }

            $countSql = $this->countOrphansSql($baseTable, $liveTable);
            $orphans = $this->fetchInt($countSql);

            if ($orphans === 0) {
                $this->printString(sprintf('OK: %s (0 orphans)', $liveTable));
                continue;
            }

            $this->printString(sprintf('Orphans: %s -> %s', $liveTable, $orphans), 'deleted');

            if ($dryRun) {
                continue;
            }

            $deleteSql = $this->deleteOrphansSql($baseTable, $liveTable);
            DB::query($deleteSql);

            $after = $this->fetchInt($countSql);
            $deleted = $orphans - $after;

            $totalDeleted += $deleted;

            $this->printString(sprintf('... Deleted: %s -> %d', $liveTable, $deleted), 'deleted');
        }

        $this->printString('Done. Total deleted: ' . $totalDeleted, 'deleted');

        $this->printString('============= COMPLETED =================', '');
        $this->printLink('', 'back to main menu.');
    }

    private function removeorphanedmanymany()
    {
        $schema = DataObject::getSchema();
        $allClasses = ClassInfo::subclassesFor(DataObject::class);

        foreach ($allClasses as $class) {
            if (! $schema->classHasTable($class)) {
                continue;
            }

            /** @var DataObject $singleton */
            $singleton = singleton($class);
            $manyMany = $singleton->config()->get('many_many') ?? [];
            $belongsManyMany = $singleton->config()->get('belongs_many_many') ?? [];

            $relations = array_merge($manyMany, $belongsManyMany);
            if ($relations === []) {
                continue;
            }

            foreach ($relations as $relName => $relDef) {
                $isThrough = is_array($relDef);

                if ($isThrough) {
                    $throughClass = $relDef['through'] ?? null;
                    $fromField = $relDef['from'] ?? null;
                    $toField = $relDef['to'] ?? null;

                    if (! $throughClass || ! $fromField || ! $toField) {
                        $this->printString(sprintf('Skipping %s.%s — incomplete many_many_through definition', $class, $relName), 'deleted');
                        continue;
                    }

                    $joinTable = $schema->tableName($throughClass);
                    $parentField = $fromField . 'ID';
                    $childField = $toField . 'ID';

                    // try to infer related class
                    $relClass = singleton($throughClass)->getRelationClass($toField)
                        ?? $relDef['to'] ?? null;
                } else {
                    $relClass = explode('.', (string) $relDef)[0];
                    $component = $schema->manyManyComponent($class, $relName);
                    if (empty($component['join'])) {
                        continue;
                    }

                    $joinTable = $component['join'];
                    $parentField = $component['parentField'];
                    $childField = $component['childField'];
                }

                if (! DB::get_schema()->hasTable($joinTable)) {
                    continue;
                }

                $parentTable = $schema->baseDataTable($class);
                $childTable = $schema->baseDataTable($relClass);

                $this->printString(sprintf('Checking %s (%s <> %s)', $joinTable, $class, $relClass));

                // --- Delete orphaned parent links ---
                $sql1 = <<<SQL
DELETE FROM "{$joinTable}"
WHERE "{$parentField}" NOT IN (SELECT "ID" FROM "{$parentTable}")
SQL;
                $this->debug ? $this->printString($sql1) : DB::query($sql1);
                $removed1 = DB::affected_rows();
                if ($removed1 > 0) {
                    $this->printString(sprintf('- Removed %d orphaned parent links', $removed1));
                }

                // --- Delete orphaned child links ---
                $sql2 = <<<SQL
DELETE FROM "{$joinTable}"
WHERE "{$childField}" NOT IN (SELECT "ID" FROM "{$childTable}")
SQL;
                $this->debug ? $this->printString($sql2) : DB::query($sql2);
                $removed2 = DB::affected_rows();
                if ($removed2 > 0) {
                    $this->printString(sprintf('- Removed %d orphaned child links', $removed2));
                }
            }
        }
    }

    private function deleteField(string $table, string $field)
    {
        $databaseSchema = DB::get_schema();
        $fields = $this->swapArray($databaseSchema->fieldList($table));
        $globalExeceptions = Config::inst()->get(DataIntegrityTest::class, 'global_exceptions');
        if (count($globalExeceptions) > 0) {
            foreach ($globalExeceptions as $exceptionTable => $exceptionField) {
                if ($exceptionTable === $table && $exceptionField === $field) {
                    $this->printString(sprintf('Listed %s.%s to be deleted, but this is listed as a global exception and can not be deleted', $table, $field), 'created');
                    return false;
                }
            }
        }

        if (! DB::query("SHOW TABLES LIKE '" . $table . "'")->value()) {
            $this->printString(sprintf('tried to delete %s.%s but TABLE does not exist', $table, $field), 'deleted');
            return false;
        }

        if (! in_array($field, $fields, true)) {
            $this->printString(sprintf('tried to delete %s.%s but FIELD does not exist', $table, $field), 'deleted');
            return false;
        }

        $this->printString(sprintf('Deleting %s in %s', $field, $table), 'deleted');
        foreach (['', '_Live', '_Versions'] as $suffix) {
            $tableNameFinal = $table . $suffix;
            if ($this->tableExists($tableNameFinal) && $this->fieldExists($tableNameFinal, $field) && ! $this->debug) {
                DB::query('ALTER TABLE "' . $tableNameFinal . '" DROP "' . $field . '";');
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
            if (str_starts_with((string) $table, '_obsolete_')) {
                $this->printString('Removing table ' . $table, 'deleted');
                if (! $this->debug) {
                    DB::query(sprintf('DROP TABLE "%s" ', $table));
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
            $endOfTable = substr((string) $table, -9);
            if ($endOfTable === '_Versions') {
                $this->printString('Removing all records from ' . $table, 'created');
                DB::query(sprintf('TRUNCATE "%s" ', $table));
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
        if ($requiredFields !== []) {
            foreach ($requiredFields as $field) {
                if (! $dataObject->hasDatabaseField($field)) {
                    $this->printString(sprintf('  **** %s.%s DOES NOT EXIST BUT IT SHOULD BE THERE!', $dataClass, $field), 'deleted');
                }
            }

            $schema = $dataObject->getSchema();
            $tableName = $schema->tableName($dataClass);
            $actualFields = $this->swapArray($databaseSchema->fieldList($tableName));
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

                if (! in_array($actualField, ['ID', 'Version'], true) && ! in_array($actualField, $requiredFields, true)) {
                    $distinctCount = DB::query(sprintf('SELECT COUNT(DISTINCT "%s") FROM "%s" WHERE "%s" IS NOT NULL ;', $actualField, $tableName, $actualField))->value();
                    $this->printString("{$dataClass}.{$actualField} {$link} - unique entries: {$distinctCount}", 'deleted');
                    if ($distinctCount) {
                        $rows = DB::query("
                                            SELECT \"{$actualField}\" as N, COUNT(\"{$actualField}\") as C
                                            FROM \"{$tableName}\"
                                            GROUP BY \"{$actualField}\"
                                            ORDER BY C DESC
                                            LIMIT 7");
                        if ($rows) {
                            foreach ($rows as $row) {
                                $this->printString('    ' . $row['C'] . ': ' . $row['N']);
                            }
                        }
                    } else {
                        if (! isset($this->canBeSafelyDeleted[$dataClass])) {
                            $this->canBeSafelyDeleted[$dataClass] = [];
                        }

                        $this->canBeSafelyDeleted[$dataClass][$actualField] = sprintf('%s.%s', $dataClass, $actualField);
                    }

                    if ($deleteAll || ($deleteSafeOnes && $distinctCount === 0)) {
                        $this->deleteField($tableName, $actualField);
                    }
                }

                if ($actualField === 'Version' && ! in_array($actualField, $requiredFields, true)) {
                    $versioningPresent = $dataObject->hasVersioning();
                    if (! $versioningPresent) {
                        $this->printString(sprintf('%s.%s %s', $dataClass, $actualField, $link), 'deleted');
                        if ($deleteAll) {
                            $this->deleteField($dataClass, $actualField);
                        }
                    }
                }
            }
        } else {
            $databaseSchema = DB::get_schema();
            if ($databaseSchema->hasTable($dataClass)) {
                $this->printString(sprintf('  **** The %s table exists, but according to the data-scheme it should not be there ', $dataClass), 'deleted');
            } else {
                $this->notCheckedArray[] = $dataClass;
            }
        }
    }

    protected function printHr()
    {
        $this->printString('---');
    }

    protected function printHeader($string, $headerNumber = 1, $style = '')
    {
        $this->printString($string, $style, $headerNumber);
    }

    protected function printString($string, $type = '', ?int $headerNumber = 0, $isInline = false)
    {
        $prefix = match ($type) {
            'error', 'deleted' => '[ERROR] ',
            'warning' => '[WARN]  ',
            'created' => '[OK]    ',
            'info' => '[INFO]  ',
            default => '        '
        };

        $plain = strip_tags((string) $string);

        if ($headerNumber) {
            $this->polyOutput->writeln(str_repeat('=', min($headerNumber * 4, 20)) . ' ' . $plain);
        } else {
            $this->polyOutput->writeln($prefix . $plain);
        }
    }

    protected function isMarkedAsObsolete(string $table): bool
    {
        return str_starts_with($table, '_obsolete_');
    }

    protected function fixBrokenDataObject(string $dataClass, string $tableName, ?bool $tryToFix = false)
    {
        if (! $this->tableExists($tableName)) {
            return;
        }

        $rawIds = DB::query(sprintf('SELECT "ID" FROM "%s"', $tableName))->column();
        Versioned::set_reading_mode('Stage.Stage');
        $realCount = 0;
        $objects = $dataClass::get();
        $realIds = $objects->columnUnique();
        $rawCount = count($rawIds);
        $realCount = count($realIds);
        $diff = array_diff($rawIds, $realIds);
        if ($diff !== []) {
            $this->printHr();
            $sign = ' > ';
            if ($rawCount < $realCount) {
                $sign = ' < ';
            }

            $this->printString(sprintf('The DB Table Row Count != DataObject Count for %s (%d %s %d).', $dataClass, $rawCount, $sign, $realCount), 'deleted');
            $this->printHr();
            if ($tryToFix) {
                foreach ($diff as $id) {
                    /**
                     * @var  DataObject $object
                     */
                    $object = $dataClass::get()->byID($id);
                    if ($object) {
                        $this->printString(sprintf('Now trying to recreate missing item %s ...', $id), 'created');
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
                            $this->printString(sprintf('Deleting record without data in %s ...', $ancestorTable), 'deleted');
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

    private function tableHasIdColumn(string $tableName): bool
    {
        $columns = DB::get_schema()->fieldList($tableName);
        return isset($columns['ID']);
    }

    private function countOrphansSql(string $baseTable, string $liveTable): string
    {
        return '
            SELECT COUNT(*) AS "Count"
            FROM "' . $liveTable . '" AS "L"
            WHERE NOT EXISTS (
                SELECT 1
                FROM "' . $baseTable . '" AS "S"
                WHERE "S"."ID" = "L"."ID"
            )
        ';
    }

    private function deleteOrphansSql(string $baseTable, string $liveTable): string
    {
        return '
            DELETE FROM "' . $liveTable . '"
            WHERE NOT EXISTS (
                SELECT 1
                FROM "' . $baseTable . '" AS "S"
                WHERE "S"."ID" = "' . $liveTable . '"."ID"
            )
        ';
    }

    private function fetchInt(string $sql): int
    {
        $rows = DB::query($sql);
        $found = false;
        foreach ($rows as $row) {
            $found = true;
        }

        if (! $found) {
            return 0;
        }

        return (int) (($row['Count'] ?? 0));
    }
}
