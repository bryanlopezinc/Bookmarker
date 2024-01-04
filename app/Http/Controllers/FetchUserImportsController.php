<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\ImportResource;
use App\Http\Resources\PaginatedResourceCollection;
use App\PaginationData;
use App\Services\FetchUserImportsService;
use Illuminate\Http\Request;

final class FetchUserImportsController
{
    public function __invoke(Request $request, FetchUserImportsService $service): PaginatedResourceCollection
    {
        $request->validate([
            'filter' => ['sometimes', 'string', 'in:pending,success,importing,failed']
        ], ...[PaginationData::new()->asValidationRules()]);

        return new PaginatedResourceCollection($service->fromRequest($request), ImportResource::class);
    }
}
