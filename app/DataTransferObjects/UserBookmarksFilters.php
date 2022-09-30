<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Collections\TagsCollection;
use App\Enums\UserBookmarksSortCriteria;
use App\Http\Requests\FetchUserBookmarksRequest;
use App\PaginationData;
use App\ValueObjects\ResourceID;

final class UserBookmarksFilters extends DataTransferObject
{
    public readonly ResourceID $siteId;
    public readonly bool $wantsOnlyBookmarksFromParticularSite;
    public readonly TagsCollection $tags;
    public readonly bool $wantsBookmarksWithSpecificTags;
    public readonly bool $wantsUntaggedBookmarks;
    public readonly PaginationData $pagination;
    public readonly bool $hasSortCriteria;
    public readonly UserBookmarksSortCriteria $sortCriteria;
    public readonly bool $wantsBooksmarksWithDeadLinks;

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(protected array $attributes)
    {
        foreach ($this->attributes as $key => $value) {
            $this->{$key} = $value;
        }

        parent::__construct();
    }
    
    public static function fromRequest(FetchUserBookmarksRequest $request): self
    {
        $data = [
            'wantsBookmarksWithSpecificTags'  => $request->has('tags'),
            'wantsOnlyBookmarksFromParticularSite' => $request->has('site_id'),
            'wantsUntaggedBookmarks' => $request->boolean('untagged'),
            'pagination' => PaginationData::fromRequest($request),
            'hasSortCriteria' => $request->has('sort'),
            'wantsBooksmarksWithDeadLinks' => $request->boolean('dead_links')
        ];

        $request->whenHas('site_id', function (int $siteId) use (&$data) {
            $data['siteId'] = new ResourceID($siteId);
        });

        $request->whenHas('tags', function (array $tags) use (&$data) {
            $data['tags'] = TagsCollection::make($tags);
        });

        $request->whenHas('sort', function (string $sort) use (&$data) {
            $data['sortCriteria'] = [
                'oldest' => UserBookmarksSortCriteria::OLDEST,
                'newest' => UserBookmarksSortCriteria::NEWEST,
            ][$sort];
        });

        return new self($data);
    }

    /**
     * @param array<string,mixed> $request
     *
     * ```php
     *  $request = [
     *    'tag' => array<string>,
     *     'siteId' => App\ValueObjects\ResourceId::class,
     *     'page' => int,
     *     'per_page' => int,
     *     'untagged' => bool,
     *      'sortBy' => 'oldest' | 'newest',
     *      'dead_links' => bool
     *  ]
     * ```
     */
    public static function fromArray(array $request): self
    {
        $data = [
            'wantsBookmarksWithSpecificTags' => $hasTag = array_key_exists('tags', $request),
            'wantsOnlyBookmarksFromParticularSite' => $hasSiteId = array_key_exists('siteId', $request),
            'wantsUntaggedBookmarks' => $request['untagged'] ?? false,
            'pagination' => new PaginationData($request['page'] ?? 1, $request['per_page'] ?? PaginationData::DEFAULT_PER_PAGE),
            'hasSortCriteria' => $hasSortCriteria = array_key_exists('sortBy', $request),
            'wantsBooksmarksWithDeadLinks' => isset($request['dead_links']),
        ];

        if ($hasSiteId) {
            $data['siteId'] = $request['siteId'];
        }

        if ($hasTag) {
            $data['tags'] = TagsCollection::make($request['tags']);
        }

        if ($hasSortCriteria) {
            $data['sortCriteria'] = match ($request['sortBy']) {
                'oldest' => UserBookmarksSortCriteria::OLDEST,
                'newest' => UserBookmarksSortCriteria::NEWEST,
            };
        }

        return new self($data);
    }

    public function hasAnyFilter(): bool
    {
        return count(array_filter([
            $this->wantsOnlyBookmarksFromParticularSite,
            $this->wantsBookmarksWithSpecificTags,
            $this->wantsUntaggedBookmarks,
            $this->hasSortCriteria,
            $this->wantsBooksmarksWithDeadLinks
        ])) > 0;
    }
}
