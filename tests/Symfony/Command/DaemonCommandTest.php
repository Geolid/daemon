<?php

namespace Geolid\Tests\Daemon\Symfony\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Geolid\Daemon\Daemon;
use Geolid\Daemon\Symfony\Command\DaemonCommand;

class DaemonCommandTest extends TestCase
{
    public function testDaemonConfiguration()
    {
        $c = new DaemonCommandConcrete;

        // Test default configuration
        $this->assertSame(500000, $c->getDaemon()->getLoopInterval());
        $this->assertSame(1, $c->getDaemon()->getTtl());
        $this->assertSame(10 * 1024 * 1024, $c->getDaemon()->getMemoryThreshold());

        $t = new CommandTester($c);

        // stop the loop directly to avoid waiting for nothing
        $c->getDaemon()->requestShutdown();

        $t->execute([
            '--ttl' => 12,
            '--memory-max' => '1K',
        ]);

        // We test that the configuration has been changed by the inputs
        $this->assertSame(12, $c->getDaemon()->getTtl());
        $this->assertSame(1024, $c->getDaemon()->getMemoryThreshold());
    }

    public function testRunTtl()
    {
        $c = new DaemonCommandConcrete;
        $t = new CommandTester($c);
        $t->execute([]);

        $expectedOutput = "starting
hello
hello
stopped
";

        $this->assertSame($expectedOutput, $t->getDisplay());
        $this->assertSame(Daemon::TTL_REACHED, $t->getStatusCode());
    }

    public function testRunNoDaemon()
    {
        $c = new DaemonCommandConcrete;
        $t = new CommandTester($c);
        $t->execute([
            '--no-daemon' => true,
        ]);

        $expectedOutput = "starting
hello
stopped
";

        $this->assertSame($expectedOutput, $t->getDisplay());
        $this->assertSame(0, $t->getStatusCode());
        $this->assertNull($c->getDaemon()->getShutdownCode());
    }

    public function testRunException()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('exception message');

        $c = new DaemonCommandConcreteThrowException;
        $t = new CommandTester($c);
        $t->execute([]);
    }

    public function testRunNoDaemonException()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('exception message');

        $c = new DaemonCommandConcreteThrowException;
        $t = new CommandTester($c);
        $t->execute([
            '--no-daemon' => true,
        ]);
    }

    public function testRunMemoryMax()
    {
        $c = new DaemonCommandConcreteMaxMemory;
        $t = new CommandTester($c);
        $t->execute([
            '--ttl' => 1, // by security, to avoid tests run indefinitly in case of the memory threshold doesn't work
            '--memory-max' => '500K'
        ]);

        $this->assertSame(Daemon::MEMORY_THRESHOLD_REACHED, $c->getDaemon()->getShutdownCode());
    }
}
