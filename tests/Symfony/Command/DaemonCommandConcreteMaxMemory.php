<?php

namespace Geolid\Tests\Daemon\Symfony\Command;

use Geolid\Daemon\Symfony\Command\DaemonCommand;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

class DaemonCommandConcreteMaxMemory extends DaemonCommand
{
    /**
     * @var array
     */
    protected $data = [];

    protected function configure()
    {
        $this->setName('test:daemon');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->data[] = str_repeat('foo', 100);
    }
}
