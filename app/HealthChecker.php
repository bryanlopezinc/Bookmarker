<?php

declare(strict_types=1);

namespace App;

use App\Collections\BookmarksCollection;
use App\Contracts\BookmarksHealthRepositoryInterface;
use App\DataTransferObjects\Bookmark;
use App\ValueObjects\ResourceID;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

final class HealthChecker
{
    public function __construct(private BookmarksHealthRepositoryInterface $repository)
    {
    }

    public function ping(BookmarksCollection $bookmarks): void
    {
        if ($bookmarks->isEmpty()) {
            return;
        }

        $responses = $this->makeRequests(
            $bookmarks->filterByIDs($this->repository->whereNotRecentlyChecked($bookmarks->ids()))
        );

        collect($responses)
            ->map(function (Response $response, int $bookmarkID) {
                return new HealthCheckResult(new ResourceID($bookmarkID), $response);
            })
            ->whenNotEmpty(fn (Collection $collection) => $this->repository->update($collection->all()));
    }

    /**
     * @return array<int,Response> Each key in the array is the bookmarkID and value the corresponding http response.
     */
    private function makeRequests(BookmarksCollection $bookmarks): array
    {
        return Http::pool(function (Pool $pool) use ($bookmarks) {
            return collect($bookmarks)->map(function (Bookmark $bookmark) use ($pool) {
                return $pool->as((string)$bookmark->id->value())
                    ->accept('text/html')
                    ->get($bookmark->url->toString());
            })->all();
        });
    }
}
