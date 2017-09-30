<?php

use PHPUnit\Framework\TestCase;
use Geolid\Daemon\Daemon;

class DaemonTest extends TestCase
{
    public function setUp()
    {
        $this->w = new Daemon;
    }

    public function createTimedDaemon(int $microseconds = 0)
    {
        $w = new Daemon;
        $w->setCallback(function (Daemon $w) use ($microseconds) {
            usleep($microseconds);
            $w->requestShutdown();
        });

        return $w;
    }

    public function createNullDaemon()
    {
        $w = new Daemon;
        $w->setCallback(function (Daemon $w) {
            // do nothing
        });

        return $w;
    }

    public function testSignalsHandled()
    {
        $this->assertContains(SIGTERM, $this->w->signalsHandled);
        $this->assertContains(SIGINT, $this->w->signalsHandled);
    }

    public function testSetGetTtl()
    {
        $this->assertNull($this->w->getTtl());
        $this->w->setTtl(12);
        $this->assertEquals(12, $this->w->getTtl());
    }

    public function testSetGetLoopInterval()
    {
        $this->assertNull($this->w->getLoopInterval());
        $this->w->setLoopInterval(1000);
        $this->assertEquals(1000, $this->w->getLoopInterval());
    }

    public function testSetGetMemoryThreshold_FromInt()
    {
        $this->assertNull($this->w->getMemoryThreshold());
        $this->w->setMemoryThreshold(12);
        $this->assertEquals(12, $this->w->getMemoryThreshold());
    }

    public function memoryStringProvider()
    {
        return [
            ["2B", 2],
            ["2K", 2 * 1024],
            ["0K", 0],
            ["2M", 2 * 1024 * 1024],
            [" 2 M ", 2 * 1024 * 1024],
            ["2 m", 2 * 1024 * 1024],
            ["1.5G", (int) (1.5 * 1024 * 1024 * 1024)],
        ];
    }

    /**
     * @dataProvider memoryStringProvider
     */
    public function testSetGetMemoryThreshold_FromString(string $memory, int $expected)
    {
        $this->w->setMemoryThreshold($memory);

        $this->assertInternalType("int", $this->w->getMemoryThreshold());
        $this->assertEquals($expected, $this->w->getMemoryThreshold());
    }

    public function testSetGetMemoryThreshold_FromString_WithError()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->w->setMemoryThreshold("WHAT?");
    }

    public function testGetMemoryUsage()
    {
        $this->assertInternalType("int", $this->w->getMemoryUsage());
    }

    public function testGetStartedTime()
    {
        $d = $this->createTimedDaemon();

        $this->assertNull($d->getStartedTime());

        $d->run();

        $this->assertInternalType("float", $d->getStartedTime());

        $t = time();
        $this->assertSame($t, (int) $d->getStartedTime());
    }

    public function testGetElapsedTime()
    {
        $d = $this->createTimedDaemon(1000000);

        $this->assertNull($d->getElapsedTime());

        $d->run();

        $this->assertInternalType("float", $d->getElapsedTime());
        $this->assertSame(1, (int) $d->getElapsedTime());
    }

    public function testSetGetExceptionCallback()
    {
        $c = function () { return true; };

        $this->assertNull($this->w->getExceptionCallback());
        $this->w->setExceptionCallback($c);
        $this->assertSame($c, $this->w->getExceptionCallback());
    }

    public function testSetGetCallback()
    {
        $c = function () { return true; };

        $this->assertNull($this->w->getCallback());
        $this->w->setCallback($c);
        $this->assertSame($c, $this->w->getCallback());
    }

    public function testRequestShutdown()
    {
        $this->assertNull($this->w->getShutdownCode());
        $this->assertNull($this->w->getShutdownReason());
        $this->assertFalse($this->w->isShutdownRequested());

        $this->w->requestShutdown(Daemon::LOGIC_SHUTDOWN);

        $this->assertTrue($this->w->isShutdownRequested());
        $this->assertSame(Daemon::LOGIC_SHUTDOWN, $this->w->getShutdownCode());
        $this->assertSame("Logic shutdown", $this->w->getShutdownReason());
    }

    public function testRequestShutdown_customCodeAndReason()
    {
        $this->w->requestShutdown(42, "Custom reason text");

        $this->assertSame(42, $this->w->getShutdownCode());
        $this->assertSame("Custom reason text", $this->w->getShutdownReason());
    }

    public function testIsTtlReached()
    {
        $d = $this->createNullDaemon();

        $this->assertFalse($d->isTtlReached());
        $d->setTtl(1);
        $this->assertFalse($d->isTtlReached());

        $d->run();

        $this->assertTrue($d->isTtlReached());
        $this->assertSame(Daemon::TTL_REACHED, $d->getShutdownCode());
    }

    public function testIsMemoryThresholdReached()
    {
        $d = $this->createNullDaemon();

        $this->assertFalse($d->isMemoryThresholdReached());
        $d->setMemoryThreshold("1G");
        $this->assertFalse($d->isMemoryThresholdReached());

        $d->setMemoryThreshold('3K');
        $d->run();

        $this->assertTrue($d->isMemoryThresholdReached());
        $this->assertSame(Daemon::MEMORY_THRESHOLD_REACHED, $d->getShutdownCode());
    }

    public function testRun_withNoCallback()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('You must override the execute() method or set a callback code.');

        $w = new Daemon;
        $w->run();
    }

    public function testRun_withAnticipatedShutdown()
    {
        $d = $this->createNullDaemon();
        $d->requestShutdown(42);
        $d->run(function() {
            throw new \Exception('Never thrown');
        });

        $this->assertSame(42, $d->getShutdownCode());
    }

    public function testRun_withException_default()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Hello');

        $w = new Daemon;
        $w->run(function () {
            throw new \Exception('Hello');
        });
    }

    public function testRun_withException_doNothing()
    {
        $w = new Daemon;
        $w
            ->setTtl(1)
            ->setExceptionCallback(function(){})
        ;

        $w->run(function () {
            throw new \Exception('Hello');
        });

        $this->assertSame(Daemon::TTL_REACHED, $w->getShutdownCode());
    }

    public function signalProvider()
    {
        return [
            [SIGINT],
            [SIGTERM],
        ];
    }

    /**
     * @dataProvider signalProvider
     */
    public function testHandleSignal(int $signal)
    {
        $d = new Daemon;
        $d->run(function (Daemon $d) use ($signal) {
            $d->handleSignal($signal);
        });

        $this->assertSame(Daemon::SIGNAL_HANDLED, $d->getShutdownCode());
    }

    public function testHandleSignal_notHandled()
    {
        $d = new Daemon;
        $d->setTtl(1);
        $d->run(function (Daemon $d) {
            $d->handleSignal(SIGKILL);
        });

        $this->assertSame(Daemon::TTL_REACHED, $d->getShutdownCode());
    }
}
