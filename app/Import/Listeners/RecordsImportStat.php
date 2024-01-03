<?php

declare(strict_types=1);

namespace App\Import\Listeners;

use App\Cache\ImportStatRepository;
use App\Import\ImportBookmarkRequestData;
use App\Import\Bookmark;
use App\Import\Contracts;
use App\Import\ImportBookmarksStatus;
use App\Import\ImportStats;
use App\Import\ReasonForSkippingBookmark;

final class RecordsImportStat implements
    Contracts\ImportsStartedListenerInterface,
    Contracts\ImportStartedListenerInterface,
    Contracts\ImportEndedListenerInterface,
    Contracts\BookmarkSkippedListenerInterface,
    contracts\ReportableInterface,
    Contracts\BookmarkNotProcessedListenerInterface,
    Contracts\ImportFailedListenerInterface,
    Contracts\BookmarkImportedListenerInterface
{
    private string $importId;
    private int $totalImported = 0;
    private int $totalFound = 0;
    private int $totalSkipped = 0;
    private int $totalUnProcessed = 0;
    private int $totalFailed = 0;

    public function __construct(private readonly ImportStatRepository $repository)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function importsStarted(ImportBookmarkRequestData $data): void
    {
        $this->importId = $data->importId();
    }

    /**
     * {@inheritdoc}
     */
    public function bookmarkNotProcessed(Bookmark $bookmark): void
    {
        $this->totalUnProcessed++;

        $this->save();
    }

    /**
     * {@inheritdoc}
     */
    public function importFailed(Bookmark $bookmark, ImportBookmarksStatus $reason): void
    {
        $this->totalFailed++;

        $this->save();
    }

    /**
     * {@inheritdoc}
     */
    public function importStarted(Bookmark $bookmark): void
    {
        $this->totalFound++;

        $this->save();
    }

    private function save(): void
    {
        $this->repository->put(
            $this->importId,
            new ImportStats(
                $this->totalImported,
                $this->totalSkipped,
                $this->totalFound,
                $this->totalUnProcessed,
                $this->totalFailed
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function bookmarkImported(Bookmark $bookmark): void
    {
        $this->totalImported++;

        $this->save();
    }

    /**
     * {@inheritdoc}
     */
    public function bookmarkSkipped(ReasonForSkippingBookmark $reason): void
    {
        $this->totalSkipped++;

        $this->save();
    }

    /**
     * {@inheritdoc}
     */
    public function getReport(): ImportStats
    {
        return $this->repository->get($this->importId);
    }
}
