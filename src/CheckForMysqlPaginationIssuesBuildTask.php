<?php

namespace Sunnysideup\DataIntegrityTest;

use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\PolyExecution\PolyOutput;

class CheckForMysqlPaginationIssuesBuildTask extends BuildTask
{
    /**
     * standard SS variable
     * @var string
     */
    protected string $title = 'Goes through all tables and checks for bad pagination';

    /**
     * standard SS variable
     * @var string
     */
    protected static string $description = 'Goes through all DataObjects to check if pagination can cause data-errors.';

    protected $limit = 100;

    protected $step = 15;

    protected $quickAndDirty = false;

    protected $debug = false;

    protected $testClassCustom = '';

    protected $timePerClass = [];

    private static $skip_tables = [];

    protected static string $commandName = 'checkformysqlpaginationissuesbuildtask';

    /**
     * Stored PolyOutput instance for use in helper methods.
     */
    protected PolyOutput $polyOutput;

    #[Override]
    public function getOptions(): array
    {
        return [
            new InputOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Maximum number of rows to iterate per table', 100),
            new InputOption('step', 's', InputOption::VALUE_REQUIRED, 'Step size for pagination chunks', 15),
            new InputOption('debug', null, InputOption::VALUE_NONE, 'Enable debug output'),
            new InputOption('quick', 'q', InputOption::VALUE_NONE, 'Use quick-and-dirty raw SQL mode'),
            new InputOption('table', 't', InputOption::VALUE_REQUIRED, 'Test a single specific class/table', ''),
        ];
    }

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        // give us some time to run this
        ini_set('max_execution_time', 3000);

        $this->polyOutput = $output;
        $classes = ClassInfo::subclassesFor(DataObject::class, false);

        $this->limit        = (int) ($input->getOption('limit') ?? 100);
        $this->step         = (int) ($input->getOption('step') ?? 15);
        $this->debug        = (bool) $input->getOption('debug');
        $this->quickAndDirty = (bool) $input->getOption('quick');
        $testClassCustom    = (string) ($input->getOption('table') ?? '');
        if ($testClassCustom && in_array($testClassCustom, $classes, true)) {
            $this->testClassCustom = $testClassCustom;
        }

