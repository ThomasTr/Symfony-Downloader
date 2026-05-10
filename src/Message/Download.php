<?php declare(strict_types=1);

namespace App\Message;

final class Download
{
    public function __construct(
        public string $url,
        public string $jobId,
    ) {
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }
}
