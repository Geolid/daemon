<?php

namespace Geolid\Tests\Daemon\Bridge\Symfony\Command;

use Geolid\Daemon\Bridge\Symfony\Command\AbstractDaemonCommand;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

class DaemonCommandConcreteMaxMemory extends AbstractDaemonCommand
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
