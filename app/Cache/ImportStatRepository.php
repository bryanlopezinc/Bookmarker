<?php

declare(strict_types=1);

namespace App\Cache;

use App\Import\ImportStats;
use Illuminate\Contracts\Cache\Repository;

final class ImportStatRepository
{
    public function __construct(private readonly Repository $repository, private readonly int $timeToLive)
    {
    }

    public function put(string $importId, ImportStats $importStats): void
    {
        $this->repository->put($importId, $importStats->toArray(), $this->timeToLive);
    }

    public function delete(string $importId): void
    {
        $this->repository->delete($importId);
    }

    public function get(string $importId): ImportStats
    {
        $stats = $this->repository->get($importId, new ImportStats());

        if (is_array($stats)) {
            return ImportStats::fromArray($stats);
        }

        return $stats;
    }
}
