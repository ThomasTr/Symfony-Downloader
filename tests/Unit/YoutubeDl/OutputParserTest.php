<?php declare(strict_types=1);

namespace App\Tests\Unit\YoutubeDl;

use App\YoutubeDl\OutputParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OutputParserTest extends TestCase
{
    private OutputParser $parser;

    protected function setUp(): void
    {
        $this->parser = new OutputParser();
    }

    public static function titleBuffers(): array
    {
        return [
            'download destination'      => ['[download] Destination: /tmp/Foo Bar.mp4',                         'Foo Bar.mp4'],
            'ffmpeg destination'        => ['[ffmpeg] Destination: /tmp/output.mp3',                            'output.mp3'],
            'ExtractAudio destination'  => ['[ExtractAudio] Destination: /tmp/clip.m4a',                        'clip.m4a'],
            'already downloaded'        => ['[download] /tmp/Already There.mp4 has already been downloaded',    'Already There.mp4'],
            'unc-style relative path'   => ['[download] Destination: video.mp4',                                'video.mp4'],
        ];
    }

    #[DataProvider('titleBuffers')]
    public function testParseTitleExtractsBasename(string $buffer, string $expected): void
    {
        self::assertSame($expected, $this->parser->parseTitle($buffer));
    }

    public function testParseTitleReturnsNullForUnrelatedBuffer(): void
    {
        self::assertNull($this->parser->parseTitle('[generic] Extracting URL: https://example.com'));
    }

    public function testParseTitleReturnsNullForEmptyString(): void
    {
        self::assertNull($this->parser->parseTitle(''));
    }

    public function testParseProgressReturnsEmptyArrayForUnrelatedBuffer(): void
    {
        self::assertSame([], $this->parser->parseProgress('[info] some random output'));
    }

    public function testParseProgressBasicLine(): void
    {
        $result = $this->parser->parseProgress('[download]   3.2% of ~ 12.34MiB at 1.50MiB/s ETA 00:42');

        self::assertCount(1, $result);
        self::assertSame('3.2',          $result[0]['percentage']);
        self::assertSame('12.34MiB',     $result[0]['size']);
        self::assertSame('1.50MiB/s',    $result[0]['speed']);
        self::assertSame('00:42',        $result[0]['eta']);
        self::assertNull($result[0]['totalTime']);
    }

    public function testParseProgressHandlesUnknownSpeed(): void
    {
        $result = $this->parser->parseProgress('[download]   1.0% of 5.00MiB at Unknown speed ETA Unknown ETA');

        self::assertCount(1, $result);
        self::assertSame('1.0',           $result[0]['percentage']);
        self::assertSame('5.00MiB',       $result[0]['size']);
        self::assertSame('Unknown speed', $result[0]['speed']);
        self::assertSame('Unknown ETA',   $result[0]['eta']);
    }

    public function testParseProgressHandlesFinalLineWithTotalTime(): void
    {
        $result = $this->parser->parseProgress('[download] 100% of 25.00MiB in 00:42');

        self::assertCount(1, $result);
        self::assertSame('100',       $result[0]['percentage']);
        self::assertSame('25.00MiB',  $result[0]['size']);
        self::assertNull($result[0]['speed']);
        self::assertNull($result[0]['eta']);
        self::assertSame('00:42',     $result[0]['totalTime']);
    }

    public function testParseProgressHandlesMultipleLinesInSingleBuffer(): void
    {
        $buffer = "[download]   1.0% of 5.00MiB at 100KiB/s ETA 00:50\n"
                . "[download]   2.0% of 5.00MiB at 200KiB/s ETA 00:30";

        $result = $this->parser->parseProgress($buffer);

        self::assertCount(2, $result);
        self::assertSame('1.0', $result[0]['percentage']);
        self::assertSame('2.0', $result[1]['percentage']);
    }

    public static function sizeUnitsProvider(): array
    {
        return [
            'KiB' => ['[download] 50.0% of 100KiB at 10KiB/s ETA 00:05', '100KiB',  '10KiB/s'],
            'MiB' => ['[download] 50.0% of 100MiB at 10MiB/s ETA 00:05', '100MiB',  '10MiB/s'],
            'GiB' => ['[download] 50.0% of 1.5GiB at 50MiB/s ETA 00:30', '1.5GiB',  '50MiB/s'],
        ];
    }

    #[DataProvider('sizeUnitsProvider')]
    public function testParseProgressAcceptsAllSizeUnits(string $buffer, string $expectedSize, string $expectedSpeed): void
    {
        $result = $this->parser->parseProgress($buffer);

        self::assertCount(1, $result);
        self::assertSame($expectedSize,  $result[0]['size']);
        self::assertSame($expectedSpeed, $result[0]['speed']);
    }

    public function testParseProgressTildeSizeIsKept(): void
    {
        $result = $this->parser->parseProgress('[download]  10.0% of ~ 12.34MiB at 1MiB/s ETA 00:10');

        self::assertCount(1, $result);
        self::assertSame('12.34MiB', $result[0]['size']);
    }

    public function testProgressPatternConstantIsExposed(): void
    {
        self::assertNotEmpty(OutputParser::PROGRESS_PATTERN);
        self::assertSame(1, preg_match(OutputParser::PROGRESS_PATTERN, '[download] 50.0% of 1MiB at 1MiB/s ETA 00:01'));
    }
}
