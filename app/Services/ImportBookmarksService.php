<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\ImportData;
use App\Enums\ImportSource;
use App\Http\Requests\ImportBookmarkRequest;
use App\Importers\FilesystemInterface;
use App\Jobs\ImportBookmarks;
use App\ValueObjects\UserID;
use App\ValueObjects\Uuid;

final class ImportBookmarksService
{
    public function __construct(private FilesystemInterface $filesystem)
    {
    }

    public function fromRequest(ImportBookmarkRequest $request): void
    {
        $validated = $request->validated();
        $userID = UserID::fromAuthUser();
        $requestID = Uuid::generate();

        foreach ($this->inputFileTypes() as $input) {
            if (!array_key_exists($input, $validated)) {
                continue;
            }

            $this->filesystem->put($request->file($input)->getContent(), $userID, $requestID);

            // Remove the file from the request data because
            // \Illuminate\Http\UploadedFile cannot be serialized
            // and will throw an exception when trying to queue ImportBookmarks job.
            unset($validated[$input]);
        }

        dispatch(new ImportBookmarks(
            new ImportData($requestID, ImportSource::fromRequest($request), $userID, $validated)
        ));
    }

    /**
     * Get the request inputs that are file type.
     *
     * @return array<string>
     */
    private function inputFileTypes(): array
    {
        return ['html', 'pocket_export_file', 'safari_html', 'instapaper_html'];
    }
}
