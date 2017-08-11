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

    public function run($request)
    {
        // give us some time to run this
        ini_set('max_execution_time', 3000);
        echo '<style>li {list-style: none!important;}</style>';
        $this->flushNow('<h3>Running through all DataObjects, limiting to '.$this->limit.' records and doing '.$this->step.' records at the time.</h3>', 'notice');
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
                    $this->flushNow('<h2>SKIPPING: '.$class.'</h2>');
                    continue;
                }
                //start the process ...
                $this->flushNow('<h2>'.$class.'</h2>');

                // must exist is its own table to avoid doubling-up on tests
                // e.g. test SiteTree and Page where Page is not its own table ...
                if($this->tableExists($class)) {
                    $count = $class::get()->count();
                    if($count > $this->step) {
                        $summaryFields = $obj->summaryFields();
                        $dbFields = $obj->db();
                        if(! is_array($dbFields)) {
                            $dbFields = [];
                        }
                        //adding base fields.
                        $dbFields['ClassName'] = 'ClassName';
                        $dbFields['Created'] = 'Created';
                        $dbFields['LastEdited'] = 'LastEdited';

                        $hasOneFields = $obj->hasOne();
                        if(! is_array($hasOneFields)) {
                            $hasOneFields = [];
                        }
                        foreach ($summaryFields as $field => $value) {
                            $comparisonArray = [];
                            if(isset($dbFields[$field])) {
                                $this->flushNow('- Sorting by '.$field);
                                for($i = 0; $i < $this->limit && $i < ($count - $this->step); $i += $this->step) {
                                    if($this->quickAndDirty) {
                                        if(DataObject::has_own_table_database_field($class, $field)) {
                                            $tempRows = DB::query('SELECT "ID" FROM "'.$class.'" ORDER BY "'.$field.'" ASC LIMIT '.$i.', '.$this->step.';');
                                            foreach($tempRows as $row) {
                                                $id = $row['ID'];
                                                if(isset($comparisonArray[$id])) {
                                                    $error = 'Found double entry: '.$id.' in '.$class;
                                                    $this->flushNow($error, 'deleted');
                                                    $errors[$class.$id] = $error;
                                                } else {
                                                    echo '.';
                                                }
                                                $comparisonArray[$id] = $id;
                                            }
                                        } else {
                                            $this->flushNow('<strong>SKIP: '.$class.'.'.$field.'</strong> does not exist');
                                        }
                                    } else {
                                        $tempObjects = $class::get()->sort($field)->limit($this->step, $i);
                                        foreach($tempObjects as $tempObject) {
                                            $id = $tempObject->ID;
                                            if(isset($comparisonArray[$id])) {
                                                $error = 'Found double entry: '.$id.' in '.$class;
                                                $this->flushNow($error, 'deleted');
                                                $errors[$class.$id] = $error;
                                            } else {
                                                echo '.';
                                            }
                                            $comparisonArray[$tempObject->ID] = $tempObject->ID;
                                        }
                                    }
                                }
                            } else {
                                $this->flushNow('<strong>SKIP: '.$class.'.'.$field.' field</strong> because it is not a DB field.');
                            }
                        }
                        $this->flushNow('No issues in '.$class, 'created');
                    } else {
                       $this->flushNow('<strong>SKIP: table '.$class.'</strong> because it does not have enough records. ');
                   }
                } else {
                    $this->flushNow('SKIP: '.$class.' because table does not exist. ');
                }
            }

        }
        echo '<hr /><hr /><hr /><hr />------------------------- END -----------------------------<hr /><hr /><hr /><hr />';
        //print out errors again ...
        foreach($errors as $error) {
            $this->flushNow($error, 'deleted');
        }
    }

    private function flushNow($error, $style = '')
    {
        DB::alteration_message($error, $style);
        // check that buffer is actually set before flushing
        if (ob_get_length()) {
            @ob_flush();
            @flush();
            @ob_end_flush();
        }
        @ob_start();
    }

    private function tableExists($table)
    {
        $db = DB::getConn();
        return $db->hasTable($table);
    }
}
