<?php

namespace Sunnysideup\DataIntegrityTest;

// Symfony's base Command class — BuildTask now runs on top of Symfony Console,
// which is why we return Command::SUCCESS / Command::FAILURE at the end.
use Symfony\Component\Console\Command\Command;
// Represents the "input" side of a console command (arguments, options, flags).
// We don't actually read any input here, but the execute() signature requires it.
use Symfony\Component\Console\Input\InputInterface;
// Silverstripe 6's output abstraction. It can render the same output either to
// an ANSI terminal (CLI) or as HTML (when the task is triggered in the browser).
// That's what writeForHtml() targets: the browser view of /dev/tasks.
use SilverStripe\PolyExecution\PolyOutput;
// The Config layer — how we read YAML / private static config values at runtime.
use SilverStripe\Core\Config\Config;
// The base class for all build/dev tasks in Silverstripe 6.
use SilverStripe\Dev\BuildTask;
// The MySQL database driver class. We read its *config* (charset/collation),
// we don't instantiate it directly.
use SilverStripe\ORM\Connect\MySQLDatabase;
// The main DB facade: DB::query(), DB::get_conn(), etc.
use SilverStripe\ORM\DB;

class DataIntegrityTestUTF8 extends BuildTask
{
    /**
     * The human-readable title shown in the /dev/tasks list.
     * NOTE: this is a non-static instance property, whereas $description and
     * $commandName below are `static`. Minor inconsistency — harmless, but worth
     * knowing if you ever copy this pattern.
     *
     * @var string
     */
    protected string $title = 'Convert tables to utf-8 and replace funny characters.';

    /**
     * Longer description shown under the title in /dev/tasks.
     * The "CAREFUL" wording is deliberate: this task rewrites EVERY table.
     *
     * @var string
     */
    protected static string $description = '
        Converts table to utf-8 by replacing a bunch of characters that show up in the Silverstripe Conversion.
        CAREFUL: replaces all tables in Database to utf-8!';

    /**
     * The find => replace map applied to every column of every table.
     *
     * Each KEY is the broken "mojibake" byte sequence that appears when UTF-8
     * text was mis-decoded as Latin-1; each VALUE is what it should become
     * (usually an HTML entity, or an empty string to just delete it).
     *
     * Because this is a `private static`, it is overridable via YAML config,
     * which is exactly how it's read below (Config::inst()->get(...)).
     *
     * ⚠ HEADS-UP: the first active line and the two commented lines below it all
     * read as 'Â' here. They were almost certainly DIFFERENT multibyte characters
     * in the original source, but got flattened to identical text when pasted.
     * If you rely on those two, re-enter them from a known-good hex source.
     */
    private static $replacement_array = [
        'Â' => '',
        // 'Â' => '',   // <-- was probably a different char originally (see note above)
        // 'Â' => '',   // <-- same
        'â€™' => '&#39;',   // curly apostrophe  ’  -> '
        'Ââ€"' => '&mdash;', // em dash          —  -> &mdash;
        'â€¨' => '',         // Unicode line separator U+2028 -> delete
        'â€œ' => '&quot;',   // opening curly quote “ -> "
        'â€^Ý' => '&quot;',  // closing curly quote ” -> "
        'â€¢' => '&#8226',   // bullet • -> &#8226 (NOTE: missing trailing ";")
        'Ý' => '- ',         // stray Ý -> "- "
    ];

    /**
     * The command name you type to run this from the CLI:
     *   vendor/bin/sake tasks:dataintegritytestutf8
     * (or via the browser at /dev/tasks/dataintegritytestutf8)
     */
    protected static string $commandName = 'dataintegritytestutf8';

    /**
     * The entry point. Silverstripe 6 calls this with a Symfony Input and a
     * PolyOutput, and expects an int return (Command::SUCCESS = 0).
     */
    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        // Give the script plenty of time — rewriting every table can be slow.
        ini_set('max_execution_time', 3000);

        // Grab the list of all tables in the current database.
        // Returns rows like ['Tables_in_mydb' => 'SiteTree'].
        $tables = DB::query('SHOW tables');

        // Read the replacement map from config (allows YAML overrides of the
        // private static above).
        $arrayOfReplacements = Config::inst()->get(DataIntegrityTestUTF8::class, 'replacement_array');

        // Read the target charset/collation from the MySQLDatabase config.
        // The `?: 'utf8mb4...'` part means: if nothing is configured, fall back
        // to these hard-coded defaults.
        $connCharset = Config::inst()->get(MySQLDatabase::class, 'connection_charset') ?: 'utf8mb4';
        $connCollation = Config::inst()->get(MySQLDatabase::class, 'connection_collation') ?: 'utf8mb4_unicode_ci';

        // =================================================================
        // NEW: "error if settings are not at the default" check.
        // -----------------------------------------------------------------
        // The whole task assumes utf8mb4 / utf8mb4_unicode_ci. If someone has
        // OVERRIDDEN these in YAML to something else, converting every table
        // blindly could give you an unexpected charset — so we surface a clear,
        // visible error before doing any damage.
        //
        // Interpretation used here: an error is only shown when the value is
        // EXPLICITLY SET to something other than the default. "Not set at all"
        // is treated as fine, because the `?:` fallback already lands on the
        // default in that case. If you'd rather require the value to be
        // explicitly present AND equal to the default, drop the leading
        // "$configured &&" guard in each check.
        // =================================================================
        $defaultCharset   = 'utf8mb4';
        $defaultCollation = 'utf8mb4_unicode_ci';

