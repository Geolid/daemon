<?php

namespace Geolid\Daemon\Symfony\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Geolid\Daemon\Daemon;

abstract class DaemonCommand extends Command
{
    /**
     * @var Daemon
     */
    protected $daemon;

    public function __construct($name = null)
    {
        $this->daemon = $this->createDaemon();

        // Construct parent context (also calls configure)
        parent::__construct($name);
        $this->configureDaemonDefinition();

        // Set our runloop as the executable code
        $this->setCode([$this, 'daemon']);
    }

    protected function createDaemon(): Daemon
    {
        return new Daemon;
    }

    public function getDaemon(): Daemon
    {
        return $this->daemon;
    }

    /**
     * Add daemon options to the command definition
     */
    protected function configureDaemonDefinition()
    {
        $this->addOption(
            'ttl',
            null,
            InputOption::VALUE_REQUIRED,
            'Set the command a time to live in seconds.',
            $this->daemon->getTtl()
        );

        $this->addOption(
            'memory-max',
            null,
            InputOption::VALUE_REQUIRED,
            'Gracefully stop running command when given memory volume is reached, Ex: 500M.',
            $this->daemon->getMemoryThreshold()
        );

        $this->addOption(
            'no-daemon',
            null,
            InputOption::VALUE_NONE,
            'Run the command one time as a classic command'
        );
    }

    protected function setup(InputInterface $input, OutputInterface $output)
    {

    }

    protected function onShutdown(InputInterface $input, OutputInterface $output)
    {

    }

    /**
     * What happen when an exception is thrown inside the loop?
     */
    protected function onException(\Exception $e, InputInterface $input, OutputInterface $output)
    {
        throw $e;
    }

    public function daemon(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('no-daemon')) {
            $this->setup($input, $output);

            try {
                $this->execute($input, $output);
            } catch (\Exception $e) {
                $this->onException($e, $input, $output);
            }

            return $this->onShutdown($input, $output);
        }

        $exceptionCallback = function (\Exception $e) use ($input, $output) {
            $this->onException($e, $input, $output);
        };

        $callback = function () use ($input, $output) {
            $this->execute($input, $output);
        };

        $this->daemon
            ->setTtl($input->getOption('ttl') ?: $this->daemon->getTtl())
            ->setMemoryThreshold($input->getOption('memory-max') ?: $this->daemon->getMemoryThreshold())
            ->setCallback($callback)
            ->setExceptionCallback($exceptionCallback)
        ;

        $this->setup($input, $output);

        $this->daemon->run();

        return $this->onShutdown($input, $output);
    }
}
