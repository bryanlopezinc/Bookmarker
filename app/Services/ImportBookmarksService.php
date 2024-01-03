<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ImportSource;
use Illuminate\Support\Str;
use App\Import\Filesystem;
use App\Jobs\ImportBookmarks;
use Illuminate\Http\UploadedFile;
use App\Import\ImportBookmarkRequestData;
use App\Http\Requests\ImportBookmarkRequest;
use App\Import\ImportBookmarksStatus;
use App\Import\ImportStats;
use App\Models\Import;

final class ImportBookmarksService
{
    public function __construct(private Filesystem $filesystem)
    {
    }

    public function fromRequest(ImportBookmarkRequest $request): void
    {
        $userID = auth()->id();

        $importId = Str::uuid()->toString();

        $this->filesystem->put($request->file('html')->getContent(), $userID, $importId);

        // Remove the file from the request data because
        // \Illuminate\Http\UploadedFile cannot be serialized
        // and will throw an exception when trying to queue ImportBookmarks job.
        $validated = collect($request->validated())->reject(fn ($value) => $value instanceof UploadedFile)->all();

        Import::query()->create([
            'import_id' => $importId,
            'user_id' => $userID,
            'status'  => ImportBookmarksStatus::PENDING,
            'statistics' => new ImportStats()
        ]);

        dispatch(new ImportBookmarks(
            new ImportBookmarkRequestData($importId, ImportSource::fromRequest($request), $userID, $validated)
        ));
    }
}
