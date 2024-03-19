<?php

declare(strict_types=1);

namespace App\Importing;

use App\Importing\Listeners\RecordsImportStat;
use App\Importing\DataTransferObjects\Bookmark;
use App\Importing\DataTransferObjects\ImportBookmarkRequestData;
use App\Importing\DataTransferObjects\ImportStats;
use App\Importing\Enums\ImportBookmarksStatus;
use App\Importing\Enums\ReasonForSkippingBookmark;

/**
 * @phpstan-type Listener (Contracts\ReportableInterface |
 *  Contracts\ImportsStartedListenerInterface |
 *  Contracts\ImportStartedListenerInterface |
 *  Contracts\BookmarkSkippedListenerInterface |
 *  Contracts\ImportsEndedListenerInterface |
 *  Contracts\BookmarkNotProcessedListenerInterface |
 *  Contracts\ImportFailedListenerInterface |
 *  Contracts\BookmarkImportedListenerInterface)
 */
final class EventDispatcher
{
    /**
     * @phpstan-var Listener[]
     */
    private array $listeners;

    /**
     * @phpstan-param Listener[] $listeners
     */
    public function __construct(Contracts\ReportableInterface $logger = null, array $listeners = [])
    {
        $logger = $logger ?: app(RecordsImportStat::class);

        $this->listeners = [$logger, ...$listeners];
    }

    /**
     * @phpstan-param Listener $listener
     */
    public function addListener(object $listener): void
    {
        $this->listeners[] = $listener;
    }

    public function importsStarted(ImportBookmarkRequestData $data): void
    {
        foreach ($this->listeners as $listener) {
            if ( ! $listener instanceof Contracts\ImportsStartedListenerInterface) {
                continue;
            }

            $listener->importsStarted($data);
        }
    }

    public function importStarted(Bookmark $bookmark): void
    {
        foreach ($this->listeners as $listener) {
            if ( ! $listener instanceof Contracts\ImportStartedListenerInterface) {
                continue;
            }

            $listener->importStarted($bookmark);
        }
    }

    public function bookmarkSkipped(ReasonForSkippingBookmark $reason): void
    {
        foreach ($this->listeners as $listener) {
            if ( ! $listener instanceof Contracts\BookmarkSkippedListenerInterface) {
                continue;
            }

            $listener->bookmarkSkipped($reason);
        }
    }

    public function importsEnded(ImportBookmarksOutcome $result): void
    {
        foreach ($this->listeners as $listener) {
            if ( ! $listener instanceof Contracts\ImportsEndedListenerInterface) {
                continue;
            }

            $listener->importsEnded($result);
        }
    }

    public function bookmarkNotProcessed(Bookmark $bookmark): void
    {
        foreach ($this->listeners as $listener) {
            if ( ! $listener instanceof Contracts\BookmarkNotProcessedListenerInterface) {
                continue;
            }

            $listener->bookmarkNotProcessed($bookmark);
        }
    }

    public function importFailed(Bookmark $bookmark, ImportBookmarksStatus $reason): void
    {
        foreach ($this->listeners as $listener) {
            if ( ! $listener instanceof Contracts\ImportFailedListenerInterface) {
                continue;
            }

            $listener->importFailed($bookmark, $reason);
        }
    }

    public function bookmarkImported(Bookmark $bookmark): void
    {
        foreach ($this->listeners as $listener) {
            if ( ! $listener instanceof Contracts\BookmarkImportedListenerInterface) {
                continue;
            }

            $listener->bookmarkImported($bookmark);
        }
    }

    public function getReport(): ImportStats
    {
        return array_filter(
            $this->listeners,
            fn ($listener) => $listener instanceof Contracts\ReportableInterface
        )[0]->getReport();
    }
}
