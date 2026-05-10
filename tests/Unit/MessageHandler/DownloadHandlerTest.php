<?php declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Message\Download;
use App\MessageHandler\DownloadHandler;
use App\Service\JobStatusStore;
use App\YoutubeDl\OutputParser;
use App\YoutubeDl\YoutubeDlFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use YoutubeDl\Entity\Video;
use YoutubeDl\Entity\VideoCollection;
use YoutubeDl\Options as YoutubeDlBaseOptions;
use YoutubeDl\YoutubeDl;

class DownloadHandlerTest extends TestCase
{
    private const URL    = 'https://example.com/video';
    private const JOB_ID = 'job-abc';

    /**
     * @var list<Update>
     */
    private array $publishedUpdates = [];

    private HubInterface $hub;

    private YoutubeDl $yt;

    private YoutubeDlFactory $factory;

    private JobStatusStore $jobs;

    private string $jobsDir;

    /**
     * @var (callable(string,string):void)|null
     */
    private $capturedDebugCallback;

    protected function setUp(): void
    {
        $this->publishedUpdates      = [];
        $this->capturedDebugCallback = null;
        $this->jobsDir               = sys_get_temp_dir().'/sfdl-handler-'.uniqid('', true);
        $this->jobs                  = new JobStatusStore($this->jobsDir);
        $this->jobs->markPending(self::JOB_ID, self::URL);

        $this->hub = $this->createStub(HubInterface::class);
        $this->hub
            ->method('publish')
            ->willReturnCallback(function (Update $update): string {
                $this->publishedUpdates[] = $update;

                return 'id-'.count($this->publishedUpdates);
            });

        $this->yt = $this->createStub(YoutubeDl::class);
        $this->yt->method('setBinPath')->willReturnSelf();
        $this->yt
            ->method('debug')
            ->willReturnCallback(function (callable $cb): YoutubeDl {
                $this->capturedDebugCallback = $cb;

                return $this->yt;
            });

        $this->factory = $this->createStub(YoutubeDlFactory::class);
        $this->factory->method('create')->willReturn($this->yt);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->jobsDir))
        {
            foreach (glob($this->jobsDir.'/*') ?: [] as $file)
            {
                @unlink($file);
            }
            @rmdir($this->jobsDir);
        }
    }

    public function testInvokeExtractsTitleAndPublishesProgressUpdates(): void
    {
        $this->yt
            ->method('download')
            ->willReturnCallback(function (YoutubeDlBaseOptions $_options): VideoCollection {
                ($this->capturedDebugCallback)('out', '[download] Destination: /tmp/Foo Bar.mp4');
                ($this->capturedDebugCallback)('out', '[download]   12.5% of 16.00MiB at 2.00MiB/s ETA 00:30');

                return new VideoCollection([new Video(['title' => 'Foo Bar'])]);
            });

        $this->createHandler()->__invoke(new Download(self::URL, self::JOB_ID));

        $jobRecord = $this->jobs->read(self::JOB_ID);
        self::assertSame('success', $jobRecord['status']);
        self::assertSame('Foo Bar', $jobRecord['title']);
        self::assertNull($jobRecord['error']);

        $payloads   = $this->decodedPayloads();
        $expectedId = hash('md5', self::URL);

        self::assertCount(2, $payloads, 'progress update + final success update');

        $progress = $payloads[0];
        self::assertSame($expectedId,   $progress['id']);
        self::assertSame('Foo Bar.mp4', $progress['title']);
        self::assertSame('12.5',        $progress['percentage']);
        self::assertSame('16.00MiB',    $progress['size']);
        self::assertSame('2.00MiB/s',   $progress['speed']);
        self::assertSame('00:30',       $progress['eta']);
        self::assertNull($progress['totalTime']);
        self::assertNull($progress['alertMessage']);

        $finalSuccess = $payloads[1];
        self::assertSame($expectedId, $finalSuccess['id']);
        self::assertSame('Foo Bar',   $finalSuccess['title']);
        self::assertSame(100,         $finalSuccess['percentage']);
        self::assertNull($finalSuccess['alertMessage']);
    }

    public function testInvokePublishesAlertForNonStdoutBuffer(): void
    {
        $this->yt
            ->method('download')
            ->willReturnCallback(function (YoutubeDlBaseOptions $_options): VideoCollection {
                ($this->capturedDebugCallback)('err', 'something went wrong');

                return new VideoCollection([]);
            });

        $this->createHandler()->__invoke(new Download(self::URL, self::JOB_ID));

        $payloads = $this->decodedPayloads();
        self::assertCount(1, $payloads);
        self::assertSame('err: something went wrong', $payloads[0]['alertMessage']);

        $jobRecord = $this->jobs->read(self::JOB_ID);
        self::assertSame('failed',            $jobRecord['status']);
        self::assertSame('No videos produced', $jobRecord['error']);
    }

    public function testInvokePublishesNomatchAlertForUnrecognisedStdout(): void
    {
        $this->yt
            ->method('download')
            ->willReturnCallback(function (YoutubeDlBaseOptions $_options): VideoCollection {
                ($this->capturedDebugCallback)('out', '[generic] some chatter');

                return new VideoCollection([]);
            });

        $this->createHandler()->__invoke(new Download(self::URL, self::JOB_ID));

        $payloads = $this->decodedPayloads();
        self::assertCount(1, $payloads);
        self::assertSame('nomatch: [generic] some chatter', $payloads[0]['alertMessage']);

        $jobRecord = $this->jobs->read(self::JOB_ID);
        self::assertSame('failed',             $jobRecord['status']);
        self::assertSame('No videos produced', $jobRecord['error']);
    }

    public function testInvokePublishesVideoErrorWhenVideoHasError(): void
    {
        $this->yt
            ->method('download')
            ->willReturn(new VideoCollection([new Video(['error' => 'boom'])]));

        $this->createHandler()->__invoke(new Download(self::URL, self::JOB_ID));

        $payloads = $this->decodedPayloads();
        self::assertCount(1, $payloads);
        self::assertSame('Error downloading video: boom.', $payloads[0]['alertMessage']);

        $jobRecord = $this->jobs->read(self::JOB_ID);
        self::assertSame('failed', $jobRecord['status']);
        self::assertSame('boom',   $jobRecord['error']);
    }

    public function testInvokeCatchesExceptionAndPublishesAlert(): void
    {
        $this->yt
            ->method('download')
            ->willThrowException(new \RuntimeException('network down'));

        $this->createHandler()->__invoke(new Download(self::URL, self::JOB_ID));

        $payloads = $this->decodedPayloads();
        self::assertCount(1, $payloads);
        self::assertSame(hash('md5', self::URL),  $payloads[0]['id']);
        self::assertSame('network down',          $payloads[0]['alertMessage']);
        self::assertSame('alert alert-danger',    $payloads[0]['alertClass']);

        $jobRecord = $this->jobs->read(self::JOB_ID);
        self::assertSame('failed',       $jobRecord['status']);
        self::assertSame('network down', $jobRecord['error']);
    }

    public function testInvokePublishesAllProgressMatchesInBuffer(): void
    {
        $this->yt
            ->method('download')
            ->willReturnCallback(function (YoutubeDlBaseOptions $_options): VideoCollection {
                $multi = "[download]   1.0% of 5.00MiB at 100KiB/s ETA 00:50\n"
                       . "[download]   2.0% of 5.00MiB at 200KiB/s ETA 00:30";
                ($this->capturedDebugCallback)('out', $multi);

                return new VideoCollection([new Video(['title' => 'x'])]);
            });

        $this->createHandler()->__invoke(new Download(self::URL, self::JOB_ID));

        $payloads = $this->decodedPayloads();
        self::assertCount(3, $payloads, '2 progress + 1 final success');
        self::assertSame('1.0', $payloads[0]['percentage']);
        self::assertSame('2.0', $payloads[1]['percentage']);

        $jobRecord = $this->jobs->read(self::JOB_ID);
        self::assertSame('success', $jobRecord['status']);
        self::assertSame('x',       $jobRecord['title']);
    }

    public function testInvokeMixedBatchPrefersSuccessOverPartialErrors(): void
    {
        $this->yt
            ->method('download')
            ->willReturn(new VideoCollection([
                new Video(['error' => 'partial-failure']),
                new Video(['title' => 'Worked Anyway']),
            ]));

        $this->createHandler()->__invoke(new Download(self::URL, self::JOB_ID));

        $jobRecord = $this->jobs->read(self::JOB_ID);
        self::assertSame('success',       $jobRecord['status'], 'a single successful video wins over partial errors');
        self::assertSame('Worked Anyway', $jobRecord['title']);
        self::assertNull($jobRecord['error']);

        $payloads = $this->decodedPayloads();
        self::assertCount(2, $payloads);
        self::assertSame('Error downloading video: partial-failure.', $payloads[0]['alertMessage']);
        self::assertNull($payloads[1]['alertMessage']);
    }

    public function testInvokeReportsAggregatedErrorsToJobStore(): void
    {
        $this->yt
            ->method('download')
            ->willReturn(new VideoCollection([
                new Video(['error' => 'first']),
                new Video(['error' => 'second']),
            ]));

        $this->createHandler()->__invoke(new Download(self::URL, self::JOB_ID));

        $jobRecord = $this->jobs->read(self::JOB_ID);
        self::assertSame('failed',         $jobRecord['status']);
        self::assertSame('first; second',  $jobRecord['error']);
    }

    private function createHandler(?LoggerInterface $logger = null): DownloadHandler
    {
        $parameters = $this->createStub(ParameterBagInterface::class);
        $parameters->method('get')->willReturnMap([
            ['ytDlpPath',    '/usr/bin/true'],
            ['downloadPath', '/tmp/sfdl-test'],
            ['ffmpegPath',   '/usr/bin/true'],
        ]);

        return new DownloadHandler(
            $this->hub,
            $logger ?? new NullLogger(),
            $parameters,
            $this->factory,
            new OutputParser(),
            $this->jobs,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodedPayloads(): array
    {
        $payloads = [];

        foreach ($this->publishedUpdates as $update)
        {
            self::assertSame(['downloads'], $update->getTopics());
            $payload = json_decode($update->getData(), true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($payload);
            $payloads[] = $payload;
        }

        return $payloads;
    }
}
