<?php

namespace Geolid\Tests\Daemon\Bridge\Symfony\Command;

use Geolid\Daemon\Bridge\Symfony\Command\AbstractDaemonCommand;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

class DaemonCommandConcrete extends AbstractDaemonCommand
{
    public function __construct($isUnique = false)
    {
        parent::__construct();
        $this->setUnique($isUnique);
    }

    protected function configure()
    {
        $this->setName('test:daemon');
        $this->getDaemon()
            ->setLoopInterval(500000) // 0.5s
            ->setTtl(1)
            ->setMemoryThreshold('10M')
        ;
    }

    protected function setup(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('starting');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('hello');
    }

    protected function onShutdown(InputInterface $input, OutputInterface $output): ?int
    {
        $output->writeln('stopped');
        return parent::onShutdown($input, $output);
    }
}
