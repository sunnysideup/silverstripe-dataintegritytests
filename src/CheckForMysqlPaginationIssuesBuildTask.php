<?php

namespace Sunnysideup\DataIntegrityTest;









use SilverStripe\ORM\DataObject;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DB;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\BuildTask;




class CheckForMysqlPaginationIssuesBuildTask extends BuildTask
{
    private static $skip_tables = [];

    /**
     * standard SS variable
     * @var String
     */
    protected $title = 'Goes through all tables and checks for bad pagination';

    /**
     * standard SS variable
     * @var String
     */
    protected $description = "Goes through all DataObjects to check if pagination can cause data-errors.";

    protected $limit = 100;

    protected $step = 15;

    protected $quickAndDirty = false;

    protected $debug = false;

    protected $testTableCustom = '';

    protected $timePerClass = [];

    public function run($request)
    {

        // give us some time to run this
        ini_set('max_execution_time', 3000);
        $classes = ClassInfo::subclassesFor(DataObject::class);
        $array = [
            'l' => 'limit',
            's' => 'step',
            'd' => 'debug',
            'q' => 'quickAndDirty',
            't' => 'testTableCustom'
        ];
        foreach ($array as $getParam => $field) {
            if (isset($_GET[$getParam])) {
                $v = $_GET[$getParam];
                switch ($getParam) {
                    case 't':
                        if (in_array($v, $classes)) {
                            $this->$field = $v;
                        }
                        break;
                    default:
                        $this->$field = intval($v);

                }
            }
        }
        $this->flushNowQuick('<style>li {list-style: none!important;}h2.group{text-align: center;}</style>');
        $this->flushNow('<h3>Scroll down to bottom to see results. Output ends with <i>END</i></h3>', 'notice');
        $this->flushNowQuick(
            '
                We run through all the summary fields for all dataobjects and select <i>limits</i> (segments) of the datalist.
                After that we check if the same ID shows up on different segments.
                If there are duplicates then Pagination is broken.
            ',
            'notice'
        );
        $this->flushNow('<hr /><hr /><hr /><hr /><h2 class="group">SETTINGS </h2><hr /><hr /><hr /><hr />');
        $this->flushNow('
            <form method="get" action="/dev/tasks/CheckForMysqlPaginationIssuesBuildTask">
                <br /><br />test table:<br /><input name="t" placeholder="e.g. SiteTree" value="'.$this->testTableCustom.'" />
                <br /><br />limit:<br /><input name="l" placeholder="limit" value="'.$this->limit.'" />
                <br /><br />step:<br /><input name="s" placeholder="step" value="'.$this->step.'" />
                <br /><br />debug:<br /><select name="d" placeholder="debug" /><option value="0">false</option><option value="1" '.($this->debug ? 'selected="selected"' : '').'>true</option></select>
                <br /><br />quick:<br /><select name="q" placeholder="quick" /><option value="0">false</option><option value="1" '.($this->quickAndDirty ? ' selected="selected"' : '').'>true</option></select>
                <br /><br /><input type="submit" value="run again with variables above" />
            </form>
        ');
        $this->flushNow('<hr /><hr /><hr /><hr /><h2 class="group">CALCULATIONS </h2><hr /><hr /><hr /><hr />');
        // array of errors
        $errors = [];
        $largestTable = '';
        $largestTableCount = 0;
        $skipTables = $this->Config()->get('skip_tables');
        // get all DataObjects and loop through them

        foreach ($classes as $class) {
            if (in_array($class, $skipTables)) {
                continue;
            }
            // skip irrelevant ones
            if ($class !== DataObject::class) {
                //skip test ones
                $obj = Injector::inst()->get($class);
                if ($obj instanceof FunctionalTest || $obj instanceof TestOnly) {
                    $this->flushNowDebug('<h2>SKIPPING: '.$class.'</h2>');
                    continue;
                }
                //start the process ...
                $this->flushNowDebug('<h2>Testing '.$class.'</h2>');

                // must exist is its own table to avoid doubling-up on tests
                // e.g. test SiteTree and Page where Page is not its own table ...
                if ($this->tableExists($class)) {
                    $this->timePerClass[$class] = [];
                    $this->timePerClass[$class]['start'] = microtime(true);
                    // check table size
                    $count = $class::get()->count();
                    $checkCount = DB::query('SELECT COUNT("ID") FROM "'.$class.'"')->value();
                    if (intval($checkCount) !== intval($count)) {
                        $this->flushNow('
                            COUNT error!
                            '.$class.' ::get: '.$count.' rows BUT 
                            DB::query(...): '.$checkCount.' rows | 
                            DIFFERENCE:  '.abs($count - $checkCount).'', 'deleted');
                    }
                    if ($count > $this->step) {
                        if ($count > $largestTableCount) {
                            $largestTableCount = $count;
                            $largestTable = $class;
                        }
                        $this->flushNowQuick('<br />'.$class.': ');
                        if (! isset($errors[$class])) {
                            $errors[$class] = [];
                        }
                        // get fields ...

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: ->db() (case sensitive)
  * NEW: ->Config()->get('db') (COMPLEX)
  * EXP: Check implementation
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
                        $dbFields = $obj->Config()->get('db');
                        if (! is_array($dbFields)) {
                            $dbFields = [];
                        }
                        // adding base fields.
                        // we do not add ID as this should work!
                        $dbFields['ClassName'] = 'ClassName';
                        $dbFields['Created'] = 'Created';
                        $dbFields['LastEdited'] = 'LastEdited';

                        $hasOneFields = $obj->hasOne();
                        if (! is_array($hasOneFields)) {
                            $hasOneFields = [];
                        }

                        //start looping through summary fields ...
                        $summaryFields = $obj->summaryFields();
                        foreach ($summaryFields as $field => $value) {
                            if (isset($dbFields[$field]) || isset($hasOneFields[$field])) {
                                $this->flushNowQuick(' / '.$field.': ');
                                // reset comparisonArray - this is important ...
                                $comparisonArray = [];
                                //fix has one field
                                if (isset($hasOneFields[$field])) {
                                    $field .= 'ID';
                                }
                                if (! isset($errors[$class][$field])) {
                                    $errors[$class][$field] = [];
                                }
                                // start loop of limits ...
                                $this->flushNowDebug('- Sorting by '.$field);
                                for ($i = 0; $i < $this->limit && $i < ($count - $this->step); $i += $this->step) {

                                    // OPTION 1
                                    if ($this->quickAndDirty) {
                                        if (DataObject::has_own_table_database_field($class, $field)) {
                                            $tempRows = DB::query('SELECT "ID" FROM "'.$class.'" ORDER BY "'.$field.'" ASC LIMIT '.$i.', '.$this->step.';');
                                            foreach ($tempRows as $row) {
                                                $id = $row['ID'];
                                                if (isset($comparisonArray[$id])) {
                                                    if (! isset($errors[$class][$field][$id])) {
                                                        $errors[$class][$field][$id] = 1;
                                                    }
                                                    $errors[$class][$field][$id]++;
                                                } else {
                                                    $this->flushNowQuick('.');
                                                }
                                                $comparisonArray[$id] = $id;
                                            }
                                        } else {
                                            $this->flushNowDebug('<strong>SKIP: '.$class.'.'.$field.'</strong> does not exist');
                                            break;
                                        }

                                        // OPTION 2
                                    } else {
                                        $tempObjects = $class::get()->sort($field)->limit($this->step, $i);
                                        foreach ($tempObjects as $tempObject) {
                                            $id = $tempObject->ID;
                                            if (isset($comparisonArray[$id])) {
                                                if (! isset($errors[$class][$field][$id])) {
                                                    $errors[$class][$field][$id] = 1;
                                                }
                                                $errors[$class][$field][$id]++;
                                            } else {
                                                $this->flushNowQuick('.');
                                            }
                                            $comparisonArray[$tempObject->ID] = $tempObject->ID;
                                        }
                                    }
                                }
                                if (count($errors[$class][$field])) {
                                    $error =    '<br /><strong>Found double entries in <u>'.$class.'</u> table,'.
                                                ' sorting by <u>'.$field.'</u></strong> ...';
                                    foreach ($errors[$class][$field] as $tempID => $tempCount) {
                                        $error .= ' ID: '.$tempID.' occurred '.$tempCount.' times /';
                                    }
                                    $this->flushNowDebug($error, 'deleted');
                                    $errors[$class][$field] = $error;
                                }
                            } else {
                                $this->flushNowDebug('<strong>SKIP: '.$class.'.'.$field.' field</strong> because it is not a DB field.');
                            }
                        }
                    } else {
                        $this->flushNowDebug('<strong>SKIP: table '.$class.'</strong> because it does not have enough records. ');
                    }
                    $this->timePerClass[$class]['end'] = microtime(true);
                } else {
                    $this->flushNowDebug('SKIP: '.$class.' because table does not exist. ');
                }
            }
        }
        $this->flushNow('<hr /><hr /><hr /><hr /><h2 class="group">RESULTS </h2><hr /><hr /><hr /><hr />');
        //print out errors again ...
        foreach ($errors as $class => $fieldValues) {
            $this->flushNow('<h4>'.$class.'</h4>');
            $time = round(($this->timePerClass[$class]['end'] - $this->timePerClass[$class]['start']) * 1000);
            $this->flushNow('Time taken: '.$time.'μs');
            $errorCount = 0;
            foreach ($fieldValues as $field => $errorMessage) {
                if (is_string($errorMessage) && $errorMessage) {
                    $errorCount++;
                    $this->flushNow($errorMessage, 'deleted');
                }
            }
            if ($errorCount === 0) {
                $this->flushNow('No errors', 'created');
            }
        }
        if ($this->testTableCustom) {
            $largestTable = $this->testTableCustom;
        }
        $this->speedComparison($largestTable);
        $this->flushNow('<hr /><hr /><hr /><hr /><h2 class="group">END </h2><hr /><hr /><hr /><hr />');
    }

    protected function flushNowDebug($error, $style = '')
    {
        if ($this->debug) {
            $this->flushNow($error, $style);
        }
    }

    protected function flushNow($error, $style = '')
    {
        DB::alteration_message($error, $style);
        $this->flushToBrowser();
    }

    protected function flushNowQuick($msg)
    {
        echo $msg;
        $this->flushToBrowser();
    }

    protected function flushToBrowser()
    {
        // check that buffer is actually set before flushing
        if (ob_get_length()) {
            @ob_flush();
            @flush();
            @ob_end_flush();
        }
        @ob_start();
    }

    protected function tableExists($table)
    {
        $db = DB::get_conn();
        return $db->hasTable($table);
    }


/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
    protected function speedComparison($className)
    {

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
        $this->flushNow('<hr /><hr /><hr /><hr /><h2 class="group">SPEED COMPARISON FOR '.$className.' with '.$className::get()->count().' records</h2><hr /><hr /><hr /><hr />');
        $testSeq = ['A', 'B', 'C', 'C', 'B', 'A'];
        shuffle($testSeq);
        $this->flushNow('Test sequence: '.print_r(implode(', ', $testSeq)));
        $testAResult = 0;
        $testBResult = 0;
        $testCResult = 0;
        $isFirstRound = false;
        foreach ($testSeq as $testIndex => $testLetter) {
            if ($testIndex > 2) {
                $isFirstRound = true;
            }
            if ($testLetter === 'A') {

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
                $objects = $className::get();

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
                $testAResult += $this->runObjects($objects, $className, $isFirstRound);
            }

            if ($testLetter === 'B') {

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
                $objects = $className::get()->sort(['ID' => 'ASC']);

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
                $testBResult += $this->runObjects($objects, $className, $isFirstRound);
            }

            if ($testLetter === 'C') {

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
                $defaultSortField = Config::inst()->get($className, 'default_sort');

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
                Config::modify()->update($className, 'default_sort', null);

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
                $objects = $className::get();

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
                $testCResult += $this->runObjects($objects, $className, $isFirstRound);

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
                Config::modify()->update($className, 'default_sort', $defaultSortField);
            }
        }
        $testAResult = round($testAResult * 1000);
        $testBResult = round($testBResult * 1000);
        $testCResult = round($testCResult * 1000);

        $this->flushNow('Default sort ('.print_r($defaultSortField, 1).'): '.$testAResult.'μs');
        $this->flushNow('ID sort '.$testBResult.'μs, '.(100-(round($testBResult / $testAResult, 2)*100)).'% faster than the default sort');
        $this->flushNow('No sort '.$testCResult.'μs, '.(100-(round($testCResult / $testAResult, 2)*100)).'% faster than the default sort');
    }

    /**
     *
     * @param  DataList  $dataList
     * @param  string  $className
     * @param  boolean $isFirstRound
     *
     * @return float
     */

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
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

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
                $this->flushNowDebug($className.' with ID = '.$object->ID.' (not sorted)');
            }
            $end = microtime(true);
            $time += $end - $start;
        }
        return $time;
    }
}
