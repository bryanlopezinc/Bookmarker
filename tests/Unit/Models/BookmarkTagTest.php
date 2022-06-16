<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\BookmarkTag;
use Tests\TestCase;

class BookmarkTagTest extends TestCase
{
    public function testBookmarkCannotHaveDuplicateTags(): void
    {
        $this->expectExceptionCode(23000);

        BookmarkTag::query()->create([
            'bookmark_id' => 5,
            'tag_id' => 3
        ]);

        BookmarkTag::query()->create([
            'bookmark_id' => 5,
            'tag_id' => 3
        ]);
    }
}
