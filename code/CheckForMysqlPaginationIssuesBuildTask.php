<?php


class CheckForMysqlPaginationIssuesBuildTask extends BuildTask
{

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

    protected $timePerClass = [];

    public function run($request)
    {
        // give us some time to run this
        ini_set('max_execution_time', 3000);
        $array = [
            'l' => 'limit',
            's' => 'step',
            'd' => 'debug',
            'q' => 'quickAndDirty',
        ];
        foreach($array as $getParam => $field) {
            if(isset($_GET[$getParam])) {
                $this->$field = intval($_GET[$getParam]);
            }
        }
        $this->flushNow('<style>li {list-style: none!important;}h2.group{text-align: center;}</style>');
        $this->flushNow('<h3>Scroll down to bottom to see results. Output ends with <i>END</i></h3>', 'notice');
        $this->flushNow(
            '
                We run through all the summary fields for all dataobjects and select <i>limits</i> (segments) of the datalist.
                After that we check if the same ID shows up on different segments.
                If there are duplicates then Pagination is broken.
            ', 'notice'
        );
        $this->flushNow('<hr /><hr /><hr /><hr /><h2 class="group">SETTINGS </h2><hr /><hr /><hr /><hr />');
        $this->flushNow('
            <form method="get" action="/dev/tasks/CheckForMysqlPaginationIssuesBuildTask">
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

        // get all DataObjects and loop through them
        $classes = ClassInfo::subclassesFor('DataObject');
        foreach($classes as $class) {
            // skip irrelevant ones
            if($class !== 'DataObject') {
                //skip test ones
                $obj = Injector::inst()->get($class);
                if($obj instanceof FunctionalTest || $obj instanceof TestOnly) {
                    $this->flushNowDebug('<h2>SKIPPING: '.$class.'</h2>');
                    continue;
                }
                //start the process ...
                $this->flushNowDebug('<h2>Testing '.$class.'</h2>');

                // must exist is its own table to avoid doubling-up on tests
                // e.g. test SiteTree and Page where Page is not its own table ...
                if($this->tableExists($class)) {
                    $this->timePerClass[$class] = [];
                    $this->timePerClass[$class]['start'] = microtime(true);
                    // check table size
                    $count = $class::get()->count();
                    if($count > $this->step) {
                        $this->flushNowQuick('<br />'.$class.': ');
                        if(! isset($errors[$class])) {
                            $errors[$class] = [];
                        }
                        // get fields ...
                        $dbFields = $obj->db();
                        if(! is_array($dbFields)) {
                            $dbFields = [];
                        }
                        // adding base fields.
                        // we do not add ID as this should work!
                        $dbFields['ClassName'] = 'ClassName';
                        $dbFields['Created'] = 'Created';
                        $dbFields['LastEdited'] = 'LastEdited';

                        $hasOneFields = $obj->hasOne();
                        if(! is_array($hasOneFields)) {
                            $hasOneFields = [];
                        }

                        //start looping through summary fields ...
                        $summaryFields = $obj->summaryFields();
                        foreach ($summaryFields as $field => $value) {
                            if(isset($dbFields[$field]) || isset($hasOneFields[$field])) {
                                $this->flushNowQuick(' / '.$field.': ');
                                // reset comparisonArray - this is important ...
                                $comparisonArray = [];
                                //fix has one field
                                if(isset($hasOneFields[$field])) {
                                    $field .= 'ID';
                                }
                                if(! isset($errors[$class][$field])) {
                                    $errors[$class][$field] = [];
                                }
                                // start loop of limits ...
                                $this->flushNowDebug('- Sorting by '.$field);
                                for($i = 0; $i < $this->limit && $i < ($count - $this->step); $i += $this->step) {

                                    // OPTION 1
                                    if($this->quickAndDirty) {
                                        if(DataObject::has_own_table_database_field($class, $field)) {
                                            $tempRows = DB::query('SELECT "ID" FROM "'.$class.'" ORDER BY "'.$field.'" ASC LIMIT '.$i.', '.$this->step.';');
                                            foreach($tempRows as $row) {
                                                $id = $row['ID'];
                                                if(isset($comparisonArray[$id])) {
                                                    if(! isset($errors[$class][$field][$id])) {
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
                                        foreach($tempObjects as $tempObject) {
                                            $id = $tempObject->ID;
                                            if(isset($comparisonArray[$id])) {
                                                if(! isset($errors[$class][$field][$id])) {
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
                                if(count($errors[$class][$field])) {
                                    $error =    '<br /><strong>Found double entries in <u>'.$class.'</u> table,'.
                                                ' sorting by <u>'.$field.'</u></strong> ...';
                                    foreach($errors[$class][$field] as $tempID => $tempCount) {
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
        foreach($errors as $class => $fieldValues) {
            $this->flushNow('<h4>'.$class.'</h4>');
            $time = round(($this->timePerClass[$class]['end'] - $this->timePerClass[$class]['start']) * 1000);
            $this->flushNow('Time taken: '.$time.'μs');
            $errorCount = 0;
            foreach($fieldValues as $field => $errorMessage) {
                if(is_string($errorMessage) && $errorMessage) {
                    $errorCount++;
                    $this->flushNow($errorMessage, 'deleted');
                }
            }
            if($errorCount === 0) {
                $this->flushNow('No errors', 'created');
            }
        }
        echo '<hr /><hr /><hr /><hr /><h2 class="group">END </h2><hr /><hr /><hr /><hr />';
    }

    protected function flushNowDebug($error, $style = '')
    {
        if($this->debug) {
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
        $db = DB::getConn();
        return $db->hasTable($table);
    }
}
