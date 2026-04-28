<?php

declare(strict_types=1);

namespace Sunnysideup\DataIntegrityTest;

use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use SilverStripe\PolyExecution\PolyOutput;
use DateInterval;
use DateTimeImmutable;
use SilverStripe\Core\Convert;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use SilverStripe\Versioned\ChangeSet;
use SilverStripe\Versioned\ChangeSetItem;

final class CleanOldChangeSetsTask extends BuildTask
{
    protected string $title = 'Clean Old ChangeSets and ChangeSetItems';

    protected static string $description = 'Deletes ChangeSets and ChangeSetItems older than X days, or shows monthly stats if not run with --forreal';

    private static int $monthsToShow = 240;

    protected static string $commandName = 'cleanoldchangesetstask';

    #[Override]
    public function getOptions(): array
    {
        return [
            new InputOption('days', 'd', InputOption::VALUE_REQUIRED, 'Number of days to keep (delete older than this)', 90),
            new InputOption('forreal', 'f', InputOption::VALUE_NONE, 'Actually delete records (default: show stats only)'),
        ];
    }

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $days = (int) ($input->getOption('days') ?? 90);
        $forReal = (bool) $input->getOption('forreal');
        if (! $forReal) {
            $this->showStats($output);
            return Command::SUCCESS;
        }

        $this->deleteOldRecords($days, $output);
        return Command::SUCCESS;
    }

    private function deleteOldRecords(int $days, PolyOutput $output): void
    {
        $cutoffDate = (new DateTimeImmutable())
            ->sub(new DateInterval('P' . $days . 'D'))
            ->format('Y-m-d H:i:s');

        $safeCutoff = Convert::raw2sql($cutoffDate);

        $itemCount = ChangeSetItem::get()->filter(['LastEdited:LessThan' => $cutoffDate])
            ->count();

        $setCount = ChangeSet::get()->filter(['LastEdited:LessThan' => $cutoffDate])
            ->count();

        $output->writeln(sprintf('Deleting %d ChangeSetItems and %d ChangeSets older than %d days...', $itemCount, $setCount, $days));

        if ($itemCount > 0) {
            DB::query(
                'DELETE FROM "ChangeSetItem" WHERE "Created" < \'' . $safeCutoff . "'"
            );
        }

        if ($setCount > 0) {
            DB::query(
                'DELETE FROM "ChangeSet" WHERE "Created" < \'' . $safeCutoff . "'"
            );
        }
    }

    private function showStats(PolyOutput $output): void
    {
        $output->writeln('Showing ChangeSet and ChangeSetItem creation stats (last ' . self::$monthsToShow . ' months)');

        foreach (['ChangeSet', 'ChangeSetItem'] as $table) {
            $output->writeln(strtoupper($table) . ':');

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
                $data[$row['Month']] = (int) $row['Count'];
                $max = max($max, (int) $row['Count']);
            }

            foreach ($data as $month => $count) {
                $bar = str_repeat('█', (int) (50 * $count / max(1, $max)));
                $output->writeln(sprintf('%s: %s ', $month, $bar) . number_format($count));
            }

            $output->writeln('');
        }

        $output->writeln('Run with --forreal --days=90 to actually delete records older than 90 days.');
    }
}
