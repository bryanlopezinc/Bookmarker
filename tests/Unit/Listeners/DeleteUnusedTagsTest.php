<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Collections\TagsCollection;
use App\Events\TagsDetachedEvent;
use App\Listeners\DeleteUnusedTags;
use App\Models\Tag;
use App\Models\Taggable;
use App\ValueObjects\UserID;
use Database\Factories\TagFactory;
use Database\Factories\UserFactory;
use Tests\TestCase;

class DeleteUnusedTagsTest extends TestCase
{
    public function taggableType()
    {
        $types = [Taggable::BOOKMARK_TYPE, Taggable::FOLDER_TYPE];

        shuffle($types);

        return [
            [$types[0]]
        ];
    }

    /**
     * @dataProvider taggableType
     */
    public function testWillNotDeleteTagsThatAreAttachedToAResource(int $taggableType): void
    {
        $listener = new DeleteUnusedTags;
        $userID = UserFactory::new()->create()->id;
        $tags = TagFactory::new()
            ->count(5)
            ->create(['created_by' => $userID])
            ->each(fn (Tag $tag) => Taggable::create([
                'tag_id' => $tag->id,
                'taggable_id' => rand(1, 10000),
                'taggable_type' => $taggableType
            ]))
            ->pluck('name');

        $listener->handle(
            new TagsDetachedEvent(TagsCollection::make($tags), new UserID($userID))
        );

        $this->assertCount(5, Tag::where('created_by', $userID)->get());
    }

    public function testWillDeleteTagsThatAreNotAttachedToAnyResource(): void
    {
        $listener = new DeleteUnusedTags;
        $userID = UserFactory::new()->create()->id;
        $tags = TagFactory::new()->count(5)->create(['created_by' => $userID])->pluck('name');

        $listener->handle(
            new TagsDetachedEvent(TagsCollection::make($tags), new UserID($userID))
        );

        $this->assertCount(0, Tag::where('created_by', $userID)->get());
    }

    /**
     * @dataProvider taggableType
     */
    public function testWillDeleteOnlyTagsNotAttachedToAnyResource(int $taggableType): void
    {
        $listener = new DeleteUnusedTags;
        $userID = UserFactory::new()->create()->id;
        $unUsedTags = TagFactory::new()->count(3)->create(['created_by' => $userID]);

        $usedTags = TagFactory::new()
            ->count(3)
            ->create(['created_by' => $userID])
            ->each(fn (Tag $tag) => Taggable::query()->create([
                'tag_id' => $tag->id,
                'taggable_id' => rand(1, 10000),
                'taggable_type' => $taggableType
            ]));

        $event = new TagsDetachedEvent(
            TagsCollection::make($usedTags->pluck('name')->merge($unUsedTags->pluck('name'))),
            new UserID($userID)
        );

        $listener->handle($event);

        $result = Tag::query()->whereKey($usedTags->pluck('id'))->get();

        $this->assertCount(3, $result);
        $this->assertEquals($result->pluck('name')->sort(), $usedTags->pluck('name')->sort());
        $this->assertCount(0, Tag::query()->whereKey($unUsedTags->pluck('id'))->get());
    }

    public function testWillDeleteOnlyUserTags(): void
    {
        $listener = new DeleteUnusedTags;
        $userID = UserFactory::new()->create()->id;
        $otherUserID = UserFactory::new()->create()->id;

        $userTags = TagFactory::new()
            ->count(3)
            ->create(['created_by' => $userID])
            ->pluck('name')
            ->each(fn (string $tag) => Tag::create([
                'name' => $tag,
                'created_by' => $otherUserID
            ]));

        $listener->handle(
            new TagsDetachedEvent(TagsCollection::make($userTags), new UserID($userID))
        );

        $this->assertCount(3, Tag::query()->where('created_by', $otherUserID)->get());
    }
}
