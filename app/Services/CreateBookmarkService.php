<?php

declare(strict_types=1);

namespace App\Services;

use App\ValueObjects\Url;
use App\ValueObjects\UserId;
use App\Http\Requests\CreateOrUpdateBookmarkRequest;
use App\Jobs\UpdateBookmarkWithHttpResponse;
use App\Models\Bookmark;
use App\Models\Source;
use App\Repositories\TagRepository;
use App\Utils\UrlHasher;
use Illuminate\Http\Resources\MissingValue;
use Symfony\Component\HttpFoundation\ParameterBag;

class CreateBookmarkService
{
    private TagRepository $tagRepository;

    public function __construct(TagRepository $tagRepository = null)
    {
        $this->tagRepository = $tagRepository ?: new TagRepository();
    }

    public function fromRequest(CreateOrUpdateBookmarkRequest $request): void
    {
        $data = [
            'url'                  => new Url($request->validated('url')),
            'createdOn'            => (string)now(),
            'userID'               => UserId::fromAuthUser()->value(),
            'tags'                 => $request->validated('tags', new MissingValue()),
            'title'                => $request->validated('title', new MissingValue()),
            'description'          =>  $request->input('description', new MissingValue()),
            'descriptionSetByUser' => $request->has('description'),
            'hasCustomTitle'       => $request->has('title'),
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
     *     'url'                  => App\ValueObjects\Url, //Required
     *     'createdOn'            => string, // Required
     *     'userID'               => int //Required
     *     'tags'                 => array<string>, //Optional
     *     'title'                => string, //Optional,
     *     'description'          => string //Optional,
     *      descriptionSetByUser' => bool //optional,
     *      hasCustomTitle'       => bool //optional
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
         * @var int $userID
         * */
        [$url, $userID] = [$attributes->get('url'), $attributes->get('userID')];

        /** @var Source */
        $source = Source::query()->firstOrCreate(['host' => $url->getHost()], ['name' => $url->toString()]);

        /** @var Bookmark */
        $bookmark = Bookmark::query()->create([
            'title'                   => $attributes->get('title', $url->toString()),
            'has_custom_title'        => $attributes->get('hasCustomTitle', false),
            'url'                     => $url->toString(),
            'preview_image_url'       => null,
            'description'             => $attributes->get('description'),
            'description_set_by_user' => $attributes->get('descriptionSetByUser', false),
            'user_id'                 => $userID,
            'source_id'               => $source->id,
            'created_at'              => $attributes->get('createdOn'),
            'url_canonical'           => $url->toString(),
            'url_canonical_hash'      => (new UrlHasher())->hashUrl($url),
            'resolved_url'            => $url->toString(),
        ]);

        $this->tagRepository->attach($attributes->get('tags', []), $bookmark);

        UpdateBookmarkWithHttpResponse::dispatch($bookmark);
    }
}
