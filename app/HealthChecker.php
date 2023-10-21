<?php

declare(strict_types=1);

namespace App;

use App\Contracts\BookmarksHealthRepositoryInterface;
use App\Models\Bookmark;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

final class HealthChecker
{
    public function __construct(private BookmarksHealthRepositoryInterface $repository)
    {
    }

    /**
     * @param iterable<Bookmark>
     */
    public function ping(iterable $bookmarks): void
    {
        $bookmarks = collect($bookmarks);

        $bookmarkIds = collect($bookmarks->all())->pluck('id');

        if ($bookmarkIds->isEmpty()) {
            return;
        }

        $unChecked = $this->repository->whereNotRecentlyChecked($bookmarkIds->all());

        $responses = $this->makeRequests(
            $bookmarks->filter(fn (Bookmark $bookmark) => in_array($bookmark->id, $unChecked))->all()
        );

        collect($responses)
            ->map(function (Response $response, int $bookmarkID) {
                return new HealthCheckResult($bookmarkID, $response);
            })
            ->whenNotEmpty(fn (Collection $collection) => $this->repository->update($collection->all()));
    }

    /**
     * @return array<int,Response> Each key in the array is the bookmarkID and value the corresponding http response.
     */
    private function makeRequests(array $bookmarks): array
    {
        return Http::pool(function (Pool $pool) use ($bookmarks) {
            return collect($bookmarks)->map(function (Bookmark $bookmark) use ($pool) {
                return $pool->as((string)$bookmark->id)
                    ->accept('text/html')
                    ->get($bookmark->url);
            })->all();
        });
    }
}
