<?php

declare(strict_types=1);

namespace App\Importing\Services;

use App\Importing\Enums\ImportSource;
use Illuminate\Support\Str;
use App\Importing\Filesystem;
use App\Importing\Jobs\ImportBookmarks;
use Illuminate\Http\UploadedFile;
use App\Importing\DataTransferObjects\ImportBookmarkRequestData;
use App\Importing\Enums\ImportBookmarksStatus;
use App\Importing\DataTransferObjects\ImportStats;
use App\Importing\Http\Requests\ImportBookmarkRequest;
use App\Importing\Models\Import;
use App\ValueObjects\UserId;

final class ImportBookmarksService
{
    public function __construct(private Filesystem $filesystem)
    {
    }

    public function fromRequest(ImportBookmarkRequest $request): void
    {
        $userID = UserId::fromAuthUser()->value();

        $importId = Str::uuid()->toString();

        $this->filesystem->put($request->allFiles()['html']->getContent(), $userID, $importId);

        // Remove the file from the request data because
        // \Illuminate\Http\UploadedFile cannot be serialized
        // and will throw an exception when trying to queue ImportBookmarks job.
        $validated = collect($request->validated())
            ->reject(fn ($value) => $value instanceof UploadedFile)
            ->all();

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
