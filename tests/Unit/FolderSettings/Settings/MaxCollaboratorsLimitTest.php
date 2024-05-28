<?php

declare(strict_types=1);

namespace Tests\Unit\FolderSettings\Settings;

use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\FolderSettings\TestCase;

class MaxCollaboratorsLimitTest extends TestCase
{
    #[Test]
    public function maxCollaboratorsLimit(): void
    {
        $settings = $this->make();
        $this->assertEquals(-1, $settings->maxCollaboratorsLimit()->value());

        $settings = $this->make(['max_collaborators_limit' => '50']);
        $this->assertEquals(50, $settings->maxCollaboratorsLimit()->value());

        $this->assertFalse($this->isValid(['max_collaborators_limit' => 1001], 'The max_collaborators_limit must not be greater than 1000.'));
        $this->assertFalse($this->isValid(['max_collaborators_limit' => 1.5], 'The max_collaborators_limit must be an integer.'));
        $this->assertFalse($this->isValid(['max_collaborators_limit' => -2], 'The max_collaborators_limit must be at least -1.'));
        $this->assertFalse($this->isValid(['max_collaborators_limit' => null], 'The max_collaborators_limit must be an integer.'));
    }
}
