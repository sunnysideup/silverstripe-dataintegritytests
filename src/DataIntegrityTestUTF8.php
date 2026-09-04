<?php

namespace Sunnysideup\DataIntegrityTest;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\Connect\MySQLDatabase;
use SilverStripe\ORM\DB;

/**
 * Silverstripe 4/5 version.
 *
 * Converts every table in the current (MySQL/MariaDB) database to a UTF-8
 * charset/collation and cleans up the classic "mojibake" byte sequences that
 * appear when UTF-8 text was previously mis-decoded as Latin-1.
 *
 * Safer than a naive version:
 *  - dry-run=1 previews every change without writing anything
 *  - only *text* columns are touched (no more @-suppressed errors on int/date columns)
 *  - find/replace values are passed as bound parameters (no SQL string building)
 *  - aborts if the DB charset/collation config is not at the expected defaults
 *    (override with force=1)
 *
 * Usage (browser or CLI):
 *   dev/tasks/dataintegritytestutf8?dry-run=1
 *   sake dev/tasks/dataintegritytestutf8 "dry-run=1"
 *   sake dev/tasks/dataintegritytestutf8 "table=SiteTree"
 *   sake dev/tasks/dataintegritytestutf8 "force=1"
 *
 * CAREFUL: without dry-run this rewrites data across ALL tables. Back up first.
 */
class DataIntegrityTestUTF8 extends BuildTask
{
    /**
     * standard SS variable
     * @var string
     */
    protected $title = 'Convert tables to UTF-8 and replace mojibake characters.';

    /**
     * standard SS variable
     * @var string
     */
    protected $description = '
        Converts every table to UTF-8 and replaces broken characters left over from a bad encoding migration.
        Use dry-run=1 first. CAREFUL: without dry-run this rewrites all tables!';

    /**
     * URL segment for /dev/tasks and the sake task name.
     * @var string
     */
    private static $segment = 'dataintegritytestutf8';

    /**
     * @var bool
     */
    private static $enabled = true;

    /** The charset/collation this task is designed around. */
    private const DEFAULT_CHARSET = 'utf8mb4';
    private const DEFAULT_COLLATION = 'utf8mb4_unicode_ci';

    /** Base column types we run REPLACE against (everything else is skipped). */
    private const TEXT_COLUMN_TYPES = [
        'char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext', 'enum', 'set',
    ];

    /**
     * find => replace map. Overridable via YAML config.
     * Each KEY is a broken byte sequence; each VALUE is the correct output
     * (an HTML entity, or '' to simply delete it).
     */
    private static $replacement_array = [
        'Â' => '',
        'â€™' => '&#39;',    // curly apostrophe
        'Ââ€“' => '&mdash;', // em dash
        'â€¨' => '',         // line separator U+2028
        'â€œ' => '&quot;',   // opening curly quote
        'â€^Ý' => '&quot;',  // closing curly quote
        'â€¢' => '&#8226;',  // bullet (fixed: trailing ";" was missing)
        'Ý' => '- ',
    ];

    /**
     * @param HTTPRequest $request  (untyped to stay compatible with BuildTask::run)
     */
    public function run($request)
    {
        ini_set('max_execution_time', 3000);

        $dryRun    = (bool) $request->getVar('dry-run');
        $force     = (bool) $request->getVar('force');
        $onlyTable = $request->getVar('table') ?: null;

        // This task is MySQL/MariaDB specific (charset conversion + REPLACE()).
        $conn = DB::get_conn();
        if (!($conn instanceof MySQLDatabase)) {
            DB::alteration_message('This task only supports MySQL / MariaDB.', 'error');
            $this->flushNow();
            return;
        }

        // 1) Verify DB settings are at their expected defaults; abort unless force=1.
        if (!$this->settingsAreDefault() && !$force) {
            DB::alteration_message('Aborting: DB settings are not at their defaults. Re-run with force=1 to proceed anyway.', 'error');
            $this->flushNow();
            return;
        }

        // Target charset/collation = configured value, falling back to the defaults.
        $charset   = Config::inst()->get(MySQLDatabase::class, 'connection_charset') ?: self::DEFAULT_CHARSET;
        $collation = Config::inst()->get(MySQLDatabase::class, 'connection_collation') ?: self::DEFAULT_COLLATION;

        $replacements = (array) Config::inst()->get(self::class, 'replacement_array');
        $databaseName = $conn->getSelectedDatabase();
        $tables       = $this->getTables($onlyTable);

        if (!$tables) {
            DB::alteration_message('No matching tables found.', 'notice');
            $this->flushNow();
            return;
        }

        if ($dryRun) {
            DB::alteration_message('DRY RUN - no data will be modified.', 'notice');
            $this->flushNow();
        }

        $grandTotal = 0;

        foreach ($tables as $table) {
            $grandTotal += $this->processTable($table, $databaseName, $charset, $collation, $replacements, $dryRun);
            $this->flushNow();
        }

        DB::alteration_message(sprintf(
            '%s %d replacement(s) across %d table(s).',
            $dryRun ? 'DRY RUN would make' : 'Made',
            $grandTotal,
            count($tables)
        ), 'changed');
        DB::alteration_message('COMPLETED');
        $this->flushNow();
    }

