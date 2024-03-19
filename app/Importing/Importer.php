<?php

declare(strict_types=1);

namespace App\Importing;

use App\ValueObjects\Url;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Throwable;
use App\Importing\DataTransferObjects\Bookmark;
use App\Importing\DataTransferObjects\ImportBookmarkRequestData;
use App\Importing\Enums\ImportBookmarksStatus;
use App\Importing\Enums\ReasonForSkippingBookmark;

final class Importer
{
    private HtmlFileIterator $iterator;
    private Filesystem $filesystem;
    private EventDispatcher $event;

    public function __construct(
        HtmlFileIterator $iterator = new HtmlFileIterator(),
        Filesystem $filesystem = null,
        EventDispatcher $event = new EventDispatcher()
    ) {
        $this->iterator = $iterator;
        $this->filesystem = $filesystem ?: app(Filesystem::class);
        $this->event = $event;
    }

    public function import(ImportBookmarkRequestData $importData): ImportBookmarksOutcome
    {
        try {
            $this->event->importsEnded($result = $this->doImport($importData));

            return $result;
        } catch (Throwable $e) {
            $result = ImportBookmarksOutcome::failed(
                ImportBookmarksStatus::FAILED_DUE_TO_SYSTEM_ERROR,
                $this->event->getReport()
            );

            $this->event->importsEnded($result);

            throw $e;
        }
    }

    private function doImport(ImportBookmarkRequestData $importData): ImportBookmarksOutcome
    {
        $userId = $importData->userID();
        $lastFailureReason = null;
        $option = $importData->getOption();

        $this->event->importsStarted($importData);

        if ( ! $this->filesystem->exists($userId, $importData->importId())) {
            throw new FileNotFoundException();
        }

        foreach ($this->iterator->iterate($this->filesystem->get($userId, $importData->importId()), $importData->source()) as $bookmark) {
            $this->event->importStarted($bookmark);

            //We want to keep recording the count of bookmarks and also log the import when import has failed
            //since iterator_count() will close the generator.
            if ($lastFailureReason !== null) {
                $this->event->bookmarkNotProcessed($bookmark);
                continue;
            }

            if ($reasonForFailure = $this->shouldFailImport($bookmark, $option)) {
                $this->event->importFailed($bookmark, $reasonForFailure);

                if ($this->shouldStopImportOnFailure($option)) {
                    $lastFailureReason = $reasonForFailure;
                }

                continue;
            }

            if ($reasonForSkippingBookmark = $this->shouldSkipBookmark($bookmark, $option)) {
                $this->event->bookmarkSkipped($reasonForSkippingBookmark);
                continue;
            }

            $this->event->bookmarkImported($bookmark);
        }

        $stats = $this->event->getReport();

        return $lastFailureReason ?
            ImportBookmarksOutcome::failed($lastFailureReason, $stats) :
            ImportBookmarksOutcome::success($stats);
    }

    private function shouldSkipBookmark(Bookmark $bookmark, Option $option): ReasonForSkippingBookmark|false
    {
        $maxBookmarksTags = setting('MAX_BOOKMARK_TAGS');

        if ($bookmark->tags->hasInvalid() && $option->skipBookmarkIfContainsAnyInvalidTag()) {
            return ReasonForSkippingBookmark::INVALID_TAG;
        }

        if ($bookmark->tags->valid()->merge($option->tags())->count() > $maxBookmarksTags && $option->skipBookmarkOnTagsMergeOverflow()) {
            return ReasonForSkippingBookmark::TAG_MERGE_OVERFLOW;
        }

        if ($bookmark->tags->valid()->count() > $maxBookmarksTags && $option->skipBookmarkIfTagsIsTooLarge()) {
            return ReasonForSkippingBookmark::TAGS_TOO_LARGE;
        }

        return false;
    }

    private function shouldFailImport(Bookmark $bookmark, Option $option): ImportBookmarksStatus|false
    {
        if ( ! Url::isValid($bookmark->url)) {
            return ImportBookmarksStatus::FAILED_DUE_TO_INVALID_BOOKMARK_URL;
        }

        if ($bookmark->tags->hasInvalid() && $option->failImportIfBookmarkContainsAnyInvalidTag()) {
            return ImportBookmarksStatus::FAILED_DUE_TO_INVALID_TAG;
        }

        if ($bookmark->tags->willOverflowWhenMergedWithUserDefinedTags($option->tags()) && $option->failImportOnTagsMergeOverflow()) {
            return ImportBookmarksStatus::FAILED_DUE_TO_MERGE_TAGS_EXCEEDED;
        }

        if ($bookmark->tags->valid()->count() > setting('MAX_BOOKMARK_TAGS') && $option->failImportIfBookmarkTagsIsTooLarge()) {
            return ImportBookmarksStatus::FAILED_DUE_TO_TO_MANY_TAGS;
        }

        return false;
    }

    private function shouldStopImportOnFailure(Option $option): bool
    {
        return $option->failImportIfBookmarkContainsAnyInvalidTag() ||
            $option->failImportOnTagsMergeOverflow() ||
            $option->failImportIfBookmarkTagsIsTooLarge();
    }
}
