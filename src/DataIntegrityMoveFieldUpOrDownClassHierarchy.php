<?php

namespace Sunnysideup\DataIntegrityTest;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;

class DataIntegrityMoveFieldUpOrDownClassHierarchy extends BuildTask
{
    /**
     * standard SS variable
     * @var string
     */
    protected string $title = 'Move data field up or down class (table) hierarchy.';

    /**
     * standard SS variable
     * @var string
     */
    protected static string $description = '
		This is useful in case you change the hierarchy of classes
		and as a consequence your data ends up in the wrong table.
		To run this task you will first need to run a dev/build -
		after that all the eligible fields will be listed
		and the task gives you the ability to move each field individually as required.
	';

    protected static string $commandName = 'DataIntegrityMoveFieldUpOrDownClassHierarchy';

    /**
     * Stored PolyOutput instance for use in helper methods.
     */
    protected PolyOutput $polyOutput;

    public function getOptions(): array
    {
        return [
            new InputOption('oldtable', 'o', InputOption::VALUE_REQUIRED, 'Source table/class name to move the field FROM', ''),
            new InputOption('newtable', 'n', InputOption::VALUE_REQUIRED, 'Destination table/class name to move the field TO', ''),
            new InputOption('field', 'x', InputOption::VALUE_REQUIRED, 'Field name to move', ''),
            new InputOption('forreal', 'f', InputOption::VALUE_NONE, 'Execute the move for real (default: dry run / show results)'),
        ];
    }

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        ini_set('max_execution_time', 3000);

        $this->polyOutput = $output;

        $oldTable = (string) ($input->getOption('oldtable') ?? '');
        $newTable = (string) ($input->getOption('newtable') ?? '');
        $field    = (string) ($input->getOption('field') ?? '');
        $forreal  = (bool) $input->getOption('forreal');

