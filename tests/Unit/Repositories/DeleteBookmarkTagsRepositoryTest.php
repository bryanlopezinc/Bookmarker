<?php

namespace Tests\Unit\Repositories;

use App\Collections\TagsCollection;
use App\Models\Bookmark;
use App\Models\BookmarkTag;
use App\Models\Tag;
use App\Repositories\DeleteBookmarkTagsRepository;
use App\Repositories\TagsRepository;
use App\ValueObjects\ResourceId;
use Database\Factories\BookmarkFactory;
use Database\Factories\TagFactory;
use Tests\TestCase;

class DeleteBookmarkTagsRepositoryTest extends TestCase
{
    private DeleteBookmarkTagsRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(DeleteBookmarkTagsRepository::class);
    }

    public function testWillDeleteBookmarkTags(): void
    {
        /** @var Bookmark */
        $model = BookmarkFactory::new()->create();

        $tags = TagFactory::new()->count(5)->create();

        (new TagsRepository)->attach($model, TagsCollection::createFromStrings($tags->pluck('name')->all()));

        $this->repository->delete(new ResourceId($model->id), TagsCollection::createFromStrings($tags->pluck('name')->all()));

        $tags->each(function (Tag $tag) use ($model) {
            $this->assertDatabaseMissing(BookmarkTag::class, [
                'bookmark_id' => $model->id,
                'tag_id' => $tag->id
            ]);
        });
    }

    public function testWillNotDeleteBookmarkTags(): void
    {
        /** @var Bookmark */
        $model = BookmarkFactory::new()->create();

        $tags = TagFactory::new()->count(5)->create();

        (new TagsRepository)->attach($model, TagsCollection::createFromStrings($tags->pluck('name')->all()));

        $this->repository->delete(
            new ResourceId($model->id),
            TagsCollection::createFromStrings(TagFactory::new()->count(5)->create()->pluck('name')->all())
        );

        $tags->each(function (Tag $tag) use ($model) {
            $this->assertDatabaseHas(BookmarkTag::class, [
                'bookmark_id' => $model->id,
                'tag_id' => $tag->id
            ]);
        });
    }
}
