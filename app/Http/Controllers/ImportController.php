<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Cache\ImportStatRepository;
use App\Exceptions\HttpException;
use App\Http\Requests\ImportBookmarkRequest;
use App\Http\Resources\ImportHistoryResource;
use App\Http\Resources\ImportResource;
use App\Http\Resources\PaginatedResourceCollection;
use App\Models\Import;
use App\Models\ImportHistory;
use App\PaginationData;
use App\Services\ImportBookmarksService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;

final class ImportController
{
    public function imports(Request $request, ImportStatRepository $cache): PaginatedResourceCollection
    {
        $request->validate(PaginationData::new()->asValidationRules());

        /** @var Paginator */
        $result = Import::query()
            ->where('user_id', auth()->id())
            ->latest('id')
            ->simplePaginate($request->input('per_page'), page: $request->input('page'));

        $collection = $result->getCollection()->map(function (Import $import) use ($cache) {
            if ($import->status->isRunning()) {
                $import->statistics = $cache->get($import->import_id);
            }

            return $import;
        });

        return new PaginatedResourceCollection($result->setCollection($collection), ImportResource::class);
    }

    public function store(ImportBookmarkRequest $request, ImportBookmarksService $service): JsonResponse
    {
        $service->fromRequest($request);

        return new JsonResponse(['message' => 'success'], JsonResponse::HTTP_PROCESSING);
    }

    public function history(Request $request, string $importId): PaginatedResourceCollection
    {
        $request->validate(PaginationData::new()->asValidationRules());

        /** @var Import */
        $import = Import::query()->where('import_id', $importId)->first();

        if (is_null($import) || ($import?->user_id !== auth()->id())) {
            throw HttpException::notFound(['message' => 'RecordNotFound']);
        }

        /** @var Paginator */
        $result = ImportHistory::query()
            ->where('import_id', $importId)
            ->latest('id')
            ->simplePaginate($request->input('per_page'), page: $request->input('page'));

        return new PaginatedResourceCollection($result, ImportHistoryResource::class);
    }
}
