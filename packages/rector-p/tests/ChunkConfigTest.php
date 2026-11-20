<?php

declare(strict_types=1);

namespace Andersundsehr\RectorP\Tests;

use Andersundsehr\RectorP\Dto\ChunkConfig;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChunkConfig::class)]
final class ChunkConfigTest extends TestCase
{
    public function testDefaultIsOneChunk(): void
    {
        self::assertSame('1/1', (new ChunkConfig())->__toString());
    }

    public function testToStringAndIncreaseChunk(): void
    {
        $chunkConfig = new ChunkConfig(2, 5);

        self::assertSame('2/5', $chunkConfig->__toString());
        self::assertSame('3/5', $chunkConfig->increaseChunk()->__toString());
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function validChunks(): iterable
    {
        yield 'one of one' => [1, 1];
        yield 'last chunk' => [2, 2];
    }

    #[DataProvider('validChunks')]
    public function testValidChunk(int $chunkNumber, int $totalChunks): void
    {
        $chunkConfig = new ChunkConfig($chunkNumber, $totalChunks);

        self::assertSame($chunkNumber . '/' . $totalChunks, $chunkConfig->__toString());
    }

    /**
     * @return iterable<string, array{int, int, string, int}>
     */
    public static function invalidChunks(): iterable
    {
        yield 'no chunks' => [1, 0, 'totalChunks must be greater than 0 give:1/0', 1791530006];
        yield 'chunk zero' => [0, 2, 'chunkNumber must be between 1 and totalChunks give:0/2', 1791530007];
        yield 'chunk after the last one' => [3, 2, 'chunkNumber must be between 1 and totalChunks give:3/2', 1791530008];
    }

    #[DataProvider('invalidChunks')]
    public function testInvalidChunkThrows(int $chunkNumber, int $totalChunks, string $message, int $code): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        $this->expectExceptionCode($code);

        new ChunkConfig($chunkNumber, $totalChunks);
    }
}
