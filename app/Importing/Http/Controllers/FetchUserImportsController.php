<?php

declare(strict_types=1);

namespace App\Importing\Http\Controllers;

use App\Http\Resources\PaginatedResourceCollection;
use App\Importing\Http\Resources\ImportResource;
use App\PaginationData;
use App\Importing\Services\FetchUserImportsService;
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
