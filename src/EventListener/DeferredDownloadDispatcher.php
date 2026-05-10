<?php declare(strict_types=1);

namespace App\EventListener;

use App\Message\Download;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\Messenger\MessageBusInterface;

final class DeferredDownloadDispatcher
{
    /** @var list<array{url: string, jobId: string}> */
    private array $pending = [];

    public function __construct(private readonly MessageBusInterface $bus)
    {
    }

    public function queue(string $url, string $jobId): void
    {
        $this->pending[] = ['url' => $url, 'jobId' => $jobId];
    }

    #[AsEventListener(event: TerminateEvent::class)]
    public function onTerminate(TerminateEvent $event): void
    {
        $jobs = $this->pending;
        $this->pending = [];

        foreach ($jobs as $job) {
            $this->bus->dispatch(new Download($job['url'], $job['jobId']));
        }
    }
}
