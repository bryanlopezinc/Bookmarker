<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Tag;
use App\Models\Taggable;
use Database\Factories\TagFactory;
use Database\Factories\UserFactory;
use Tests\TestCase;

class TagTest extends TestCase
{
    public function testCannotHaveDuplicateTags(): void
    {
        $this->expectExceptionCode(23000);

        $user = UserFactory::new()->create();
        $tag = TagFactory::new()->create(['created_by' => $user->id]);

        Tag::query()->create([
            'created_by' => $user->id,
            'name' => $tag->name,
        ]);
    }
}
