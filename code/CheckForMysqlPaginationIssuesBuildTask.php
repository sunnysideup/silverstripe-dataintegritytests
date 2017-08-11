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

    protected $limit = 20;

    protected $step = 2;

    protected $quickAndDirty = false;

    public function run($request)
    {
        ini_set('max_execution_time', 3000);
        $classes = ClassInfo::subclassesFor('DataObject');
        foreach($classes as $class) {
            if($class !== 'DataObject') {
                $obj = Injector::inst()->get($class);
                print_r($obj->class);
                if($obj instanceof FunctionalTest || $obj instanceof TestOnly) {
                    $this->flushNow('<h2>SKIPPING: '.$class.'</h2>');
                    continue;
                }
                $this->flushNow('<h2>'.$class.'</h2>');
                $count = $class::get()->count();
                if($this->tableExists($class)) {
                    if($count > $this->step) {
                        $comparisonArray = [];
                        $summaryFields = $obj->summaryFields();
                        $dbFields = $obj->stat('db');
                        if(! is_array($dbFields)) {
                            $dbFields = [];
                        }

                        $hasOneFields = $obj->stat('has_one');
                        if(! is_array($hasOneFields)) {
                            $hasOneFields = [];
                        }
                        foreach ($summaryFields as $field => $value) {
                            if(isset($dbFields[$field])) {
                                $this->flushNow('<h4>Sorting by '.$field.'</h4>');
                                for($i = 0; $i < $this->limit && $i < ($count - $this->step); $i += $this->step) {
                                    if($this->quickAndDirty) {
                                        $tempRows = DB::query('SELECT "ID" FROM "'.$class.'" ORDER BY "'.$field.'" ASC LIMIT '.$i.', '.$this->step.';');
                                        foreach($tempRows as $row) {
                                            $id = $row['ID'];
                                            if(isset($comparisonArray[$id])) {
                                                $error = 'Found double entry: '.$id.' in '.$class;
                                                $this->flushNow($error, 'deleted');
                                            } else {
                                                echo '.';
                                            }
                                            $comparisonArray[$id] = $id;
                                        }
                                    } else {
                                        $tempObjects = $class::get()->sort($field)->limit($this->step, $i);
                                        foreach($tempObjects as $tempObject) {
                                            if(isset($comparisonArray[$tempObject->ID])) {
                                                $error = 'Found double entry: '.$tempObject->ID.' in '.$class;
                                                $this->flushNow($error, 'deleted');
                                            } else {
                                                echo '.';
                                            }
                                            $comparisonArray[$tempObject->ID] = $tempObject->ID;
                                        }
                                    }
                                }
                            } else {
                                $this->flushNow('<h4>Not sorting by '.$field.'</h4>');
                            }
                        }
                    } else {
                       $this->flushNow('<h3>There are not enough records available</h3>');
                   }
                } else {
                    $this->flushNow($class.' table does not exist');
                }
            }

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
