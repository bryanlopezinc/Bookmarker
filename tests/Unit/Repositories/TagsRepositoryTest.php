<?php

namespace Tests\Unit\Repositories;

use App\Collections\TagsCollection;
use App\Models\Bookmark;
use App\Models\BookmarkTag;
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
        $repository->attach(TagsCollection::createFromStrings([$user1Tag]), $bookmark);
        $repository->attach(TagsCollection::createFromStrings([$user2Tag]), BookmarkFactory::new()->create());
        $repository->attach(TagsCollection::createFromStrings([$user3Tag]), BookmarkFactory::new()->create());

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
        $repository->attach(TagsCollection::createFromStrings([$user1Tag]), $bookmark);
        $repository->attach(TagsCollection::createFromStrings([$user2Tag]), BookmarkFactory::new()->create());
        $repository->attach(TagsCollection::createFromStrings([$user3Tag]), BookmarkFactory::new()->create());

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

        $repository->attach(TagsCollection::createFromStrings($tags->pluck('name')->all()), $model);

        $repository->detach(TagsCollection::createFromStrings($tags->pluck('name')->all()), new ResourceID($model->id));

        $tags->each(function (Tag $tag) use ($model) {
            $this->assertDatabaseMissing(BookmarkTag::class, [
                'bookmark_id' => $model->id,
                'tag_id' => $tag->id
            ]);
        });
    }

    public function testWillNotDetachBookmarkTags(): void
    {
        $repository =  new TagsRepository;

        /** @var Bookmark */
        $model = BookmarkFactory::new()->create();

        $tags = TagFactory::new()->count(5)->create();

        $repository->attach(TagsCollection::createFromStrings($tags->pluck('name')->all()), $model);

        $repository->detach(
            TagsCollection::createFromStrings(TagFactory::new()->count(5)->create()->pluck('name')->all()),
            new ResourceID($model->id)
        );

        $tags->each(function (Tag $tag) use ($model) {
            $this->assertDatabaseHas(BookmarkTag::class, [
                'bookmark_id' => $model->id,
                'tag_id' => $tag->id
            ]);
        });
    }

    public function testWillNotDuplicateTags(): void
    {
        $tag  = 'like' . rand(0, 1000);

        $repository = new TagsRepository;

        $repository->attach(TagsCollection::createFromStrings([$tag]), BookmarkFactory::new()->create());
        $repository->attach(TagsCollection::createFromStrings([$tag]), BookmarkFactory::new()->create());
        $repository->attach(TagsCollection::createFromStrings([$tag]), BookmarkFactory::new()->create());
        $repository->attach(TagsCollection::createFromStrings([$tag]), BookmarkFactory::new()->create());

        $this->assertEquals(1, Tag::whereIn('name', [$tag])->get(['name'])->count());
    }
}
