<?php

namespace Tests\Unit\Repositories;

use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\DataTransferObjects\Builders\SiteBuilder;
use App\Models\Bookmark;
use App\Models\Tag;
use App\Models\Taggable;
use App\Repositories\CreateBookmarkRepository;
use Database\Factories\BookmarkFactory;
use Database\Factories\SiteFactory;
use Database\Factories\TagFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class CreateBookmarkRepositoryTest extends TestCase
{
    use WithFaker;

    private CreateBookmarkRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(CreateBookmarkRepository::class);
    }

    public function testWillSaveBookmarks(): void
    {
        /** @var Bookmark $model */
        $bookmark = BookmarkBuilder::fromModel($model = BookmarkFactory::new()->make())
            ->site(SiteBuilder::fromModel(SiteFactory::new()->create())->build())
            ->bookmarkedById($model['user_id'])
            ->tags([$tag = $this->faker->word])
            ->bookmarkedOn((string) now())
            ->canonicalUrl($model->url_canonical)
            ->canonicalUrlHash($model->url_canonical_hash)
            ->resolvedUrl($model->resolved_url)
            ->build();

        $result = $this->repository->create($bookmark);

        $this->assertDatabaseHas(Tag::class, ['name' => $tag]);

        $this->assertDatabaseHas(Taggable::class, [
            'taggable_id' => $result->id->toInt(),
            'taggable_type' => Taggable::BOOKMARK_TYPE,
            'tag_id' => Tag::query()->where('name', $tag)->first()->id,
        ]);
    }

    public function testWillAttachExistingTagsToNewBookmark(): void
    {
        /** @var Bookmark $model */
        $bookmark = BookmarkBuilder::fromModel($model = BookmarkFactory::new()->make())
            ->site(SiteBuilder::fromModel(SiteFactory::new()->create())->build())
            ->bookmarkedById($model['user_id'])
            ->tags([$tag = TagFactory::new()->make()->name, $this->faker->word])
            ->bookmarkedOn((string) now())
            ->canonicalUrl($model->url_canonical)
            ->canonicalUrlHash($model->url_canonical_hash)
            ->resolvedUrl($model->resolved_url)
            ->build();

        $tagModel = Tag::query()->create([
            'name' => $tag,
            'created_by' => $model['user_id']
        ]);

        $result = $this->repository->create($bookmark);

        $this->assertDatabaseHas(Taggable::class, [
            'taggable_id' => $result->id->toInt(),
            'tag_id' => $tagModel->id
        ]);
    }
}
