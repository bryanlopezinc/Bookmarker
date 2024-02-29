<?php

declare(strict_types=1);

namespace App\Importing\Http\Controllers;

use App\Exceptions\HttpException;
use App\Http\Resources\PaginatedResourceCollection;
use App\PaginationData;
use App\Importing\Services\ImportBookmarksService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use App\Importing\Enums\BookmarkImportStatus as Status;
use App\Importing\Http\Requests\ImportBookmarkRequest;
use App\Importing\Http\Resources\ImportHistoryResource;
use App\Importing\Models\Import;
use App\Importing\Models\ImportHistory;

final class ImportController
{
    public function store(ImportBookmarkRequest $request, ImportBookmarksService $service): JsonResponse
    {
        $service->fromRequest($request);

        return new JsonResponse(['message' => 'success'], JsonResponse::HTTP_PROCESSING);
    }

    public function history(Request $request, string $importId): PaginatedResourceCollection
    {
        $request->validate(['filter' => ['sometimes', 'string', 'in:skipped,failed']]);
        $request->validate(PaginationData::new()->asValidationRules());

        /** @var Import|null */
        $import = Import::query()->where('import_id', $importId)->first();

        if (is_null($import) || ($import->user_id !== auth()->id())) {
            throw HttpException::notFound(['message' => 'RecordNotFound']);
        }

        /** @var Paginator */
        $result = ImportHistory::query()
            ->where('import_id', $importId)
            ->when($request->input('filter'), function ($query, string $filterBy) {
                $query->useIndex('imports_history_import_id_status_index');

                if ($filterBy === 'failed') {
                    $query->whereIn('status', Status::failedCases());
                } else {
                    $query->whereIn('status', Status::skippedCases());
                }
            })
            ->latest('id')
            ->simplePaginate($request->input('per_page'), page: $request->input('page'));

        return new PaginatedResourceCollection($result, ImportHistoryResource::class);
    }
}