        // Read the RAW configured values (null/'' when never set in YAML).
        $configuredCharset   = Config::inst()->get(MySQLDatabase::class, 'connection_charset');
        $configuredCollation = Config::inst()->get(MySQLDatabase::class, 'connection_collation');

        $settingsError = false;

        if ($configuredCharset && $configuredCharset !== $defaultCharset) {
            $settingsError = true;
            $output->writeForHtml(sprintf(
                '<strong style="color:#b00">SETTINGS ERROR: MySQLDatabase.connection_charset is "%s" but the expected default is "%s".</strong>',
                $configuredCharset,
                $defaultCharset
            ));
        }

        if ($configuredCollation && $configuredCollation !== $defaultCollation) {
            $settingsError = true;
            $output->writeForHtml(sprintf(
                '<strong style="color:#b00">SETTINGS ERROR: MySQLDatabase.connection_collation is "%s" but the expected default is "%s".</strong>',
                $configuredCollation,
                $defaultCollation
            ));
        }

        // OPTIONAL HARD STOP — because this task is destructive, you probably
        // want to bail out rather than continue when settings are wrong.
        // Uncomment these lines to abort before touching any tables:
        //
        // if ($settingsError) {
        //     $output->writeForHtml('<hr />ABORTED: settings are not at their defaults.<hr />');
        //     return Command::FAILURE;
        // }
        // =================================================================

        // The live DB connection object.
        $conn = DB::get_conn();

        // Name of the database we're connected to (needed for the
        // INFORMATION_SCHEMA lookup below, which is scoped per-schema).
        // Assumes database class is like "MySQLDatabase" or "MSSQLDatabase" (suffixed with "Database")
        $databaseName = $conn->getSelectedDatabase();

        // ---- Loop over every table ------------------------------------
        foreach ($tables as $table) {
            // Each $table is an assoc array like ['Tables_in_mydb' => 'SiteTree'];
            // array_pop() grabs the value regardless of the column key name.
            $table = array_pop($table);

            // Look up the table's CURRENT collation, for reporting only.
            // NOTE: this concatenates $table straight into SQL. It's low risk
            // because the value came from SHOW TABLES (trusted), but a bound
            // parameter would be safer as a habit.
            $currentCollation = DB::query('
                SELECT TABLE_COLLATION
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_NAME = \'' . $table . "' AND table_schema = '" . $databaseName . "';")->value();

            // Report what we're about to do to this table.
            $output->writeForHtml('<strong>Resetting "' . $table . '" table to "' . $connCharset . '", collation "' . $connCollation . '", with current collation: "' . $currentCollation . '"</strong>');

            // STEP 1: convert the whole table (and all its text columns) to the
            // target charset/collation. The double quotes around "table" are
            // Silverstripe's ANSI-style identifier quoting; the DB layer
            // rewrites them to MySQL backticks automatically.
            DB::query('ALTER TABLE "' . $table . '" CONVERT TO CHARACTER SET ' . $connCharset . ' COLLATE ' . $connCollation);

            // Get full metadata for each column (Field, Type, Collation, etc.).
            $rows = DB::query('SHOW FULL COLUMNS FROM "' . $table . '"');

            // ---- Loop over every column of this table -----------------
            foreach ($rows as $row) {
                $fieldName = $row['Field'];               // column name
                $fieldCollation = $row['Collation'] ?? ''; // '' for non-text columns

                // Sanity check: after the ALTER above, every text column SHOULD
                // now match $connCollation. If not, report it (doesn't fix it).
                if ($fieldCollation && $fieldCollation !== $connCollation) {
                    $output->writeForHtml('Error in ' . $fieldName . ' collation: ' . $fieldCollation);
                }

                // Buffer of human-readable messages for this column. We seed it
                // with a "CHECKING..." line so we can later tell whether any
                // actual replacements happened (count > 1 means yes).
                $usedFieldsChanged = [sprintf('CHECKING %s.%s : ', $table, $fieldName)];

                // STEP 2: run each find/replace against this column.
                foreach ($arrayOfReplacements as $from => $to) {
                    // The leading @ suppresses errors: running REPLACE() on a
                    // non-text column (int/date/etc.) can throw, and we simply
                    // want to skip those quietly rather than abort the task.
                    @DB::query(sprintf("UPDATE \"%s\" SET \"%s\" = REPLACE(\"%s\", '%s', '%s');", $table, $fieldName, $fieldName, $from, $to));

                    // How many rows the UPDATE actually changed.
                    $count = DB::get_conn()->affectedRows();

                    // Prettify the "to" side for the log when it's an empty string.
                    $toWord = $to;
                    if ($to === '') {
                        $toWord = '[NOTHING]';
                    }

                    // Only log lines where something was actually replaced.
                    if ($count) {
                        $usedFieldsChanged[] = sprintf('%s Replacements <strong>%s</strong> with <strong>%s</strong>', $count, $from, $toWord);
                    }
                }

                // If the buffer grew beyond its seed line, print the report
                // for this column (one line per replacement performed).
                if (count($usedFieldsChanged) > 1) {
                    $output->writeForHtml(implode('<br /> &nbsp;&nbsp;&nbsp;&nbsp; - ', $usedFieldsChanged));
                }
            }
        }

        // Done.
        $output->writeForHtml('<hr />COMPLETED<hr />');
        return Command::SUCCESS;
    }
}
