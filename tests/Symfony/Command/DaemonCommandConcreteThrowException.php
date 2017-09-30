<?php

namespace Geolid\Tests\Daemon\Symfony\Command;

use Geolid\Daemon\Symfony\Command\DaemonCommand;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

class DaemonCommandConcreteThrowException extends DaemonCommand
{
    protected function configure()
    {
        $this->setName('test:daemon');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        throw new \Exception('exception message');
    }
}
