<?php

namespace Geolid\Daemon;

/**
 * Daemon
 *
 * This is simply a Cron that runs indefinitly
 *
 * <code>
 *
 * </code>
 */
class Daemon extends Cron
{
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
            } catch (\Throwable $e) {
                // By default any error/exception is thrown.
                if (is_null($this->getExceptionCallback())) {
                    throw $e;
                }

                call_user_func($this->getExceptionCallback(), $e, $this);
            }

            $loopInterval = $this->getLoopInterval();
            if (!is_null($loopInterval)) {
                usleep($loopInterval);
            }
        }
    }
}
