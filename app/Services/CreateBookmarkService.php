<?php

declare(strict_types=1);

namespace App\Services;

use App\ValueObjects\Url;
use App\ValueObjects\UserID;
use App\Http\Requests\CreateBookmarkRequest;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\DataTransferObjects\Builders\SiteBuilder;
use App\Jobs\UpdateBookmarkInfo;
use App\Repositories\CreateBookmarkRepository as Repository;

final class CreateBookmarkService
{
    public function __construct(private Repository $repository)
    {
    }

    public function fromRequest(CreateBookmarkRequest $request): void
    {
        $url = new Url($request->validated('url'));

        $bookmark = BookmarkBuilder::new()
            ->title($request->validated('title', $url->value))
            ->hasCustomTitle($request->has('title'))
            ->url($url->value)
            ->previewImageUrl('')
            ->description($request->input('description', ''))
            ->descriptionWasSetByUser($request->has('description'))
            ->bookmarkedById(UserID::fromAuthUser()->toInt())
            ->site(SiteBuilder::new()->domainName($url->getHostName())->name($url->value)->build())
            ->tags($request->validated('tags', []))
            ->build();

        UpdateBookmarkInfo::dispatch($this->repository->create($bookmark));
    }
}
