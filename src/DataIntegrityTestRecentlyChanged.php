<?php

namespace Sunnysideup\DataIntegrityTest;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use SilverStripe\PolyExecution\PolyOutput;
use DateTime;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class DataIntegrityTestRecentlyChanged extends BuildTask
{
    /**
     * standard SS variable
     * @var string
     */
    protected string $title = 'Check what records have been changed in the last xxx minutes';

    /**
     * standard SS variable
     * @var string
     */
    protected static string $description = 'Go through all tables in the database and see what records have been edited in the last xxx minutes. Set the time using --minutes (or a date/time string).';

    protected static string $commandName = 'DataIntegrityTestRecentlyChanged';

    public function getOptions(): array
    {
        return [
            new InputOption('minutes', 'm', InputOption::VALUE_REQUIRED, 'Number of minutes ago, or a date string (e.g. "yesterday", "2024-01-01")', ''),
        ];
    }

    /**
     * runs the task and outputs directly to the screen
     */
    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $output->writeForHtml('<style>table {width: 100%;} th, td {padding: 5px; font-size: 12px; border: 1px solid #ccc; vertical-align: top;}</style>');

        $mParam = (string) ($input->getOption('minutes') ?? '');
        $minutes = 0;

        if ($mParam !== '') {
            $asInt = intval($mParam);
            if ((string) $asInt === $mParam) {
                $minutes = $asInt;
            } else {
                $tsFrom = strtotime($mParam);
                if ($tsFrom) {
                    $tsUntil = strtotime('NOW');
                    $minutes = (int) round(($tsUntil - $tsFrom) / 60);
                }
            }
        }

        if ($minutes) {
            $ts = strtotime($minutes . ' minutes ago');
            $date = date(DATE_RFC2822, $ts);
            $output->writeForHtml('<hr /><h3>changes in the last ' . $this->minutesToTime($minutes) . '<br />from: ' . $date . '<br />make sure you see THE END at the bottom of this list</h3><hr />');
            $whereStatementFixed = 'UNIX_TIMESTAMP("LastEdited") > ' . $ts . ' ';
            $dataClasses = ClassInfo::subclassesFor(DataObject::class);
            array_shift($dataClasses);
            foreach ($dataClasses as $dataClass) {
                if (class_exists($dataClass)) {
                    $singleton = Injector::inst()->get($dataClass);
                    if ($singleton instanceof TestOnly) {
                        //do nothing
                    } else {
                        $whereStatement = $whereStatementFixed . " AND \"ClassName\" = '" . $dataClass . "' ";
                        $count = $dataClass::get()->where($whereStatement)->count();
                        $fields = Config::inst()->get($dataClass, 'db');
                        if (! is_array($fields)) {
                            $fields = [];
                        }

                        $fields = ['ID' => 'Int', 'Created' => 'SS_DateAndTime', 'LastEdited' => 'SS_DateAndTime'] + $fields;
                        if ($count) {
                            $output->writeForHtml('<h2>' . $singleton->singular_name() . '(' . $count . ')</h2>');
                            $objects = $dataClass::get()->where($whereStatement)->limit(1000);
                            foreach ($objects as $object) {
                                $output->writeForHtml('<h4>' . $object->getTitle() . '</h4><ul>');
                                if ($fields !== []) {
                                    foreach (array_keys($fields) as $field) {
                                        $output->writeForHtml('<li><strong>' . $field . "</strong><pre>\t\t" . htmlentities((string) $object->{$field}) . '</pre></li>');
                                    }
                                }

                                $output->writeForHtml('</ul>');
                            }
                        }
                    }
                }
            }

            $output->writeForHtml('<hr /><h1>-------- THE END --------</h1>');
        } else {
            $output->writeln('No time parameter provided. Use --minutes=60 for the last hour, or --minutes="yesterday" etc.');
            $output->writeln('Example: sake tasks:' . static::$commandName . ' --minutes=60');
        }

        return Command::SUCCESS;
    }

    protected function minutesToTime($minutes)
    {
        $seconds = $minutes * 60;
        $dtF = new DateTime('@0');
        $dtT = new DateTime('@' . $seconds);
        return $dtF->diff($dtT)->format('%a days, %h hours,  and %i minutes');
    }
}
