<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\IdGeneratorInterface;
use App\Importing\DataTransferObjects\ImportedBookmark;
use App\Enums\BookmarkCreationSource;
use App\ValueObjects\Url;
use App\Http\Requests\CreateOrUpdateBookmarkRequest;
use App\Jobs\UpdateBookmarkWithHttpResponse;
use App\Models\Bookmark;
use App\Models\Source;
use App\Repositories\TagRepository;
use App\Utils\UrlHasher;
use Exception;

class CreateBookmarkService
{
    private TagRepository $tagRepository;
    private IdGeneratorInterface $idGenerator;

    public function __construct(TagRepository $tagRepository = null, IdGeneratorInterface $idGenerator = null)
    {
        $this->tagRepository = $tagRepository ??= new TagRepository();
        $this->idGenerator = $idGenerator ??= app(IdGeneratorInterface::class);
    }

    public function fromRequest(CreateOrUpdateBookmarkRequest $request): void
    {
        $url = new Url($request->validated('url'));

        $source = Source::query()->firstOrCreate(
            ['host' => $url->getHost()],
            ['name' => $url->toString(), 'public_id' => $this->idGenerator->generate()]
        );

        $bookmark = Bookmark::query()->create([
            'public_id'               => $this->idGenerator->generate(),
            'title'                   => $request->validated('title', $url->toString()),
            'has_custom_title'        => $request->has('title'),
            'url'                     => $url->toString(),
            'preview_image_url'       => null,
            'description'             => $request->validated('description'),
            'description_set_by_user' => $request->has('description'),
            'user_id'                 => auth()->id(),
            'source_id'               => $source->id,
            'created_at'              => now(),
            'url_canonical'           => $url->toString(),
            'url_canonical_hash'      => (new UrlHasher())->hashUrl($url),
            'resolved_url'            => $url->toString(),
            'created_from'            => BookmarkCreationSource::HTTP
        ]);

        $this->tagRepository->attach($request->validated('tags', []), $bookmark);

        $this->dispatchEvents($bookmark);
    }

    private function dispatchEvents(Bookmark $bookmark): void
    {
        UpdateBookmarkWithHttpResponse::dispatch($bookmark);
    }

    public function fromImport(ImportedBookmark $importedBookmark): void
    {
        if (count($importedBookmark->tags) > setting('MAX_BOOKMARK_TAGS')) {
            throw new Exception('Bookmark contains Too many tags.');
        }

        $hasher = new UrlHasher();

        $source = Source::query()->firstOrCreate(
            ['host' => $importedBookmark->url->getHost()],
            ['name' => $importedBookmark->url->toString(), 'public_id' => $this->idGenerator->generate()]
        );

        $bookmark = Bookmark::query()->create([
            'public_id'               => $this->idGenerator->generate(),
            'title'                   => $importedBookmark->url->toString(),
            'has_custom_title'        => false,
            'url'                     => $importedBookmark->url->toString(),
            'preview_image_url'       => null,
            'description'             => null,
            'description_set_by_user' => false,
            'user_id'                 => $importedBookmark->userId,
            'source_id'               => $source->id,
            'created_at'              => $importedBookmark->createdOn,
            'url_canonical'           => $importedBookmark->url->toString(),
            'url_canonical_hash'      => $hasher->hashUrl($importedBookmark->url),
            'resolved_url'            => $importedBookmark->url->toString(),
            'created_from'            => $importedBookmark->importSource->toBookmarkCreationSource()
        ]);

        $this->tagRepository->attach($importedBookmark->tags, $bookmark);

        $this->dispatchEvents($bookmark);
    }

    public function fromMail(Url $url, int $userId): Bookmark
    {
        $hasher = new UrlHasher();

        $source = Source::query()->firstOrCreate(
            ['host' => $url->getHost()],
            ['name' => $url->toString(), 'public_id' => $this->idGenerator->generate()]
        );

        $bookmark = Bookmark::query()->create([
            'public_id'               => $this->idGenerator->generate(),
            'title'                   => $url->toString(),
            'has_custom_title'        => false,
            'url'                     => $url->toString(),
            'preview_image_url'       => null,
            'description'             => null,
            'description_set_by_user' => false,
            'user_id'                 => $userId,
            'source_id'               => $source->id,
            'created_at'              => now(),
            'url_canonical'           => $url->toString(),
            'url_canonical_hash'      => $hasher->hashUrl($url),
            'resolved_url'            => $url->toString(),
            'created_from'            => BookmarkCreationSource::EMAIL
        ]);

        $this->dispatchEvents($bookmark);

        return $bookmark;
    }
}
