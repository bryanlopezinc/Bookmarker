<?php

declare(strict_types=1);

namespace Tests\Unit\FolderSettings\Settings;

use App\Enums\FolderActivitiesVisibility;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\FolderSettings\TestCase;

class ActivitiesVisibilityTest extends TestCase
{
    #[Test]
    public function activitiesVisibility(): void
    {
        $settings = $this->make();
        $this->assertEquals(FolderActivitiesVisibility::PUBLIC, $settings->activitiesVisibility()->value());

        $settings = $this->make(['activities' => ['visibility' => 'public']]);
        $this->assertEquals(FolderActivitiesVisibility::PUBLIC, $settings->activitiesVisibility()->value());
        $settings = $this->make(['activities' => ['visibility' => FolderActivitiesVisibility::PUBLIC->value]]);
        $this->assertEquals(FolderActivitiesVisibility::PUBLIC, $settings->activitiesVisibility()->value());

        $settings = $this->make(['activities' => ['visibility' => 'private']]);
        $this->assertEquals(FolderActivitiesVisibility::PRIVATE, $settings->activitiesVisibility()->value());
        $settings = $this->make(['activities' => ['visibility' => FolderActivitiesVisibility::PRIVATE->value]]);
        $this->assertEquals(FolderActivitiesVisibility::PRIVATE, $settings->activitiesVisibility()->value());

        $settings = $this->make(['activities' => ['visibility' => 'collaborators']]);
        $this->assertEquals(FolderActivitiesVisibility::COLLABORATORS, $settings->activitiesVisibility()->value());
        $settings = $this->make(['activities' => ['visibility' => FolderActivitiesVisibility::COLLABORATORS->value]]);
        $this->assertEquals(FolderActivitiesVisibility::COLLABORATORS, $settings->activitiesVisibility()->value());

        $this->assertFalse($this->isValid(['activities' => ['visibility' => 99]], 'The selected activities.visibility is invalid.'));
        $this->assertFalse($this->isValid(['activities' => ['visibility' => 'foo']], 'The selected activities.visibility is invalid.'));
        $this->assertFalse($this->isValid(['activities' => ['visibility' => null]], 'The selected activities.visibility is invalid.'));
    }
}
