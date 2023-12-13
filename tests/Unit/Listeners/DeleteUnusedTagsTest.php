<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\TagsDetachedEvent;
use App\Listeners\DeleteUnusedTags;
use App\Repositories\TagRepository;
use Database\Factories\BookmarkFactory;
use Database\Factories\TagFactory;
use Tests\TestCase;

class DeleteUnusedTagsTest extends TestCase
{
    public function testWillNotDeleteTagsThatAreAttachedToAResource(): void
    {
        $listener = new DeleteUnusedTags();
        $tag = TagFactory::new()->create();

        (new TagRepository())->attach($tag, BookmarkFactory::new()->create());

        $listener->handle(
            new TagsDetachedEvent([$tag->name])
        );

        $this->assertDatabaseHas($tag::class, ['id' => $tag->id]);
    }

    public function testWillDeleteTagsThatAreNotAttachedToAnyResource(): void
    {
        $listener = new DeleteUnusedTags();
        $tag = TagFactory::new()->create();

        $listener->handle(
            new TagsDetachedEvent([$tag->name])
        );

        $this->assertDatabaseMissing($tag::class, ['id' => $tag->id]);
    }
}
