<?php

declare(strict_types=1);

namespace App\Importing\Listeners;

use App\Importing\Repositories\ImportStatRepository;
use App\Importing\ImportBookmarksOutcome;
use App\Importing\Contracts;
use App\Importing\DataTransferObjects\ImportBookmarkRequestData;
use App\Importing\Filesystem;

final class ClearsDataAfterImport implements Contracts\ImportsEndedListenerInterface
{
    private readonly ImportBookmarkRequestData $data;
    private readonly Filesystem $filesystem;
    private readonly ImportStatRepository $importStatRepository;

    public function __construct(
        ImportBookmarkRequestData $data,
        Filesystem $filesystem =  null,
        ImportStatRepository $importStatRepository = null
    ) {
        $this->data = $data;
        $this->filesystem = $filesystem ?: app(Filesystem::class);
        $this->importStatRepository = $importStatRepository ?: app(ImportStatRepository::class);
    }

    /**
     * {@inheritdoc}
     */
    public function importsEnded(ImportBookmarksOutcome $result): void
    {
        $this->filesystem->delete($this->data->getFileName());

        $this->importStatRepository->delete($this->data->importId());
    }
}
