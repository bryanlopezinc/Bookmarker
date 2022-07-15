<?php

namespace Tests\Unit\DataTransferObjects;

use App\DataTransferObjects\Bookmark;
use App\DataTransferObjects\Builders\FolderBuilder;
use Database\Factories\TagFactory;
use Tests\TestCase;

class FolderTest extends TestCase
{
    public function test_folder_cannot_have_more_than_15_tags(): void
    {
        $this->expectExceptionCode(600);

        (new FolderBuilder())->setTags(TagFactory::new()->count(16)->make()->pluck('name')->all())->build();
    }

    public function test_will_not_check_folder_tags_when_tags_is_not_initialized(): void
    {
        $this->expectNotToPerformAssertions();

        new Bookmark([]);
    }
}
