<?php

declare(strict_types=1);

namespace Tests\Unit\FolderSettings\Settings;

use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\FolderSettings\TestCase;

class MaxBookmarksLimitTest extends TestCase
{
    #[Test]
    public function maxCollaboratorsLimit(): void
    {
        $settings = $this->make();
        $this->assertEquals(-1, $settings->maxBookmarksLimit()->value());

        $settings = $this->make(['max_bookmarks_limit' => '50']);
        $this->assertEquals(50, $settings->maxBookmarksLimit()->value());

        $this->assertFalse($this->isValid(['max_bookmarks_limit' => 1001], 'The max_bookmarks_limit must not be greater than 200.'));
        $this->assertFalse($this->isValid(['max_bookmarks_limit' => 1.5], 'The max_bookmarks_limit must be an integer.'));
        $this->assertFalse($this->isValid(['max_bookmarks_limit' => -2], 'The max_bookmarks_limit must be at least -1.'));
        $this->assertFalse($this->isValid(['max_bookmarks_limit' => null], 'The max_bookmarks_limit must be an integer.'));
    }
}