        $output->writeForHtml('<style>li {list-style: none!important;}h2.group{text-align: center;}</style>');
        $output->writeForHtml('<h3>Scroll down to bottom to see results. Output ends with <i>END</i></h3>');
        $output->writeForHtml('
            We run through all the summary fields for all dataobjects and select <i>limits</i> (segments) of the datalist.
            After that we check if the same ID shows up on different segments.
            If there are duplicates then Pagination may break if a list is paginated and sorted by that field.
        ');
        $output->writeForHtml('<hr /><h2 class="group">SETTINGS</h2><hr />');
        $output->writeForHtml('
            <p>limit: ' . $this->limit . ' | step: ' . $this->step . ' | debug: ' . ($this->debug ? 'true' : 'false') . ' | quick: ' . ($this->quickAndDirty ? 'true' : 'false') . ' | table: ' . ($this->testClassCustom ?: '(all)') . '</p>
        ');
        $output->writeForHtml('<hr /><h2 class="group">CALCULATIONS</h2><hr />');

        // array of errors
        $errors = [];
        $largestClass = '';
        $largestTableCount = 0;
        $skipTables = $this->Config()->get('skip_tables');
        // get all DataObjects and loop through them
        foreach ($classes as $class) {
            if (in_array($class, $skipTables, true)) {
                continue;
            }

            // skip irrelevant ones
            //skip test ones
            $obj = Injector::inst()->get($class);
            if ($obj instanceof FunctionalTest || $obj instanceof TestOnly) {
                $this->flushNowDebug('<h2>SKIPPING: ' . $class . '</h2>');
                continue;
            }

            //start the process ...
            $this->flushNowDebug('<h2>Testing ' . $class . '</h2>');
            $schema = $obj->getSchema();
            $tableName = $schema->tableName($class);
            // must exist is its own table to avoid doubling-up on tests
            if ($this->tableExists($tableName)) {
                $this->timePerClass[$tableName] = [];
                $this->timePerClass[$tableName]['start'] = microtime(true);
                // check table size
                $count = $class::get()->count();
                $checkCount = DB::query('SELECT COUNT("ID") FROM "' . $tableName . '"')->value();
                if (intval($checkCount) !== intval($count)) {
                    $this->flushNow('
                        COUNT error!
                        ' . $class . ' ::get: ' . $count . ' rows BUT
                        DB::query(...): ' . $checkCount . ' rows |
                        DIFFERENCE:  ' . abs($count - $checkCount) . '', 'deleted');
                }

                if ($count > $this->step) {
                    if ($count > $largestTableCount) {
                        $largestTableCount = $count;
                        $largestClass = $class;
                    }

                    $output->write('<br />' . $tableName . ': ');
                    if (! isset($errors[$tableName])) {
                        $errors[$tableName] = [];
                    }

                    // get fields ...
                    $dbFields = $obj->Config()->get('db');
                    if (! is_array($dbFields)) {
                        $dbFields = [];
                    }

                    // adding base fields.
                    $dbFields['ClassName'] = 'ClassName';
                    $dbFields['Created'] = 'Created';
                    $dbFields['LastEdited'] = 'LastEdited';

                    $hasOneFields = $obj->hasOne();
                    if (! is_array($hasOneFields)) {
                        $hasOneFields = [];
                    }

                    //start looping through summary fields ...
                    $summaryFields = $obj->summaryFields();
                    foreach (array_keys($summaryFields) as $field) {
                        if (isset($dbFields[$field]) || isset($hasOneFields[$field])) {
                            $output->write(' / ' . $field . ': ');
                            // reset comparisonArray - this is important ...
                            $comparisonArray = [];
                            //fix has one field
                            if (isset($hasOneFields[$field])) {
                                $field .= 'ID';
                            }

                            if (! isset($errors[$tableName][$field])) {
                                $errors[$tableName][$field] = [];
                            }

                            // start loop of limits ...
                            $this->flushNowDebug('- Sorting by ' . $field);
                            for ($i = 0; $i < $this->limit && $i < ($count - $this->step); $i += $this->step) {
                                // OPTION 1
                                if ($this->quickAndDirty) {
                                    if (DataObject::getSchema()->fieldSpec($class, $field)) {
                                        $tempRows = DB::query('SELECT "ID" FROM "' . $tableName . '" ORDER BY "' . $field . '" ASC LIMIT ' . $i . ', ' . $this->step . ';');
                                        foreach ($tempRows as $row) {
                                            $id = $row['ID'];
                                            if (isset($comparisonArray[$id])) {
                                                if (! isset($errors[$tableName][$field][$id])) {
                                                    $errors[$tableName][$field][$id] = 1;
                                                }

                                                $errors[$tableName][$field][$id]++;
                                            } else {
                                                $output->write('.');
                                            }

                                            $comparisonArray[$id] = $id;
                                        }
                                    } else {
                                        $this->flushNowDebug('<strong>SKIP: ' . $tableName . '.' . $field . '</strong> does not exist');
                                        break;
                                    }

                                    // OPTION 2
                                } else {
                                    $tempObjects = $class::get()->sort($field)->limit($this->step, $i);
                                    foreach ($tempObjects as $tempObject) {
                                        $id = $tempObject->ID;
                                        if (isset($comparisonArray[$id])) {
                                            if (! isset($errors[$tableName][$field][$id])) {
                                                $errors[$tableName][$field][$id] = 1;
                                            }

                                            $errors[$tableName][$field][$id]++;
                                        } else {
                                            $output->write('.');
                                        }

                                        $comparisonArray[$tempObject->ID] = $tempObject->ID;
                                    }
                                }
                            }

                            if ($errors[$tableName][$field] !== []) {
                                $error = '<br /><strong>Found double entries in <u>' . $tableName . '</u> table,' .
                                    ' sorting by <u>' . $field . '</u></strong> ...';
                                foreach ($errors[$tableName][$field] as $tempID => $tempCount) {
                                    $error .= ' ID: ' . $tempID . ' occurred ' . $tempCount . ' times /';
                                }

                                $this->flushNowDebug($error, 'deleted');
                                $errors[$tableName][$field] = $error;
                            }
                        } else {
                            $this->flushNowDebug('<strong>SKIP: ' . $tableName . '.' . $field . ' field</strong> because it is not a DB field.');
                        }
                    }
                } else {
                    $this->flushNowDebug('<strong>SKIP: table ' . $tableName . '</strong> because it does not have enough records. ');
                }

                $this->timePerClass[$tableName]['end'] = microtime(true);
            } else {
                $this->flushNowDebug('SKIP: ' . $tableName . ' because table does not exist. ');
            }
        }

        $output->writeForHtml('<hr /><h2 class="group">RESULTS</h2><hr />');
        //print out errors again ...
        foreach ($errors as $tableName => $fieldValues) {
            $output->writeForHtml('<h4>' . $tableName . '</h4>');
            $time = round(($this->timePerClass[$tableName]['end'] - $this->timePerClass[$tableName]['start']) * 1000);
            $output->writeln('Time taken: ' . $time . 'μs');
            $errorCount = 0;
            // key is field
            foreach ($fieldValues as $errorMessage) {
                if (is_string($errorMessage) && $errorMessage) {
                    $errorCount++;
                    $output->writeForHtml($errorMessage);
                }
            }

            if ($errorCount === 0) {
                $output->writeln('No errors');
            }
        }

        if ($this->testClassCustom) {
            $largestClass = $this->testClassCustom;
        } elseif (! $largestClass) {
            $largestClass = $class ?? '';
        }

        if ($largestClass) {
            $this->speedComparison($largestClass);
        }

        $output->writeForHtml('<hr /><h2 class="group">END</h2><hr />');
        return Command::SUCCESS;
    }

    protected function flushNowDebug($error, $style = '')
    {
        if ($this->debug) {
            $this->flushNow($error, $style);
        }
    }

    protected function flushNow($error, $style = '')
    {
        $this->polyOutput->writeForHtml($error);
    }

    protected function tableExists($table)
    {
        $databaseSchema = DB::get_schema();
        return $databaseSchema->hasTable($table);
    }

    protected function speedComparison($className)
    {
        $this->flushNow('<hr /><h2 class="group">SPEED COMPARISON FOR ' . $className . ' with ' . $className::get()->count() . ' records</h2><hr />');
        $testSeq = ['A', 'B', 'C', 'C', 'B', 'A'];
        shuffle($testSeq);
        $this->flushNow('Test sequence: ' . implode(', ', $testSeq));
        $testAResult = 0;
        $testBResult = 0;
        $testCResult = 0;
        $isFirstRound = false;
        $defaultSortField = '';
        foreach ($testSeq as $testIndex => $testLetter) {
            if ($testIndex > 2) {
                $isFirstRound = true;
            }

            if ($testLetter === 'A') {
                $objects = $className::get();
                $testAResult += $this->runObjects($objects, $className, $isFirstRound);
            }

            if ($testLetter === 'B') {
                $objects = $className::get()->sort(['ID' => 'ASC']);
                $testBResult += $this->runObjects($objects, $className, $isFirstRound);
            }

            if ($testLetter === 'C') {
                $defaultSortField = Config::inst()->get($className, 'default_sort');
                Config::modify()->set($className, 'default_sort', null);
                $objects = $className::get();
                $testCResult += $this->runObjects($objects, $className, $isFirstRound);
                Config::modify()->set($className, 'default_sort', $defaultSortField);
            }
        }

        $testAResult = round($testAResult * 1000);
        $testBResult = round($testBResult * 1000);
        $testCResult = round($testCResult * 1000);

        $this->flushNow('Default sort (' . print_r($defaultSortField, 1) . '): ' . $testAResult . 'μs');
        $this->flushNow('... ID sort ' . $testBResult . 'μs, ' . (100 - (round($testBResult / $testAResult, 2) * 100)) . '% faster than the default sort');
        $this->flushNow('... No sort ' . $testCResult . 'μs, ' . (100 - (round($testCResult / $testAResult, 2) * 100)) . '% faster than the default sort');
    }

    /**
     * @param  DataList  $objects
     * @param  string  $className
     * @param  boolean $isFirstRound
     *
     * @return float
     */
    protected function runObjects($objects, $className, $isFirstRound)
    {
        $time = 0;
        $objects = $objects->limit(10, 20);
        for ($i = 0; $i < 50; $i++) {
            if ($i === 0 && $isFirstRound) {
                $this->flushNowDebug($objects->sql());
            }

            $start = microtime(true);
            foreach ($objects as $object) {
                $this->flushNowDebug($className . ' with ID = ' . $object->ID . ' (not sorted)');
            }

            $end = microtime(true);
            $time += $end - $start;
        }

        return $time;
    }
}
