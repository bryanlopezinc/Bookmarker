<?php

declare(strict_types=1);

namespace Tests\Unit\FolderSettings;

use App\FolderSettings\FolderSettings;
use App\FolderSettings\Settings\MaxBookmarksLimit;
use App\FolderSettings\Settings\MaxCollaboratorsLimit;
use App\FolderSettings\Settings\Notifications\Notifications;
use PHPUnit\Framework\Attributes\Test;

class FolderSettingsTest extends TestCase
{
    #[Test]
    public function fromKeys(): void
    {
        $settings = $this->make([
            'notifications' => ['enabled' => false],
            'max_collaborators_limit' => 120,
            'max_bookmarks_limit' => 140
        ]);

        $this->assertEquals($settings, FolderSettings::fromKeys([
            new MaxBookmarksLimit(140),
            new Notifications(false),
            new MaxCollaboratorsLimit(120)
        ]));
    }

    #[Test]
    public function empty(): void
    {
        $settings = $this->make([])->toArray();
        
        $this->assertCount(0, $settings);
    }

    #[Test]
    public function toArrayMethod(): void
    {
        $values = $this->all();

        $this->assertEquals($this->make($values)->toArray(), $values);
    }

    #[Test]
    public function toJsonMethod(): void
    {
        $values = $this->all();

        $this->assertEquals($this->make($values)->toJson(), json_encode($values));
    }
}
