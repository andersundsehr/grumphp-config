<?php

declare(strict_types=1);

namespace Andersundsehr\RectorP\Dto;

use Stringable;
use InvalidArgumentException;

final readonly class ChunkConfig implements Stringable
{
    public function __construct(
        public int $chunkNumber = 1,
        public int $totalChunks = 1,
    ) {
        if ($this->totalChunks < 1) {
            throw new InvalidArgumentException('totalChunks must be greater than 0 give:' . $this, 1791530006);
        }

        if ($this->chunkNumber < 1) {
            throw new InvalidArgumentException('chunkNumber must be between 1 and totalChunks give:' . $this, 1791530007);
        }

        if ($this->chunkNumber > $this->totalChunks) {
            throw new InvalidArgumentException('chunkNumber must be between 1 and totalChunks give:' . $this, 1791530008);
        }
    }

    public function __toString(): string
    {
        return $this->chunkNumber . '/' . $this->totalChunks;
    }

    public function increaseChunk(): self
    {
        return new self($this->chunkNumber + 1, $this->totalChunks);
    }
}
