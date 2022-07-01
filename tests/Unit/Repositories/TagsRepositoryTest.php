<?php

namespace Tests\Unit\Repositories;

use App\Collections\TagsCollection;
use App\Models\Bookmark;
use App\Models\Taggable;
use App\Models\Tag;
use App\PaginationData;
use App\Repositories\TagsRepository;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Database\Factories\BookmarkFactory;
use Database\Factories\TagFactory;
use Tests\TestCase;

class TagsRepositoryTest extends TestCase
{
    public function testWillReturnOnlyTagsCreatedByUser(): void
    {
        $repository = new TagsRepository;

        /** @var Bookmark */
        $bookmark = BookmarkFactory::new()->create();

        $user1Tag = 'like' . rand(0, 1000);
        $user2Tag = 'like' . rand(0, 1000);
        $user3Tag = 'like' . rand(0, 1000);

        //Bookmarks by different users with similar tags
        $repository->attach(TagsCollection::make([$user1Tag]), $bookmark);
        $repository->attach(TagsCollection::make([$user2Tag]), BookmarkFactory::new()->create());
        $repository->attach(TagsCollection::make([$user3Tag]), BookmarkFactory::new()->create());

        $result = $repository->search('like', new UserID($bookmark->user_id), 20);

        $this->assertCount(1, $result);
        $this->assertEquals($user1Tag, $result->toStringCollection()->sole());
    }

    public function testGetUserTagsMethodWillReturnOnlyTagsCreatedByUser(): void
    {
        $repository = new TagsRepository;

        /** @var Bookmark */
        $bookmark = BookmarkFactory::new()->create();

        $user1Tag = 'like' . rand(0, 1000);
        $user2Tag = 'like' . rand(0, 1000);
        $user3Tag = 'like' . rand(0, 1000);

        //Bookmarks by different users with similar tags
        $repository->attach(TagsCollection::make([$user1Tag]), $bookmark);
        $repository->attach(TagsCollection::make([$user2Tag]), BookmarkFactory::new()->create());
        $repository->attach(TagsCollection::make([$user3Tag]), BookmarkFactory::new()->create());

        /** @var array<\App\ValueObjects\Tag> */
        $result = $repository->getUsertags(new UserID($bookmark->user_id), new PaginationData)->items();

        $this->assertCount(1, $result);
        $this->assertEquals($user1Tag, $result[0]->value);
    }

    public function testWillDetachBookmarkTags(): void
    {
        $repository =  new TagsRepository;

        /** @var Bookmark */
        $model = BookmarkFactory::new()->create();

        $tags = TagFactory::new()->count(5)->create();

        $repository->attach(TagsCollection::make($tags), $model);

        $repository->detach(TagsCollection::make($tags), new ResourceID($model->id));

        $tags->each(function (Tag $tag) use ($model) {
            $this->assertDatabaseMissing(Taggable::class, [
                'taggable_id' => $model->id,
                'tag_id' => $tag->id,
                'tagged_by_id' => $model->user_id,
                'taggable_type' => Taggable::BOOKMARK_TYPE
            ]);
        });
    }

    public function testWillNotDetachBookmarkTags(): void
    {
        $repository =  new TagsRepository;

        /** @var Bookmark */
        $model = BookmarkFactory::new()->create();

        $tags = TagFactory::new()->count(5)->create();

        $repository->attach(TagsCollection::make($tags), $model);

        $repository->detach(
            TagsCollection::make(TagFactory::new()->count(5)->create()),
            new ResourceID($model->id)
        );

        $tags->each(function (Tag $tag) use ($model) {
            $this->assertDatabaseHas(Taggable::class, [
                'taggable_id' => $model->id,
                'tag_id' => $tag->id,
                'tagged_by_id' => $model->user_id,
                'taggable_type' => Taggable::BOOKMARK_TYPE
            ]);
        });
    }

    public function testWillNotDuplicateTags(): void
    {
        $tag  = 'like' . rand(0, 1000);

        $repository = new TagsRepository;

        $repository->attach(TagsCollection::make([$tag]), BookmarkFactory::new()->create());
        $repository->attach(TagsCollection::make([$tag]), BookmarkFactory::new()->create());
        $repository->attach(TagsCollection::make([$tag]), BookmarkFactory::new()->create());
        $repository->attach(TagsCollection::make([$tag]), BookmarkFactory::new()->create());

        $this->assertEquals(1, Tag::whereIn('name', [$tag])->get(['name'])->count());
    }
}
