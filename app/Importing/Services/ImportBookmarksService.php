<?php

declare(strict_types=1);

namespace App\Importing\Services;

use App\Contracts\IdGeneratorInterface;
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
use App\Models\User;

final class ImportBookmarksService
{
    public function __construct(private readonly Filesystem $filesystem, private readonly IdGeneratorInterface $idGenerator)
    {
    }

    public function fromRequest(ImportBookmarkRequest $request): void
    {
        $userID = User::fromRequest($request)->id;

        $this->filesystem->put($request->allFiles()['html']->getContent(), $filename = Str::uuid()->toString());

        // Remove the file from the request data because
        // \Illuminate\Http\UploadedFile cannot be serialized
        // and will throw an exception when trying to queue ImportBookmarks job.
        $validated = collect($request->validated())
            ->reject(fn ($value) => $value instanceof UploadedFile)
            ->all();

        $import = Import::query()->create([
            'public_id'  => $this->idGenerator->generate(),
            'user_id'    => $userID,
            'status'     => ImportBookmarksStatus::PENDING,
            'statistics' => new ImportStats()
        ]);

        dispatch(new ImportBookmarks(
            new ImportBookmarkRequestData(
                $import->id,
                ImportSource::fromRequest($request),
                $userID,
                $validated,
                $filename
            )
        ));
    }
}
