<?php declare(strict_types=1);

namespace App\EventListener;

use App\Message\Download;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\Messenger\MessageBusInterface;

final class DeferredDownloadDispatcher
{
    /** @var list<string> */
    private array $pendingUrls = [];

    public function __construct(private readonly MessageBusInterface $bus)
    {
    }

    public function queue(string $url): void
    {
        $this->pendingUrls[] = $url;
    }

    #[AsEventListener(event: TerminateEvent::class)]
    public function onTerminate(TerminateEvent $event): void
    {
        $urls = $this->pendingUrls;
        $this->pendingUrls = [];

        foreach ($urls as $url) {
            $this->bus->dispatch(new Download($url));
        }
    }
}
