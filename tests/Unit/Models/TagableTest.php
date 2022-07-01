<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Taggable;
use Database\Factories\TagFactory;
use Tests\TestCase;

class TagableTest extends TestCase
{
    public function testBookmarkCannotHaveDuplicateTags(): void
    {
        $this->expectExceptionCode(23000);

        $tag = TagFactory::new()->create();

        Taggable::query()->create($attributes = [
            'taggable_id' => 5,
            'taggable_type' => Taggable::BOOKMARK_TYPE,
            'tag_id' => $tag->id,
            'tagged_by_id' => 55
        ]);

        Taggable::query()->create($attributes);
    }
}
