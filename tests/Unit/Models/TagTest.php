<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Tag;
use Database\Factories\TagFactory;
use Database\Factories\UserFactory;
use Tests\TestCase;

class TagTest extends TestCase
{
    public function testWillThrowExceptionWhenTagsExists(): void
    {
        $this->expectExceptionCode(23000);

        $user = UserFactory::new()->create();
        $tag = TagFactory::new()->create();

        Tag::query()->create(['name' => $tag->name]);
    }
}
