<?php declare(strict_types=1);

namespace App\Tests\Controller;

use App\Kernel;
use App\Message\Download;
use App\Service\JobStatusStore;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class DownloadControllerTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testIndexRendersTemplate(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
    }

    public function testApiDownloadAcceptsRequestAndQueuesJob(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/download', ['url' => 'https://example.com/video']);

        self::assertResponseStatusCodeSame(202);
        self::assertResponseHeaderSame('content-type', 'application/json');

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertTrue($payload['accepted']);
        self::assertSame('https://example.com/video', $payload['url']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $payload['job_id']);

        /** @var JobStatusStore $jobs */
        $jobs    = static::getContainer()->get(JobStatusStore::class);
        $pending = $jobs->read($payload['job_id']);

        self::assertNotNull($pending);
        self::assertSame('pending',                    $pending['status']);
        self::assertSame('https://example.com/video',  $pending['url']);
        self::assertNull($pending['title']);
        self::assertNull($pending['error']);

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        $envelopes = $transport->getSent();

        self::assertCount(1, $envelopes);
        $message = $envelopes[0]->getMessage();
        self::assertInstanceOf(Download::class, $message);
        self::assertSame('https://example.com/video', $message->getUrl());
        self::assertSame($payload['job_id'],          $message->getJobId());
    }

    public function testApiDownloadRejectsMissingUrl(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/download');

        self::assertResponseStatusCodeSame(400);

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('Missing url parameter', $payload['error']);

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        self::assertCount(0, $transport->getSent());
    }

    public function testApiDownloadRejectsEmptyUrl(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/download', ['url' => '']);

        self::assertResponseStatusCodeSame(400);
    }

    public function testApiDownloadRejectsMalformedUrl(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/download', ['url' => 'not-a-url']);

        self::assertResponseStatusCodeSame(400);

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('Invalid url parameter', $payload['error']);
        self::assertNotEmpty($payload['violations']);
    }

    public function testApiDownloadRejectsNonHttpScheme(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/download', ['url' => 'ftp://example.com/file']);

        self::assertResponseStatusCodeSame(400);
    }

    public function testStatusEndpointReturnsJobData(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/download', ['url' => 'https://example.com/video']);
        $created = json_decode((string) $client->getResponse()->getContent(), true);

        $client->request('GET', '/api/download/status/'.$created['job_id']);

        self::assertResponseIsSuccessful();
        $status = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame($created['job_id'],            $status['id']);
        self::assertSame('https://example.com/video',   $status['url']);
        self::assertSame('pending',                     $status['status']);
    }

    public function testStatusEndpointReturns404ForUnknownJob(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/download/status/doesnotexist');

        self::assertResponseStatusCodeSame(404);
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('Job not found', $payload['error']);
    }
}
