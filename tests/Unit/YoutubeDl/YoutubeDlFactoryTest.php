<?php declare(strict_types=1);

namespace App\Tests\Unit\YoutubeDl;

use App\YoutubeDl\YoutubeDlFactory;
use PHPUnit\Framework\TestCase;
use YoutubeDl\YoutubeDl;

class YoutubeDlFactoryTest extends TestCase
{
    public function testCreateReturnsYoutubeDlInstance(): void
    {
        self::assertInstanceOf(YoutubeDl::class, (new YoutubeDlFactory())->create());
    }

    public function testCreateReturnsFreshInstancePerCall(): void
    {
        $factory = new YoutubeDlFactory();

        self::assertNotSame($factory->create(), $factory->create());
    }
}
