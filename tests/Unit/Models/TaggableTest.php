<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Taggable;
use Database\Factories\TagFactory;
use Tests\TestCase;

class TaggableTest extends TestCase
{
    public function testWillThrowExceptionWhenBookmarkHasDuplicateTags(): void
    {
       $this->expectExceptionCode(23000);

        Taggable::query()->create($attributes = [
            'taggable_id' => 5,
            'tag_id'      => TagFactory::new()->create()->id,
        ]);

        Taggable::query()->create($attributes);
    }
}
