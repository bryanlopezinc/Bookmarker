<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Http\Requests\FetchUserBookmarksRequest;
use App\PaginationData;
use App\ValueObjects\ResourceID;
use App\ValueObjects\Tag;
use App\ValueObjects\UserID;

final class FetchUserBookmarksRequestData extends DataTransferObject
{
    public readonly UserID $userId;
    public readonly ResourceID $siteId;
    public readonly bool $hasCustomSite;
    public readonly Tag $tag;
    public readonly bool $hasTag;
    public readonly bool $wantsUntaggedBookmarks;
    public readonly PaginationData $pagination;

    public static function fromRequest(FetchUserBookmarksRequest $request): self
    {
        $data = [
            'userId' => UserID::fromAuthUser(),
            'hasTag'  => $request->has('tag'),
            'hasCustomSite' => $request->has('site_id'),
            'wantsUntaggedBookmarks' => $request->boolean('untagged'),
            'pagination' => PaginationData::fromRequest($request)
        ];

        $request->whenHas('site_id', function (int $siteId) use (&$data) {
            $data['siteId'] = new ResourceID($siteId);
        });

        $request->whenHas('tag', function (string $tag) use (&$data) {
            $data['tag'] = new Tag($tag);
        });

        return new self($data);
    }

    /**
     * @param array<string,mixed> $request
     *
     * @key `userId` the authorized userid App\ValueObjects\UserId
     * @key `tag` App\ValueObjects\Tag
     * @key `siteId` App\ValueObjects\ResourceId
     * @key `page` int
     * @key `per_page` int
     * @key untagged bool
     */
    public static function fromArray(array $request): self
    {
        $data = [
            'userId' => $request['userId'],
            'hasTag' => $hasTag = array_key_exists('tag', $request),
            'hasCustomSite' => $hasSiteId = array_key_exists('siteId', $request),
            'wantsUntaggedBookmarks' => $request['untagged'] ?? false,
            'pagination' => new PaginationData($request['page'] ?? 1, $request['per_page'] ?? PaginationData::DEFAULT_PER_PAGE)
        ];

        if ($hasSiteId) {
            $data['siteId'] = $request['siteId'];
        }

        if ($hasTag) {
            $data['tag'] = $request['tag'];
        }

        return new self($data);
    }
}
