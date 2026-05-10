<?php declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\EventListener\DeferredDownloadDispatcher;
use App\Message\Download;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class DeferredDownloadDispatcherTest extends TestCase
{
    public function testQueueDoesNotDispatchUntilTerminate(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $dispatcher = new DeferredDownloadDispatcher($bus);
        $dispatcher->queue('https://example.com/a', 'job-1');
    }

    public function testOnTerminateDispatchesAllQueuedJobs(): void
    {
        $bus      = $this->createStub(MessageBusInterface::class);
        $messages = [];
        $bus->method('dispatch')
            ->willReturnCallback(function (Download $message) use (&$messages): Envelope {
                $messages[] = $message;

                return new Envelope($message);
            });

        $dispatcher = new DeferredDownloadDispatcher($bus);
        $dispatcher->queue('https://example.com/a', 'job-1');
        $dispatcher->queue('https://example.com/b', 'job-2');

        $dispatcher->onTerminate($this->terminateEvent());

        self::assertCount(2, $messages);
        self::assertSame('https://example.com/a', $messages[0]->getUrl());
        self::assertSame('job-1',                 $messages[0]->getJobId());
        self::assertSame('https://example.com/b', $messages[1]->getUrl());
        self::assertSame('job-2',                 $messages[1]->getJobId());
    }

    public function testOnTerminateClearsQueueSoSecondTerminateIsNoop(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(static fn ($m) => new Envelope($m));

        $dispatcher = new DeferredDownloadDispatcher($bus);
        $dispatcher->queue('https://example.com/a', 'job-1');

        $dispatcher->onTerminate($this->terminateEvent());
        $dispatcher->onTerminate($this->terminateEvent());
    }

    private function terminateEvent(): TerminateEvent
    {
        return new TerminateEvent(
            $this->createStub(HttpKernelInterface::class),
            new Request(),
            new Response(),
        );
    }
}