    /**
     * Returns true when the configured charset/collation match the defaults.
     * Only an *explicitly overridden* value counts as a mismatch - a value that
     * was never set is fine because the fallback lands on the default anyway.
     */
    private function settingsAreDefault(): bool
    {
        $ok        = true;
        $charset   = Config::inst()->get(MySQLDatabase::class, 'connection_charset');
        $collation = Config::inst()->get(MySQLDatabase::class, 'connection_collation');

        if ($charset && $charset !== self::DEFAULT_CHARSET) {
            $ok = false;
            DB::alteration_message(sprintf('SETTINGS: MySQLDatabase.connection_charset is "%s", expected "%s".', $charset, self::DEFAULT_CHARSET), 'error');
        }
        if ($collation && $collation !== self::DEFAULT_COLLATION) {
            $ok = false;
            DB::alteration_message(sprintf('SETTINGS: MySQLDatabase.connection_collation is "%s", expected "%s".', $collation, self::DEFAULT_COLLATION), 'error');
        }

        return $ok;
    }

    /**
     * @return string[] list of table names
     */
    private function getTables(?string $onlyTable): array
    {
        $tables = [];
        foreach (DB::query('SHOW TABLES') as $row) {
            $tables[] = (string) array_pop($row);
        }

        if ($onlyTable !== null) {
            $tables = in_array($onlyTable, $tables, true) ? [$onlyTable] : [];
        }

        return $tables;
    }

    /**
     * Convert one table and run the replacements over its text columns.
     * Returns the number of replacements made (or that would be made in dry-run).
     */
    private function processTable(
        string $table,
        string $databaseName,
        string $charset,
        string $collation,
        array $replacements,
        bool $dryRun
    ): int {
        $qTable           = $this->quote($table);
        $currentCollation = (string) DB::prepared_query(
            'SELECT TABLE_COLLATION FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = ? AND TABLE_SCHEMA = ?',
            [$table, $databaseName]
        )->value();

        DB::alteration_message(sprintf(
            '%s  (current: %s  ->  %s / %s)',
            $table,
            $currentCollation ?: 'unknown',
            $charset,
            $collation
        ));

        // Convert the table only if it isn't already at the target collation.
        if ($currentCollation !== $collation) {
            if ($dryRun) {
                DB::alteration_message(sprintf('  would convert to %s %s', $charset, $collation), 'changed');
            } else {
                DB::query(sprintf('ALTER TABLE %s CONVERT TO CHARACTER SET %s COLLATE %s', $qTable, $charset, $collation));
            }
        }

        $tableTotal = 0;

        foreach (DB::query('SHOW FULL COLUMNS FROM ' . $qTable) as $row) {
            $field          = (string) $row['Field'];
            $type           = (string) ($row['Type'] ?? '');
            $fieldCollation = (string) ($row['Collation'] ?? '');

            if (!$this->isTextColumn($type)) {
                continue; // skip int/date/blob/etc. entirely
            }

            if ($fieldCollation && $fieldCollation !== $collation) {
                DB::alteration_message(sprintf('  %s: collation is %s', $field, $fieldCollation), 'error');
            }

            $qField = $this->quote($field);

            foreach ($replacements as $from => $to) {
                $count = $dryRun
                    ? $this->countMatches($qTable, $qField, (string) $from)
                    : $this->applyReplacement($qTable, $qField, (string) $from, (string) $to);

                if ($count > 0) {
                    $tableTotal += $count;
                    DB::alteration_message(sprintf(
                        '  %s.%s: %d x "%s" -> "%s"',
                        $table,
                        $field,
                        $count,
                        $from,
                        $to === '' ? '[removed]' : $to
                    ), 'changed');
                }
            }
        }

        return $tableTotal;
    }

    /** Run the actual UPDATE ... REPLACE() and return rows affected. */
    private function applyReplacement(string $qTable, string $qField, string $from, string $to): int
    {
        DB::prepared_query(
            sprintf('UPDATE %s SET %s = REPLACE(%s, ?, ?)', $qTable, $qField, $qField),
            [$from, $to]
        );

        return (int) DB::get_conn()->affectedRows();
    }

    /** Count rows containing the search string (used for dry-run previews). */
    private function countMatches(string $qTable, string $qField, string $from): int
    {
        return (int) DB::prepared_query(
            sprintf('SELECT COUNT(*) FROM %s WHERE LOCATE(?, %s) > 0', $qTable, $qField),
            [$from]
        )->value();
    }

    /** True for char/text/enum/set style columns. */
    private function isTextColumn(string $type): bool
    {
        $base = strtolower((string) preg_replace('/\(.*$/', '', trim($type)));

        return in_array($base, self::TEXT_COLUMN_TYPES, true);
    }

    /** Quote a MySQL identifier, escaping any embedded backticks. */
    private function quote(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /** Force buffered output to the browser so progress shows in real time. */
    private function flushNow(): void
    {
        if (ob_get_length()) {
            @ob_flush();
        }
        @flush();
    }
}
