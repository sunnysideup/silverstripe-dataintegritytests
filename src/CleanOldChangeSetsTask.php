<?php

declare(strict_types=1);

namespace Sunnysideup\DataIntegrityTest;

use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use SilverStripe\Versioned\ChangeSet;
use SilverStripe\Versioned\ChangeSetItem;
use SilverStripe\Core\Convert;
use SilverStripe\Control\HTTPRequest;
use DateTimeImmutable;
use DateInterval;
use SilverStripe\Control\Director;

final class CleanOldChangeSetsTask extends BuildTask
{
    protected $title = 'Clean Old ChangeSets and ChangeSetItems';
    protected $description = 'Deletes ChangeSets and ChangeSetItems older than X days, or shows monthly stats if not run with ?forreal=1';
    private static int $monthsToShow = 240;

    private static $segment = 'cleanoldchangesetstask';

    public function run($request): void
    {
        $days = (int)($request->getVar('days') ?? 90);
        $forReal = (bool)$request->getVar('forreal');

        if (!$forReal) {
            $this->showStats();
            return;
        }

        $this->deleteOldRecords($days);
    }

    private function deleteOldRecords(int $days): void
    {
        $cutoffDate = (new DateTimeImmutable())
            ->sub(new DateInterval('P' . $days . 'D'))
            ->format('Y-m-d H:i:s');

        $safeCutoff = Convert::raw2sql($cutoffDate);

        $itemCount = ChangeSetItem::get()
            ->filter('LastEdited:LessThan', $cutoffDate)
            ->count();

        $setCount = ChangeSet::get()
            ->filter('LastEdited:LessThan', $cutoffDate)
            ->count();

        $this->printHelper("Deleting {$itemCount} ChangeSetItems and {$setCount} ChangeSets older than {$days} days...");

        if ($itemCount > 0) {
            DB::query(
                'DELETE FROM "ChangeSetItem" WHERE "Created" < \'' . $safeCutoff . '\''
            );
        }

        if ($setCount > 0) {
            DB::query(
                'DELETE FROM "ChangeSet" WHERE "Created" < \'' . $safeCutoff . '\''
            );
        }

        $this->printHelper("Done.");
    }

    private function showStats(): void
    {
        $this->printHelper("Showing ChangeSet and ChangeSetItem creation stats (last " . self::$monthsToShow . " months)");

        foreach (['ChangeSet', 'ChangeSetItem'] as $table) {
            $this->printHelper(strtoupper($table) . ":");

            $sql = '
                SELECT
                    DATE_FORMAT("Created", \'%Y-%m\') AS Month,
                    COUNT(*) AS Count
                FROM "' . $table . '"
                WHERE "Created" > DATE_SUB(NOW(), INTERVAL ' . self::$monthsToShow . ' MONTH)
                GROUP BY Month
                ORDER BY Month ASC
            ';

            $rows = DB::query($sql);
            $max = 0;
            $data = [];

            foreach ($rows as $row) {
                $data[$row['Month']] = (int)$row['Count'];
                $max = max($max, (int)$row['Count']);
            }

            foreach ($data as $month => $count) {
                $bar = str_repeat('█', (int)(50 * $count / max(1, $max)));
                $this->printHelper("{$month}: {$bar} " . number_format($count) . "");
            }

            $this->printHelper("");
        }
        $add = Director::is_cli() ? "forreal=1 days=90" : '?forreal=1&days=90';
        $this->printHelper("ADD: {$add} to actually delete records older than 90 days.");
    }

    private function printHelper(string $message): void
    {
        if (Director::is_cli()) {
            echo $message . "\n";
        } else {
            echo '<p style="font-family: monospace;">' . htmlspecialchars($message) . '</p>';
        }
    }
}
