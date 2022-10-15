<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Taggable;
use Database\Factories\TagFactory;
use Database\Factories\UserFactory;
use Tests\TestCase;

class TaggableTest extends TestCase
{
    public function testBookmarkCannotHaveDuplicateTags(): void
    {
       $this->expectExceptionCode(23000);

        $tag = TagFactory::new()->create(['created_by' => UserFactory::new()->create()->id]);

        Taggable::query()->create($attributes = [
            'taggable_id' => 5,
            'taggable_type' => Taggable::BOOKMARK_TYPE,
            'tag_id' => $tag->id,
        ]);

        Taggable::query()->create($attributes);
    }
}
