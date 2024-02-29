<?php

declare(strict_types=1);

namespace App\Importing\Listeners;

use App\Importing\Repositories\ImportStatRepository;
use App\Importing\ImportBookmarksOutcome;
use App\Importing\Contracts;
use App\Importing\Filesystem;

final class ClearsDataAfterImport implements Contracts\ImportsEndedListenerInterface
{
    private readonly int $userId;
    private readonly string $importId;
    private readonly Filesystem $filesystem;
    private readonly ImportStatRepository $importStatRepository;

    public function __construct(
        int $userId,
        string $importId,
        Filesystem $filesystem =  null,
        ImportStatRepository $importStatRepository = null
    ) {
        $this->userId = $userId;
        $this->importId = $importId;
        $this->filesystem = $filesystem ?: app(Filesystem::class);
        $this->importStatRepository = $importStatRepository ?: app(ImportStatRepository::class);
    }

    /**
     * {@inheritdoc}
     */
    public function importsEnded(ImportBookmarksOutcome $result): void
    {
        $this->filesystem->delete($this->userId, $this->importId);

        $this->importStatRepository->delete($this->importId);
    }
}
