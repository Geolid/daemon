<?php

namespace Geolid\Tests\Daemon\Symfony\Command;

use Geolid\Daemon\Symfony\Command\DaemonCommand;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

class DaemonCommandConcrete extends DaemonCommand
{
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

    protected function onShutdown(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('stopped');
        return $this->getDaemon()->getShutdownCode();
    }
}
