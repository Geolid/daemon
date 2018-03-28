<?php

declare(strict_types=1);

namespace Geolid\Daemon\Bridge\Symfony\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\Factory;
use Symfony\Component\Lock\Store\FlockStore;
use Geolid\Daemon\Daemon;

abstract class AbstractDaemonCommand extends Command
{
    /**
     * @var Daemon
     */
    protected $daemon;

    /**
     * @var Lock
     */
    protected $lock;

    /**
     * @var bool
     */
    protected $isUnique = false;

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
        return new Daemon();
    }

    public function getDaemon(): Daemon
    {
        return $this->daemon;
    }

    public function setUnique(bool $isUnique): self
    {
        $this->isUnique = $isUnique;

        return $this;
    }

    public function isUnique(): bool
    {
        return $this->isUnique;
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

    public function setLock(LockInterface $lock): self
    {
        $this->lock = $lock;

        return $this;
    }

    public function getLock(): ?LockInterface
    {
        return $this->lock;
    }

    protected function checkLock(): void
    {
        if (!$this->isUnique) {
            return;
        }

        // If no lock we create a default one
        if (null === $this->lock) {
            $factory = new Factory(new FlockStore());
            $this->lock = $factory->createLock($this->getName() ?: $this->getDefaultName());
        }

        if (!$this->lock->acquire()) {
            throw new \RuntimeException(sprintf(
                'Command "%s" is already in use',
                $this->getName() ?: $this->getDefaultName()
            ));
        }
    }

    protected function releaseLock(): void
    {
        if ($this->lock) {
            $this->lock->release();
        }
    }

    protected function setup(InputInterface $input, OutputInterface $output)
    {
    }

    protected function onShutdown(InputInterface $input, OutputInterface $output): ?int
    {
        return $this->getDaemon()->getShutdownCode();
    }

    /**
     * What happen when an exception is thrown inside the loop?
     */
    protected function onException(\Throwable $throwable, InputInterface $input, OutputInterface $output)
    {
        throw $throwable;
    }

    public function daemon(InputInterface $input, OutputInterface $output)
    {
        $this->checkLock();

        if ($input->getOption('no-daemon')) {
            $this->setup($input, $output);

            try {
                $this->execute($input, $output);
            } catch (\Throwable $throwable) {
                $this->onException($throwable, $input, $output);
            }

            $result = $this->onShutdown($input, $output);

            // If its an unique daemon, we release the lock
            $this->releaseLock();

            return $result;
        }

        $exceptionCallback = function (\Throwable $throwable) use ($input, $output) {
            $this->onException($throwable, $input, $output);
        };

        $callback = function () use ($input, $output) {
            $this->execute($input, $output);
        };

        $this->daemon
            ->setTtl($input->getOption('ttl') ? (int) $input->getOption('ttl') : $this->daemon->getTtl())
            ->setMemoryThreshold($input->getOption('memory-max') ?: $this->daemon->getMemoryThreshold())
            ->setCallback($callback)
            ->setExceptionCallback($exceptionCallback)
        ;

        $this->setup($input, $output);

        $this->daemon->run();

        $result = $this->onShutdown($input, $output);

        // If its an unique daemon, we release the lock
        $this->releaseLock();

        return $result;
    }
}
