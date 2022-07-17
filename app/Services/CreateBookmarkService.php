<?php

declare(strict_types=1);

namespace App\Services;

use App\ValueObjects\Url;
use App\ValueObjects\UserID;
use App\Http\Requests\CreateBookmarkRequest;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\DataTransferObjects\Builders\SiteBuilder;
use App\Jobs\UpdateBookmarkInfo;
use App\Contracts\CreateBookmarkRepositoryInterface as Repository;
use App\Contracts\UrlHasherInterface;
use Illuminate\Http\Resources\MissingValue;
use Symfony\Component\HttpFoundation\ParameterBag;

final class CreateBookmarkService
{
    public function __construct(private Repository $repository, private UrlHasherInterface $urlHasher)
    {
    }

    public function fromRequest(CreateBookmarkRequest $request): void
    {
        $data = [
            'url' => new Url($request->validated('url')),
            'createdOn' => (string)now(),
            'userID' => UserID::fromAuthUser(),
            'tags' => $request->validated('tags', new MissingValue),
            'title' => $request->validated('title', new MissingValue),
            'description' =>  $request->input('description', new MissingValue),
            'descriptionSetByUser' => $request->has('description'),
            'hasCustomTitle' => $request->has('title'),
        ];

        $this->fromArray(
            array_filter($data, fn (mixed $value) => !$value instanceof MissingValue)
        );
    }

    /**
     * @param array<string,mixed> $data
     *
     * ```php
     *  $data = [
     *     'url' => App\ValueObjects\Url, //Required
     *     'createdOn' => string, // Required
     *      'userID' => App\ValueObjects\UserID //Required
     *     'tags' => array<string>, //Optional
     *     'title' => string, //Optional,
     *     'description' => string //Optional,
     *      'descriptionSetByUser' =>bool //optional,
     *      'hasCustomTitle' => bool //optional
     *  ]
     * ```
     */
    public function fromArray(array $data): void
    {
        $attributes = new ParameterBag($data);

        foreach (['url', 'createdOn', 'userID'] as $requiredAttribute) {
            if (!$attributes->has($requiredAttribute)) {
                throw new \ErrorException("Undefined array key $requiredAttribute");
            }
        }

        /**
         * @var Url $url
         * @var UserID $userID
         * */
        [$url, $userID] = [$attributes->get('url'), $attributes->get('userID')];

        $bookmark = BookmarkBuilder::new()
            ->title($attributes->get('title', $url->value))
            ->hasCustomTitle($attributes->get('hasCustomTitle', false))
            ->url($url->value)
            ->previewImageUrl('')
            ->description($attributes->get('description'))
            ->descriptionWasSetByUser($attributes->get('descriptionSetByUser', false))
            ->bookmarkedById($userID->toInt())
            ->site(SiteBuilder::new()->domainName($url->getHostName())->name($url->value)->build())
            ->tags($attributes->get('tags', []))
            ->bookmarkedOn($attributes->get('createdOn'))
            ->canonicalUrl($url)
            ->canonicalUrlHash($this->urlHasher->hashCanonicalUrl($url))
            ->resolvedUrl($url)
            ->build();

        UpdateBookmarkInfo::dispatch($this->repository->create($bookmark));
    }
}
