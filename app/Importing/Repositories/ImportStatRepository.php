<?php

declare(strict_types=1);

namespace App\Importing\Repositories;

use App\Importing\DataTransferObjects\ImportStats;
use Illuminate\Contracts\Cache\Repository;

final class ImportStatRepository
{
    public function __construct(private readonly Repository $repository, private readonly int $timeToLive)
    {
    }

    public function put(int $importId, ImportStats $importStats): void
    {
        $this->repository->put($this->key($importId), $importStats->toArray(), $this->timeToLive);
    }

    private function key(int $importId): string
    {
        return "im_stats:{$importId}";
    }

    public function delete(int $importId): void
    {
        $this->repository->delete($this->key($importId));
    }

    public function get(int $importId): ImportStats
    {
        $stats = $this->repository->get($this->key($importId), new ImportStats());

        if (is_array($stats)) {
            return ImportStats::fromArray($stats);
        }

        return $stats;
    }
}
