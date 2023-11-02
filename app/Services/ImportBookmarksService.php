<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ImportSource;
use Illuminate\Support\Str;
use App\ValueObjects\UserId;
use App\Importers\Filesystem;
use App\Jobs\ImportBookmarks;
use Illuminate\Http\UploadedFile;
use App\DataTransferObjects\ImportData;
use App\Http\Requests\ImportBookmarkRequest;

final class ImportBookmarksService
{
    public function __construct(private Filesystem $filesystem)
    {
    }

    public function fromRequest(ImportBookmarkRequest $request): void
    {
        $userID = UserId::fromAuthUser()->value();

        $requestID = Str::uuid()->toString();

        /** @var UploadedFile*/
        $file = match ($request->input('source')) {
            $request::CHROME     => $request->file('html'),
            $request::POCKET     => $request->file('pocket_export_file'),
            $request::SAFARI     => $request->file('safari_html'),
            $request::INSTAPAPER => $request->file('instapaper_html'),
            $request::FIREFOX    => $request->file('firefox_export_file'),
        };

        $this->filesystem->put($file->getContent(), $userID, $requestID);

        // Remove the file from the request data because
        // \Illuminate\Http\UploadedFile cannot be serialized
        // and will throw an exception when trying to queue ImportBookmarks job.
        $validated = collect($request->validated())->reject(fn ($value) => $value instanceof UploadedFile)->all();

        dispatch(new ImportBookmarks(
            new ImportData($requestID, ImportSource::fromRequest($request), $userID, $validated)
        ));
    }
}
