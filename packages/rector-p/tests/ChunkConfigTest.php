<?php

declare(strict_types=1);

namespace Andersundsehr\RectorP\Tests;

use Andersundsehr\RectorP\Dto\ChunkConfig;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ChunkConfigTest extends TestCase
{
    public function testToStringAndIncreaseChunk(): void
    {
        $chunkConfig = new ChunkConfig(2, 5);

        self::assertSame('2/5', $chunkConfig->__toString());
        self::assertSame('3/5', $chunkConfig->increaseChunk()->__toString());
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function invalidChunks(): iterable
    {
        yield 'no chunks' => [1, 0];
        yield 'chunk zero' => [0, 2];
        yield 'chunk after the last one' => [3, 2];
    }

    #[DataProvider('invalidChunks')]
    public function testInvalidChunkThrows(int $chunkNumber, int $totalChunks): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ChunkConfig($chunkNumber, $totalChunks);
    }
}
