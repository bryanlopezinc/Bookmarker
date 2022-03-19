<?php

declare(strict_types=1);

namespace Tests\Unit\Collections;

use App\Collections\TagsCollection;
use Tests\TestCase;

class TagsCollectionTest extends TestCase
{
    public function testExcept(): void
    {
        $tags = TagsCollection::createFromStrings(['monday', 'tuesday', 'wednesday', 'thursday', 'friday']);

        $tags = $tags->except(TagsCollection::createFromStrings(['monday', 'tuesday']));

        $this->assertCount(3, $tags);
        $this->assertEquals($tags->toStringCollection()->all(), ['wednesday', 'thursday', 'friday']);
    }
}
