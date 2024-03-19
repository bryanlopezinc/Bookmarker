<?php

declare(strict_types=1);

namespace App\Importing\Listeners;

use App\Importing\DataTransferObjects\ImportBookmarkRequestData;
use App\Importing\DataTransferObjects\Bookmark;
use App\Importing\Enums\BookmarkImportStatus;
use App\Importing\Contracts;
use App\Importing\Enums\ReasonForSkippingBookmark;
use App\Importing\ImportBookmarksOutcome;
use App\Importing\Enums\ImportBookmarksStatus;
use App\Importing\Models\ImportHistory;

final class StoresImportHistory implements
    Contracts\ImportStartedListenerInterface,
    Contracts\ImportsEndedListenerInterface,
    Contracts\BookmarkSkippedListenerInterface,
    Contracts\BookmarkImportedListenerInterface,
    Contracts\ImportFailedListenerInterface,
    Contracts\BookmarkNotProcessedListenerInterface
{
    private ImportBookmarkRequestData $data;
    private array $pending = [];
    private array $current = [];

    public function __construct(ImportBookmarkRequestData $data)
    {
        $this->data = $data;
    }

    /**
     * {@inheritdoc}
     */
    public function importStarted(Bookmark $bookmark): void
    {
        $this->persist();

        if ( ! empty($this->current)) {
            $this->pending[] = $this->current;
        }

        $tagsData = [
            'invalid'  => $bookmark->tags->invalid(),
            'resolved' => $this->resolveTags($bookmark),
            'found'    => count($bookmark->tags->all())
        ];

        $this->current = [
            'import_id'            => $this->data->importId(),
            'url'                  => $bookmark->url,
            'document_line_number' => $bookmark->lineNumber,
            'tags'                 => json_encode($tagsData, JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function bookmarkNotProcessed(Bookmark $bookmark): void
    {
        $this->current = [];
    }

    private function persist(bool $force = false): void
    {
        if (empty($this->pending)) {
            return;
        }

        if (count($this->pending) >= 200 || $force) {
            ImportHistory::insert($this->pending);

            $this->pending = [];
        }
    }

    private function resolveTags(Bookmark $bookmark): array
    {
        $option = $this->data->getOption();
        $userDefinedTags = collect($option->tags());
        $bookmarkTags = $bookmark->tags->valid()->take(setting('MAX_BOOKMARK_TAGS'));

        if ( ! $option->includeImportFileTags()) {
            return $userDefinedTags->all();
        }

        if (count($tags = $bookmarkTags->merge($userDefinedTags)->unique()->values()) <= setting('MAX_BOOKMARK_TAGS')) {
            return $tags->all();
        }

        if ($option->ignoreAllTagsOnTagsMergeOverflow()) {
            return [];
        }

        return $option->getMergeTagsStrategy()->merge($userDefinedTags, $bookmarkTags)->unique()->values()->all();
    }

    /**
     * {@inheritdoc}
     */
    public function importsEnded(ImportBookmarksOutcome $result): void
    {
        if ( ! empty($this->current)) {
            $this->pending[] = $this->current;
            $this->current = [];
        }

        $this->persist(true);
    }

    /**
     * {@inheritdoc}
     */
    public function bookmarkSkipped(ReasonForSkippingBookmark $reason): void
    {
        $this->current['status'] = BookmarkImportStatus::fromSkippedOutcome($reason)->value;
    }

    /**
     * {@inheritdoc}
     */
    public function bookmarkImported(Bookmark $bookmark): void
    {
        $this->current['status'] = BookmarkImportStatus::SUCCESS->value;
    }

    /**
     * {@inheritdoc}
     */
    public function importFailed(Bookmark $bookmark, ImportBookmarksStatus $reason): void
    {
        $this->current['status'] = BookmarkImportStatus::fromFailedOutcome($reason)->value;
    }
}
