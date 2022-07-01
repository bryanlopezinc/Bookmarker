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
        $bookmark = BookmarkBuilder::fromModel($model = BookmarkFactory::new()->make())
            ->site(SiteBuilder::fromModel(SiteFactory::new()->create())->build())
            ->bookmarkedById($model['user_id'])
            ->tags([$tag = $this->faker->word])
            ->build();

        $result = $this->repository->create($bookmark);

        $this->assertDatabaseHas(Tag::class, [
            'name' => $tag
        ]);

        $this->assertDatabaseHas(Bookmark::class, [
            'id' => $result->id->toInt(),
            'has_custom_title' => false,
            'description_set_by_user' => false
        ]);

        $this->assertDatabaseHas(Taggable::class, [
            'taggable_id' => $result->id->toInt(),
            'taggable_type' => Taggable::BOOKMARK_TYPE,
            'tag_id' => Tag::query()->where('name', $tag)->first()->id,
            'tagged_by_id' => $bookmark->ownerId->toInt()
        ]);
    }

    public function testWillAttachExistingTagsToNewBookmark(): void
    {
        $bookmark = BookmarkBuilder::fromModel($model = BookmarkFactory::new()->make())
            ->site(SiteBuilder::fromModel(SiteFactory::new()->create())->build())
            ->bookmarkedById($model['user_id'])
            ->tags([$tag = TagFactory::new()->make()->name, $this->faker->word])
            ->build();

        $tagModel = Tag::query()->create(['name' => $tag]);

        $result = $this->repository->create($bookmark);

        $this->assertDatabaseHas(Taggable::class, [
            'taggable_id' => $result->id->toInt(),
            'tag_id' => $tagModel->id
        ]);
    }
}
