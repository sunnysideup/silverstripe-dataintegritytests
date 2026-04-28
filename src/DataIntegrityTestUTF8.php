<?php

namespace Sunnysideup\DataIntegrityTest;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\Connect\MySQLDatabase;
use SilverStripe\ORM\DB;

class DataIntegrityTestUTF8 extends BuildTask
{
    /**
     * standard SS variable
     * @var string
     */
    protected string $title = 'Convert tables to utf-8 and replace funny characters.';

    /**
     * standard SS variable
     * @var string
     */
    protected static string $description = '
        Converts table to utf-8 by replacing a bunch of characters that show up in the Silverstripe Conversion.
        CAREFUL: replaces all tables in Database to utf-8!';

    private static $replacement_array = [
        'Â' => '',
        // 'Â' => '',
        // 'Â' => '',
        'â€™' => '&#39;',
        'Ââ€"' => '&mdash;',
        'â€¨' => '',
        'â€œ' => '&quot;',
        'â€^Ý' => '&quot;',
        'â€¢' => '&#8226',
        'Ý' => '- ',
    ];

    protected static string $commandName = 'dataintegritytestutf8';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        ini_set('max_execution_time', 3000);
        $tables = DB::query('SHOW tables');
        $arrayOfReplacements = Config::inst()->get(DataIntegrityTestUTF8::class, 'replacement_array');
        $connCharset = Config::inst()->get(MySQLDatabase::class, 'connection_charset') ?: 'utf8mb4';
        $connCollation = Config::inst()->get(MySQLDatabase::class, 'connection_collation') ?: 'utf8mb4_unicode_ci';
        $conn = DB::get_conn();
        // Assumes database class is like "MySQLDatabase" or "MSSQLDatabase" (suffixed with "Database")
        $databaseName = $conn->getSelectedDatabase();
        foreach ($tables as $table) {
            $table = array_pop($table);
            $currentCollation = DB::query('
                SELECT TABLE_COLLATION
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_NAME = \'' . $table . "' AND table_schema = '" . $databaseName . "';")->value();
            $output->writeForHtml('<strong>Resetting "' . $table . '" table to "' . $connCharset . '", collation "' . $connCollation . '", with current collation: "' . $currentCollation . '"</strong>');
            DB::query('ALTER TABLE "' . $table . '" CONVERT TO CHARACTER SET ' . $connCharset . ' COLLATE ' . $connCollation);
            $rows = DB::query('SHOW FULL COLUMNS FROM "' . $table . '"');
            foreach ($rows as $row) {
                $fieldName = $row['Field'];
                $fieldCollation = $row['Collation'] ?? '';
                if ($fieldCollation && $fieldCollation !== $connCollation) {
                    $output->writeForHtml('Error in ' . $fieldName . ' collation: ' . $fieldCollation);
                }

                $usedFieldsChanged = [sprintf('CHECKING %s.%s : ', $table, $fieldName)];
                foreach ($arrayOfReplacements as $from => $to) {
                    @DB::query(sprintf("UPDATE \"%s\" SET \"%s\" = REPLACE(\"%s\", '%s', '%s');", $table, $fieldName, $fieldName, $from, $to));
                    $count = DB::get_conn()->affectedRows();
                    $toWord = $to;
                    if ($to === '') {
                        $toWord = '[NOTHING]';
                    }

                    if ($count) {
                        $usedFieldsChanged[] = sprintf('%s Replacements <strong>%s</strong> with <strong>%s</strong>', $count, $from, $toWord);
                    }
                }

                if (count($usedFieldsChanged) > 1) {
                    $output->writeForHtml(implode('<br /> &nbsp;&nbsp;&nbsp;&nbsp; - ', $usedFieldsChanged));
                }
            }
        }

        $output->writeForHtml('<hr />COMPLETED<hr />');
        return Command::SUCCESS;
    }
}
