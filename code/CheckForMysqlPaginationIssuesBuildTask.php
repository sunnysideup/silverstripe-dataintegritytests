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

    protected $limit = 200;

    protected $step = 2;

    protected $quickAndDirty = false;

    protected $debug = false;

    public function run($request)
    {
        // give us some time to run this
        ini_set('max_execution_time', 3000);
        echo '<style>li {list-style: none!important;}</style>';
        $this->flushNow('<h3>Running through all DataObjects, limiting to '.$this->limit.' records and doing '.$this->step.' records at the time. Scroll down to bottom to see results.</h3>', 'notice');
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
                    // check table size
                    $count = $class::get()->count();
                    if($count > $this->step) {
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
                                                        $errors[$class][$field][$id] = 0;
                                                    }
                                                    $errors[$class][$field][$id]++;
                                                } else {
                                                    echo '.';
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
                                    $error =    '<br /><strong>Found double entries in '.$class.' table,'.
                                                ' when sorting with '.$field.'</strong> ...';
                                    foreach($errors[$class][$field] as $tempID => $tempCount) {
                                        $error .= ' ID: '.$tempID.' occurred '.$tempCount.' times /';
                                    }
                                    $this->flushNowDebug($error, 'deleted');
                                    $errors[$class][$field] = $error;
                                }
                                $this->flushNowQuick(' / ');

                            } else {
                                $this->flushNowDebug('<strong>SKIP: '.$class.'.'.$field.' field</strong> because it is not a DB field.');
                            }
                        }
                    } else {
                       $this->flushNowDebug('<strong>SKIP: table '.$class.'</strong> because it does not have enough records. ');
                   }
                } else {
                    $this->flushNowDebug('SKIP: '.$class.' because table does not exist. ');
                }
            }

        }
        echo '<hr /><hr /><hr /><hr />------------------------- END -----------------------------<hr /><hr /><hr /><hr />';
        //print out errors again ...
        foreach($errors as $class => $fieldValues) {
            $this->flushNow('<h4>'.$class.'</h4>');
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
