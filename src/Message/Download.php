<?php declare(strict_types=1);

namespace App\Message;

final class Download
{
    public function __construct(public string $url)
    {
    }

    public function getUrl(): string
    {
        return $this->url;
    }
}
