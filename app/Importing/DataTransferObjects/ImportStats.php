<?php

declare(strict_types=1);

namespace App\Importing\DataTransferObjects;

final class ImportStats
{
    public function __construct(
        public readonly int $totalImported = 0,
        public readonly int $totalSkipped = 0,
        public readonly int $totalFound = 0,
        public readonly int $totalUnProcessed = 0,
        public readonly int $totalFailed = 0
    ) {
    }

    public static function fromJson(string $json): self
    {
        return self::fromArray(json_decode($json, true, JSON_THROW_ON_ERROR));
    }

    public static function fromArray(array $stats): self
    {
        return new self(
            $stats['totalImported'],
            $stats['totalSkipped'],
            $stats['totalFound'],
            $stats['totalUnProcessed'],
            $stats['totalFailed']
        );
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    public function toArray(): array
    {
        return [
            'totalImported'    => $this->totalImported,
            'totalSkipped'     => $this->totalSkipped,
            'totalFound'       => $this->totalFound,
            'totalUnProcessed' => $this->totalUnProcessed,
            'totalFailed'      => $this->totalFailed
        ];
    }
}
