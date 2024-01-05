<?php

declare(strict_types=1);

namespace App\Import;

use App\Import\Listeners\RecordsImportStat;

final class EventDispatcher
{
    /**
     * @var array<object>
     */
    private array $listeners;

    /**
     * @param array<object> $listeners
     */
    public function __construct(Contracts\ReportableInterface $logger = null, array $listeners = [])
    {
        $logger = $logger ?: app(RecordsImportStat::class);

        $this->listeners = [$logger, ...$listeners];
    }

    public function addListener(object $listener): void
    {
        $this->listeners[] = $listener;
    }

    public function importsStarted(ImportBookmarkRequestData $data): void
    {
        foreach ($this->listeners as $listener) {
            if (!$listener instanceof Contracts\ImportsStartedListenerInterface) {
                continue;
            }

            $listener->importsStarted($data);
        }
    }

    public function importStarted(Bookmark $bookmark): void
    {
        foreach ($this->listeners as $listener) {
            if (!$listener instanceof Contracts\ImportStartedListenerInterface) {
                continue;
            }

            $listener->importStarted($bookmark);
        }
    }

    public function bookmarkSkipped(ReasonForSkippingBookmark $reason): void
    {
        foreach ($this->listeners as $listener) {
            if (!$listener instanceof Contracts\BookmarkSkippedListenerInterface) {
                continue;
            }

            $listener->bookmarkSkipped($reason);
        }
    }

    public function importsEnded(ImportBookmarksOutcome $result): void
    {
        foreach ($this->listeners as $listener) {
            if (!$listener instanceof Contracts\ImportsEndedListenerInterface) {
                continue;
            }

            $listener->importsEnded($result);
        }
    }

    public function bookmarkNotProcessed(Bookmark $bookmark): void
    {
        foreach ($this->listeners as $listener) {
            if (!$listener instanceof Contracts\BookmarkNotProcessedListenerInterface) {
                continue;
            }

            $listener->bookmarkNotProcessed($bookmark);
        }
    }

    public function importFailed(Bookmark $bookmark, ImportBookmarksStatus $reason): void
    {
        foreach ($this->listeners as $listener) {
            if (!$listener instanceof Contracts\ImportFailedListenerInterface) {
                continue;
            }

            $listener->importFailed($bookmark, $reason);
        }
    }

    public function bookmarkImported(Bookmark $bookmark): void
    {
        foreach ($this->listeners as $listener) {
            if (!$listener instanceof Contracts\BookmarkImportedListenerInterface) {
                continue;
            }

            $listener->bookmarkImported($bookmark);
        }
    }

    public function getReport(): ImportStats
    {
        return $this->listeners[0]->getReport(); //@phpstan-ignore-line
    }
}
