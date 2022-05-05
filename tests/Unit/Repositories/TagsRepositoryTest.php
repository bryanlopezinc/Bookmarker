<?php

namespace Tests\Unit\Repositories;

use App\Collections\TagsCollection;
use App\Models\Bookmark;
use App\Repositories\TagsRepository;
use App\ValueObjects\UserId;
use Database\Factories\BookmarkFactory;
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
        $repository->attach($bookmark, TagsCollection::createFromStrings([$user1Tag]));
        $repository->attach(BookmarkFactory::new()->create(), TagsCollection::createFromStrings([$user2Tag]));
        $repository->attach(BookmarkFactory::new()->create(), TagsCollection::createFromStrings([$user3Tag]));

        $result = $repository->search('like', new UserId($bookmark->user_id), 20);

        $this->assertCount(1, $result);
        $this->assertEquals($user1Tag, $result->toStringCollection()->sole());
    }
}
