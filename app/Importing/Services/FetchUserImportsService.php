<?php

declare(strict_types=1);

namespace App\Importing\Services;

use App\Importing\Repositories\ImportStatRepository;
use App\Importing\Enums\ImportBookmarksStatus as Status;
use App\Importing\Models\Import;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;

final class FetchUserImportsService
{
    public function __construct(private readonly ImportStatRepository $cache)
    {
    }

    /**
     * @return Paginator<Import>
     */
    public function fromRequest(Request $request): Paginator
    {
        /** @var Paginator */
        $userImports = Import::query()
            ->where('user_id', auth()->id())
            ->when($request->input('filter'), function ($query, string $filterBy) {
                if ($filterBy === 'failed') {
                    $query->whereIn('status', Status::failedCases());
                } else {
                    $query->where('status', Status::fromRequest($filterBy));
                }
            })->latest('id')
            ->simplePaginate($request->input('per_page'), page: $request->input('page'));

        $collection = $userImports->getCollection()->map(function (Import $import) {
            if ($import->status->isRunning()) {
                $import->statistics = $this->cache->get($import->id);
            }

            return $import;
        });

        return $userImports->setCollection($collection);
    }
}
