<?php declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\DownloadCommand;
use App\YoutubeDl\OutputParser;
use App\YoutubeDl\YoutubeDlFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YoutubeDl\Entity\Video;
use YoutubeDl\Entity\VideoCollection;
use YoutubeDl\Options as YoutubeDlBaseOptions;
use YoutubeDl\YoutubeDl;

class DownloadCommandTest extends TestCase
{
    /**
     * @var (callable(string,string):void)|null
     */
    private $capturedDebugCallback;

    public function testCommandRendersTitleAndProgressAndSuccess(): void
    {
        $yt = $this->buildYtMock(function () {
            ($this->capturedDebugCallback)('out', '[download] Destination: /tmp/Foo.mp4');
            ($this->capturedDebugCallback)('out', '[download]   50.0% of 4.00MiB at 1.00MiB/s ETA 00:02');

            return new VideoCollection([new Video(['title' => 'Foo'])]);
        });

        $tester = $this->createTester($yt);
        $tester->execute(['url' => 'https://example.com/video']);

        $output = $tester->getDisplay();

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('TITLE: Foo.mp4', $output);
        self::assertStringContainsString('50.0%',           $output);
        self::assertStringContainsString('4.00MiB',         $output);
        self::assertStringContainsString('Successfully downloaded Foo', $output);
    }

    public function testCommandReportsVideoErrors(): void
    {
        $yt = $this->buildYtMock(fn () => new VideoCollection([new Video(['error' => 'boom'])]));

        $tester = $this->createTester($yt);
        $tester->execute(['url' => 'https://example.com/video']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Error downloading video: boom.', $tester->getDisplay());
    }

    public function testCommandLogsUnrecognisedOutput(): void
    {
        $yt = $this->buildYtMock(function () {
            ($this->capturedDebugCallback)('out', '[generic] hello');
            ($this->capturedDebugCallback)('err', 'bye');

            return new VideoCollection([]);
        });

        $tester = $this->createTester($yt);
        $tester->execute(['url' => 'https://example.com/video']);

        $output = $tester->getDisplay();
        self::assertStringContainsString('Type: OOUUTT: [generic] hello', $output);
        self::assertStringContainsString('Type: err: bye',                $output);
    }

    private function buildYtMock(\Closure $downloadResolver): YoutubeDl
    {
        $yt = $this->createStub(YoutubeDl::class);
        $yt->method('setBinPath')->willReturnSelf();
        $yt
            ->method('debug')
            ->willReturnCallback(function (callable $cb) use ($yt): YoutubeDl {
                $this->capturedDebugCallback = $cb;

                return $yt;
            });
        $yt
            ->method('download')
            ->willReturnCallback(function (YoutubeDlBaseOptions $_options) use ($downloadResolver): VideoCollection {
                return $downloadResolver();
            });

        return $yt;
    }

    private function createTester(YoutubeDl $yt): CommandTester
    {
        $factory = $this->createStub(YoutubeDlFactory::class);
        $factory->method('create')->willReturn($yt);

        $parameters = $this->createStub(ParameterBagInterface::class);
        $parameters->method('get')->willReturnMap([
            ['ytDlpPath',    '/usr/bin/true'],
            ['downloadPath', '/tmp/sfdl-test'],
            ['ffmpegPath',   '/usr/bin/true'],
        ]);

        $command = new DownloadCommand($parameters, $factory, new OutputParser());

        return new CommandTester($command);
    }
}