        $databaseSchema = DB::get_schema();
        if ($oldTable && $newTable && $field) {
            if (class_exists($oldTable)) {
                if (class_exists($newTable)) {
                    $oldFields = array_keys($databaseSchema->fieldList($oldTable));
                    $newFields = array_keys($databaseSchema->fieldList($newTable));
                    $jointFields = array_intersect($oldFields, $newFields);
                    if (in_array($field, $jointFields, true)) {
                        if ($forreal) {
                            $output->writeForHtml(sprintf('Moving %s from %s to %s', $field, $oldTable, $newTable));
                            $sql = '
							UPDATE "' . $newTable . '"
								INNER JOIN "' . $oldTable . '"
								 ON "' . $newTable . '"."ID" = "' . $oldTable . '"."ID"
							SET "' . $newTable . '"."' . $field . '" = "' . $oldTable . '"."' . $field . '"
							WHERE
								"' . $newTable . '"."' . $field . '" = 0 OR
								"' . $newTable . '"."' . $field . '" IS NULL OR
								"' . $newTable . '"."' . $field . "\" = '0.00' OR
								\"" . $newTable . '"."' . $field . "\" = ''
								;";
                            DB::query($sql);
                            $sql = '
							INSERT IGNORE INTO "' . $newTable . "\" (ID, \"{$field}\")
							SELECT \"" . $oldTable . '".ID, "' . $oldTable . "\".\"{$field}\"
							FROM \"" . $oldTable . '"
								LEFT JOIN "' . $newTable . '"
								 ON "' . $newTable . '"."ID" = "' . $oldTable . '"."ID"
							WHERE
								"' . $newTable . '"."ID" IS NULL
								;';
                            DB::query($sql);
                            $this->deleteField($oldTable, $field);
                        } else {
                            $output->writeForHtml(sprintf('TESTING a move of %s from %s to %s', $field, $oldTable, $newTable));
                            $sql = '
							SELECT
								COUNT("' . $newTable . '"."ID") AS C
								FROM "' . $oldTable . '"
									INNER JOIN "' . $newTable . '"
									ON "' . $newTable . '"."ID" = "' . $oldTable . '"."ID"
								;';
                            $matchingRowCount = DB::query($sql)->value();
                            $sql = '
							SELECT
								"' . $newTable . '"."ID"
								FROM "' . $oldTable . '"
									INNER JOIN "' . $newTable . '"
									ON "' . $newTable . '"."ID" = "' . $oldTable . '"."ID"
								;';
                            $rows = DB::query($sql);
                            $matchingRows = [];
                            foreach ($rows as $row) {
                                $matchingRows[$row['ID']] = $row['ID'];
                            }

                            $sql = '
							SELECT
								"' . $newTable . '"."ID",
								"' . $newTable . '"."' . $field . '" AS NEW' . $field . ',
								"' . $oldTable . '"."' . $field . '" AS OLD' . $field . '
								FROM "' . $oldTable . '"
									INNER JOIN "' . $newTable . '"
									ON "' . $newTable . '"."ID" = "' . $oldTable . '"."ID"
							WHERE
								(
									"' . $newTable . '"."' . $field . '" <> "' . $oldTable . '"."' . $field . '"
								)
								OR
								(
									("' . $newTable . '"."' . $field . '" IS NULL AND "' . $oldTable . '"."' . $field . '" IS NOT NULL)
									 OR
									("' . $newTable . '"."' . $field . '" IS NOT NULL AND "' . $oldTable . '"."' . $field . '" IS NULL)
								)
								;';
                            $rows = DB::query($sql);
                            if ($rows->numRecords()) {
                                $output->writeForHtml(sprintf('<h3>DIFFERENCES in MATCHING ROWS (%s)</h3><table border="1"><thead><tr><th>ID</th><th>OLD</th><th>NEW</th><th>ACTION</th></tr></thead><tbody>', $matchingRowCount));
                                foreach ($rows as $row) {
                                    $action = 'do nothing';
                                    if (! $row['NEW' . $field] || $row['NEW' . $field] === '0.00') {
                                        $action = 'override';
                                    }

                                    $output->writeForHtml('<tr><td>' . $row['ID'] . '</td><td>' . $row['OLD' . $field] . '</td><td>' . $row['NEW' . $field] . '</td><td>' . $action . '</td></tr>');
                                }

                                $output->writeForHtml('</tbody></table>');
                            } else {
                                $output->writeForHtml('<p>No differences!</p>');
                            }

                            $sql = '
							SELECT
								COUNT("' . $oldTable . '"."ID") AS C
								FROM "' . $oldTable . '"
									LEFT JOIN "' . $newTable . '"
									ON "' . $newTable . '"."ID" = "' . $oldTable . '"."ID"
								WHERE "' . $newTable . '"."ID" IS NULL;
								;';
                            $nonMatchingRowCount = DB::query($sql)->value();
                            $output->writeForHtml('<h3>Number of rows to insert: ' . $nonMatchingRowCount . '</h3>');
                            $output->writeForHtml('<h2>Run with --forreal to move now!</h2>');
                        }
                    }
                } else {
                    $output->writeln('Field is not in both tables. We recommend that you run a dev/build first as this may solve the problem....');
                }
            } else {
                $output->writeln('Specify valid oldtable using --oldtable option');
            }
        }

        $output->writeForHtml('<hr />');
        $tablesToCheck = DB::query('SHOW tables');
        $array = [];
        $completed = [];
        foreach ($tablesToCheck as $tableToCheck) {
            $tableToCheck = array_pop($tableToCheck);
            $fieldsToCheck = array_keys($databaseSchema->fieldList($tableToCheck));
            $fieldsToCheck = array_diff($fieldsToCheck, ['ID']);
            $array[$tableToCheck] = $fieldsToCheck;
        }

