<?php

declare(strict_types=1);

namespace Geolid\Daemon;

/**
 * A simple class to create script that can gracefully shutdown on some triggers:
 *  - a memory threshold is reached
 *  - a TTl is reached
 *  - a signal is handled (only if pcntl extension is enabled)
 *
 * <code>
 *     <?php
 *
 *     $daemon = new Daemon;
 *     $daemon
 *         ->setMemoryThreshold('550M')
 *         ->setTtl(86400)
 *     ;
 *
 *     $daemon->initialize();
 *
 *     for ($i = 0; $i < 10000; $i++) {
 *         // do stuff
 *
 *         if ($daemon->isShutdownRequested()) {
 *             break;
 *         }
 *     }
 *
 *     echo $daemon->getShutdownCode();
 *     echo $daemon->getShutdownReason();
 * </code>
 */
class Daemon
{
    const LOGIC_SHUTDOWN           = 1; // Shutdown requested by the code
    const TTL_REACHED              = 2; // Shutdown requested by the end of ttl
    const MEMORY_THRESHOLD_REACHED = 3; // Shutdown requested by an overflow of memory
    const SIGNAL_HANDLED           = 4; // Shutdown requested by a signal

    /**
     * @var array
     */
    public $shutdownReasons = [
        self::LOGIC_SHUTDOWN           => "Logic shutdown",
        self::TTL_REACHED              => "TTL reached",
        self::MEMORY_THRESHOLD_REACHED => "Memory threshold reached",
        self::SIGNAL_HANDLED           => "Signal handled",
    ];

    /**
     * All signals handled and leading to a shutdown
     *
     * @var array
     */
    public $signalsHandled = [SIGTERM, SIGINT];

    /**
     * Shutdown the worker when the ttl is reached
     * In seconds, null means no ttl
     * Default: null
     *
     * @var int|null
     */
    protected $ttl;

    /**
     * Max memory allowed before gracefully shutdown the loop
     * In bytes, null means no limit
     * Default: null
     *
     * @var int|null
     */
    protected $memoryThreshold;

    /**
     * @var int|null
     */
    protected $startedTime;

    /**
     * @var bool
     */
    protected $shutdownRequested = false;

    /**
     * @var int|null
     */
    protected $shutdownCode;

    /**
     * @var string|null
     */
    protected $shutdownReason;

    /**
     * @var int|null
     */
    protected $loopInterval;

    /**
     * @var callable|null
     */
    protected $exceptionCallback;

    /**
     * @var callable|null
     */
    protected $callback;

    public function initialize(): Daemon
    {
        $this->startedTime = microtime(true);
        $this->handleSignals();

        return $this;
    }

    public function setTtl(int $ttl = null): Daemon
    {
        $this->ttl = $ttl;

        return $this;
    }

    public function getTtl(): ?int
    {
        return $this->ttl;
    }

    public function setMemoryThreshold($memoryThreshold = null): Daemon
    {
        if (is_string($memoryThreshold)) {
            $this->memoryThreshold = $this->toByteSize($memoryThreshold);
        } else {
            $this->memoryThreshold = $memoryThreshold;
        }

        return $this;
    }

    public function getMemoryThreshold(): ?int
    {
        return $this->memoryThreshold;
    }

    public function getStartedTime(): ?float
    {
        return $this->startedTime;
    }

    public function getElapsedTime(): ?float
    {
        if ($this->startedTime) {
            return microtime(true) - $this->startedTime;
        }

        return null;
    }

    public function getMemoryUsage(): int
    {
        return memory_get_usage(true);
    }

    public function getShutdownCode(): ?int
    {
        return $this->shutdownCode;
    }

    public function getShutdownReason(): ?string
    {
        if ($this->shutdownReason) {
            return $this->shutdownReason;
        }

        if (isset($this->shutdownReasons[$this->shutdownCode])) {
            return $this->shutdownReasons[$this->shutdownCode];
        }

        return null;
    }

    public function requestShutdown(
        int $shutdownCode = self::LOGIC_SHUTDOWN,
        string $shutdownReason = null
    ): self {
        $this->shutdownRequested = true;
        $this->shutdownCode = $shutdownCode;
        $this->shutdownReason = $shutdownReason;

        return $this;
    }

