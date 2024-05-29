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
use App\Models\Scopes\WherePublicIdScope;
use App\ValueObjects\PublicId\ImportPublicId;

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

        $import = Import::query()
            ->tap(new WherePublicIdScope(ImportPublicId::fromRequest($importId)))
            ->firstOrNew();

        if ( ! $import->exists || ($import->user_id !== auth()->id())) {
            throw HttpException::notFound(['message' => 'RecordNotFound']);
        }

        /** @var Paginator */
        $result = ImportHistory::query()
            ->where('import_id', $import->id)
            ->when($request->input('filter'), function ($query, string $filterBy) {
                if ($filterBy === 'failed') {
                    $failedCases = Status::failedCases();
                    $query->whereBetween('status', [$failedCases[0], end($failedCases)]);
                } else {
                    $skipped = Status::skippedCases();
                    $query->whereBetween('status', [$skipped[0], end($skipped)]);
                }
            })
            ->latest('id')
            ->simplePaginate($request->input('per_page'), page: $request->input('page'));

        return new PaginatedResourceCollection($result, ImportHistoryResource::class);
    }
}
