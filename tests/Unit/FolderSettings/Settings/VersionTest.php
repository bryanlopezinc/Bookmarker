<?php

declare(strict_types=1);

namespace Tests\Unit\FolderSettings;

use PHPUnit\Framework\Attributes\Test;

class VersionTest extends TestCase
{
    #[Test]
    public function version(): void
    {
        $settings = $this->make();
        $this->assertEquals('1.0.0', $settings->version()->value());

        $this->assertFalse($this->isValid(['version' => 1], 'The version must be a string.'));
        $this->assertFalse($this->isValid(['version' => '1'], 'The selected version is invalid.'));
        $this->assertFalse($this->isValid(['version' => null], 'The selected version is invalid.'));
    }
}
