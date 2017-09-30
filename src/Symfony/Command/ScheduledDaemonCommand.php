<?php

namespace Geolid\Daemon\Symfony\Command;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\Table;
use Geolid\Daemon\Daemon;
use Geolid\Daemon\ScheduledDaemon;

abstract class ScheduledDaemonCommand extends DaemonCommand
{
    /**
     * {@inheritdoc}
     */
    protected function createDaemon(): Daemon
    {
        return new ScheduledDaemon;
    }

    /**
     * {@inheritdoc}
     */
    protected function configureDaemonDefinition()
    {
        parent::configureDaemonDefinition();

        $this->addOption(
            '--display-schedule',
            null,
            InputOption::VALUE_NONE,
            'Display the schedule'
        );
    }

    public function daemon(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('display-schedule')) {
            $this->displaySchedule($output);
            return;
        }

        return parent::daemon($input, $output);
    }

    protected function displaySchedule(OutputInterface $output): void
    {
        $table = new Table($output);
        $table->setHeaders(['Cron expression', 'Previous date', 'Next date']);

        foreach ($this->getDaemon()->getSchedules() as $cron) {
            $table->addRow([
                $cron->getExpression(),
                $cron->getPreviousRunDate()->format('c'),
                $cron->getNextRunDate()->format('c'),
            ]);
        }

        $table->render();
    }
}