        $testArray1 = $array;
        $testArray2 = $array;
        $link = [];
        foreach ($testArray1 as $testTable1 => $testFields1) {
            foreach ($testArray2 as $testTable2 => $testFields2) {
                $parentArray1 = class_exists($testTable1) ? class_parents($testTable1) : ['MATCH'];
                $parentArray2 = class_exists($testTable2) ? class_parents($testTable2) : ['MATCH'];
                if (in_array($testTable2, $parentArray1, true) || in_array($testTable1, $parentArray2, true)) {
                    $interSect = array_intersect($testFields1, $testFields2);
                    if ($interSect !== []) {
                        if ((
                            isset($completed[$testTable1 . '_' . $testTable2]) ||
                                isset($completed[$testTable2 . '_' . $testTable1])
                        )
                            && (
                                (isset($completed[$testTable1 . '_' . $testTable2]) ? count($completed[$testTable1 . '_' . $testTable2]) : random_int(0, 9999999)) === count($interSect) ||
                                (isset($completed[$testTable2 . '_' . $testTable1]) ? count($completed[$testTable2 . '_' . $testTable1]) : random_int(0, 9999999)) === count($interSect)
                            )
                        ) {
                            //do nothing
                        } else {
                            $completed[$testTable1 . '_' . $testTable2] = $interSect;

                            $link['movetoparent'] = [];
                            if (in_array(DataObject::class, $parentArray1, true)) {
                                $modelFields1 = array_keys((array) Config::inst()->get($testTable1, 'db', Config::UNINHERITED)) +
                                $hasOneArray = array_keys((array) Config::inst()->get($testTable1, 'has_one', Config::UNINHERITED));
                                $hasOneArray = array_map(
                                    fn($val) => $val . 'ID',
                                    $hasOneArray
                                );
                                foreach ($interSect as $moveableField) {
                                    if (in_array($moveableField, $modelFields1, true)) {
                                        $link['movetoparent'][$moveableField] = sprintf('move from %s into %s: sake tasks:' . static::$commandName . ' --oldtable=%s --newtable=%s --field=%s', $testTable2, $testTable1, $testTable2, $testTable1, $moveableField);
                                    }
                                }
                            }

                            $link['movetochild'] = [];
                            if (in_array(DataObject::class, $parentArray1, true)) {
                                $modelFields2 = array_keys((array) Config::inst()->get($testTable2, 'db', Config::UNINHERITED)) + array_keys((array) Config::inst()->get($testTable2, 'has_one', Config::UNINHERITED));
                                $hasOneArray = array_keys((array) Config::inst()->get($testTable2, 'has_one', Config::UNINHERITED));
                                $hasOneArray = array_map(
                                    fn($val) => $val . 'ID',
                                    $hasOneArray
                                );
                                foreach ($interSect as $moveableField) {
                                    if (in_array($moveableField, $modelFields2, true)) {
                                        $link['movetochild'][$moveableField] = sprintf('move from %s into %s: sake tasks:' . static::$commandName . ' --oldtable=%s --newtable=%s --field=%s', $testTable1, $testTable2, $testTable1, $testTable2, $moveableField);
                                    }
                                }
                            }

                            $str = sprintf('%s <> %s', $testTable1, $testTable2) . PHP_EOL;
                            foreach ($interSect as $moveableField) {
                                $str .= sprintf('  %s:', $moveableField);

                                if (isset($link['movetoparent'][$moveableField])) {
                                    $str .= ' ' . $link['movetoparent'][$moveableField];
                                }

                                if (isset($link['movetochild'][$moveableField])) {
                                    $str .= ' | ' . $link['movetochild'][$moveableField];
                                }

                                $str .= PHP_EOL;
                            }

                            $output->writeln($str);
                        }
                    }
                }
            }
        }

        $output->writeln('======================== THE END ======================');
        return Command::SUCCESS;
    }

    /**
     * @return string
     */
    protected function Link()
    {
        return '/dev/tasks/' . static::$commandName . '/';
    }

    /**
     * @param string $table
     * @param string $field
     *
     * @return boolean
     */
    private function deleteField($table, $field)
    {
        $databaseSchema = DB::get_schema();
        $fields = array_keys($databaseSchema->fieldList($table));
        if (! DB::query("SHOW TABLES LIKE '" . $table . "'")->value()) {
            $this->polyOutput->writeln(sprintf('tried to delete %s.%s but TABLE does not exist', $table, $field));
            return false;
        }

        if (! class_exists($table)) {
            $this->polyOutput->writeln(sprintf('tried to delete %s.%s but CLASS does not exist', $table, $field));
            return false;
        }

        if (! in_array($field, $fields, true)) {
            $this->polyOutput->writeln(sprintf('tried to delete %s.%s but FIELD does not exist', $table, $field));
            return false;
        }

        $this->polyOutput->writeln(sprintf('Deleting %s in %s', $field, $table));
        DB::query('ALTER TABLE "' . $table . '" DROP "' . $field . '";');
        $obj = singleton($table);
        //to do: make this more reliable - checking for versioning rather than SiteTree
        if ($obj instanceof SiteTree) {
            DB::query('ALTER TABLE "' . $table . '_Live" DROP "' . $field . '";');
            $this->polyOutput->writeln(sprintf('Deleted %s in %s_Live', $field, $table));
            DB::query('ALTER TABLE "' . $table . 'Versions" DROP "' . $field . '";');
            $this->polyOutput->writeln(sprintf('Deleted %s in %s_Versions', $field, $table));
        }

        return true;
    }
}