    public function isShutdownRequested(): bool
    {
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }

        // Check if we need to stop the loop because of the worker ttl
        if ($this->isTtlReached()) {
            $this->requestShutdown(self::TTL_REACHED, "TTL reached");
        }

        // check if we need to stop the loop because of the memory threshold
        if ($this->isMemoryThresholdReached()) {
            $this->requestShutdown(self::MEMORY_THRESHOLD_REACHED, "Memory threshold reached");
        }

        return $this->shutdownRequested;
    }

    public function isTtlReached(): bool
    {
        return !is_null($this->getTtl()) && $this->getElapsedTime() >= $this->getTtl();
    }

    public function isMemoryThresholdReached(): bool
    {
        return !is_null($this->getMemoryThreshold()) && $this->getMemoryUsage() > $this->getMemoryThreshold();
    }

    protected function handleSignals(): void
    {
        // Add the signal handler
        if (function_exists('pcntl_signal')) {
            // Enable ticks for fast signal processing
            declare(ticks = 1);

            foreach ($this->signalsHandled as $signal) {
                pcntl_signal($signal, [$this, 'handleSignal']);
            }
        }
    }

    public function handleSignal(int $signal): void
    {
        if (in_array($signal, $this->signalsHandled)) {
            $this->requestShutdown(self::SIGNAL_HANDLED, "Signal handled: " . $signal);
        }
    }

    protected function toByteSize(string $str): int
    {
        $str = trim($str);
        $units = array_flip(['B', 'K', 'M', 'G', 'T', 'P']);

        $unit = strtoupper(substr($str, -1));
        $number = (float) trim(substr($str, 0, -1));

        if (!isset($units[$unit])) {
            throw new \InvalidArgumentException(sprintf('Invalid memory string format, given: %s', $str));
        }

        return (int) ($number * pow(1024, $units[$unit]));
    }

    /**
     * Set the time between each iteration of the loop
     * In microseconds, null means no loop interval
     */
    public function setLoopInterval(int $loopInterval = null): Daemon
    {
        $this->loopInterval = $loopInterval;

        return $this;
    }

    public function getLoopInterval(): ?int
    {
        return $this->loopInterval;
    }

    /**
     * What do we do in case of exception?
     * If this callback is set to null, the exception will be thrown by default
     *
     * Callback example:
     * function (\Exception $e, Daemon $daemon) {
     *     // do something
     *     // throw the exception or not
     * }
     */
    public function setExceptionCallback(callable $callback = null): Daemon
    {
        $this->exceptionCallback = $callback;

        return $this;
    }

    public function getExceptionCallback(): ?callable
    {
        return $this->exceptionCallback;
    }

    /**
     * What do we do in the loop?
     * Either set a callable here or extends this class and override the execute() method
     *
     * Callback example:
     * function (Daemon $daemon) {
     *     // do something
     * }
     */
    public function setCallback(callable $callback = null): Daemon
    {
        $this->callback = $callback;

        return $this;
    }

    public function getCallback(): ?callable
    {
        return $this->callback;
    }

    /**
     * What do we do in the loop?
     * Either set a callable with setCallback() method or override this method
     */
    public function execute(): void
    {
        throw new \LogicException('You must override the execute() method or set a callback code.');
    }

    /**
     * Run the infinite loop
     */
    public function run(callable $callback = null): void
    {
        if ($callback) {
            $this->setCallback($callback);
        }

        // Initialize the parent
        $this->initialize();

        while (!$this->isShutdownRequested()) {
            try {
                if ($callback = $this->getCallback()) {
                    call_user_func($callback, $this);
                } else {
                    $this->execute();
                }
            } catch (\Throwable $throwable) {
                // By default any error/exception is thrown.
                if (is_null($this->getExceptionCallback())) {
                    throw $throwable;
                }

                call_user_func($this->getExceptionCallback(), $throwable, $this);
            }

            $loopInterval = $this->getLoopInterval();
            if (!is_null($loopInterval)) {
                usleep($loopInterval);
            }
        }
    }
}
