<?php declare(strict_types=1);

namespace App\Tests\Unit\Message;

use App\Message\Download;
use PHPUnit\Framework\TestCase;

class DownloadTest extends TestCase
{
    public function testStoresUrlAndJobId(): void
    {
        $download = new Download('https://example.com/video', 'job-abc');

        self::assertSame('https://example.com/video', $download->url);
        self::assertSame('job-abc',                   $download->jobId);
    }

    public function testGettersReturnConstructorArguments(): void
    {
        $download = new Download('https://youtu.be/abc123', 'deadbeef');

        self::assertSame('https://youtu.be/abc123', $download->getUrl());
        self::assertSame('deadbeef',                $download->getJobId());
    }
}
