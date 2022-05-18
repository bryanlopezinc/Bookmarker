<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Collections\TagsCollection;
use App\Enums\SortCriteria;
use App\Http\Requests\FetchUserBookmarksRequest;
use App\PaginationData;
use App\ValueObjects\ResourceID;
use App\ValueObjects\Tag;
use App\ValueObjects\UserID;

final class UserBookmarksFilters extends DataTransferObject
{
    public readonly UserID $userId;
    public readonly ResourceID $siteId;
    public readonly bool $hasCustomSite;
    public readonly TagsCollection $tags;
    public readonly bool $hasTags;
    public readonly bool $wantsUntaggedBookmarks;
    public readonly PaginationData $pagination;
    public readonly bool $hasSortCriteria;
    public readonly SortCriteria $sortCriteria;

    public static function fromRequest(FetchUserBookmarksRequest $request): self
    {
        $data = [
            'userId' => UserID::fromAuthUser(),
            'hasTags'  => $request->has('tags'),
            'hasCustomSite' => $request->has('site_id'),
            'wantsUntaggedBookmarks' => $request->boolean('untagged'),
            'pagination' => PaginationData::fromRequest($request),
            'hasSortCriteria' => $request->has('sort'),
        ];

        $request->whenHas('site_id', function (int $siteId) use (&$data) {
            $data['siteId'] = new ResourceID($siteId);
        });

        $request->whenHas('tags', function (array $tags) use (&$data) {
            $data['tags'] = TagsCollection::createFromStrings($tags);
        });

        $request->whenHas('sort', function (string $sort) use (&$data) {
            $data['sortCriteria'] = match ($sort) {
                'oldest' => SortCriteria::OLDEST,
                'newest' => SortCriteria::NEWEST,
            };
        });

        return new self($data);
    }

    /**
     * @param array<string,mixed> $request
     *
     * ```php
     *  $request = [
     *    'userId' => App\ValueObjects\UserId::class,
     *    'tag' => array<string>,
     *     'siteId' => App\ValueObjects\ResourceId::class,
     *     'page' => int,
     *     'per_page' => int,
     *     'untagged' => bool,
     *      'sortBy' => 'oldest' | 'newest'
     *  ]
     * ```
     */
    public static function fromArray(array $request): self
    {
        $data = [
            'userId' => $request['userId'],
            'hasTags' => $hasTag = array_key_exists('tags', $request),
            'hasCustomSite' => $hasSiteId = array_key_exists('siteId', $request),
            'wantsUntaggedBookmarks' => $request['untagged'] ?? false,
            'pagination' => new PaginationData($request['page'] ?? 1, $request['per_page'] ?? PaginationData::DEFAULT_PER_PAGE),
            'hasSortCriteria' => $hasSortCriteria = array_key_exists('sortBy', $request),
        ];

        if ($hasSiteId) {
            $data['siteId'] = $request['siteId'];
        }

        if ($hasTag) {
            $data['tags'] = TagsCollection::createFromStrings($request['tags']);
        }

        if ($hasSortCriteria) {
            $data['sortCriteria'] = match ($request['sortBy']) {
                'oldest' => SortCriteria::OLDEST,
                'newest' => SortCriteria::NEWEST,
            };
        }

        return new self($data);
    }
}
