<?php declare(strict_types=1);

namespace App\Tests\Unit\YoutubeDl;

use App\YoutubeDl\Options;
use PHPUnit\Framework\TestCase;

class OptionsTest extends TestCase
{
    public function testCreateReturnsOptionsInstance(): void
    {
        self::assertInstanceOf(Options::class, Options::create());
    }

    public function testPresetAliasIsImmutable(): void
    {
        $original = Options::create();
        $modified = $original->presetAlias('mp4');

        self::assertNotSame($original, $modified);
        self::assertNull($original->toArray()['preset-alias']);
        self::assertSame('mp4', $modified->toArray()['preset-alias']);
    }

    public function testForceGenericExtractorIsImmutable(): void
    {
        $original = Options::create();
        $modified = $original->forceGenericExtractor(true);

        self::assertNotSame($original, $modified);
        self::assertArrayNotHasKey('force-generic-extractor', $original->toArray());
        self::assertTrue($modified->toArray()['force-generic-extractor']);
    }

    public function testToArrayAlwaysContainsPresetAliasKey(): void
    {
        $options = Options::create();

        self::assertArrayHasKey('preset-alias', $options->toArray());
        self::assertNull($options->toArray()['preset-alias']);
    }

    public function testToArrayOmitsForceGenericExtractorWhenFalse(): void
    {
        $options = Options::create()->forceGenericExtractor(false);

        self::assertArrayNotHasKey('force-generic-extractor', $options->toArray());
    }

    public function testToArrayIncludesForceGenericExtractorWhenTrue(): void
    {
        $options = Options::create()->forceGenericExtractor(true);

        self::assertArrayHasKey('force-generic-extractor', $options->toArray());
        self::assertTrue($options->toArray()['force-generic-extractor']);
    }

    public function testFluentChainingProducesCombinedOptions(): void
    {
        $options = Options::create()
                          ->presetAlias('mp4')
                          ->forceGenericExtractor(true);

        $array = $options->toArray();

        self::assertSame('mp4', $array['preset-alias']);
        self::assertTrue($array['force-generic-extractor']);
    }
}
