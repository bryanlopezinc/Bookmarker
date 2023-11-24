<?php

namespace Tests\Unit;

use App\ValueObjects\FolderSettings;
use App\Enums\FolderSettingKey as Key;
use App\Exceptions\InvalidFolderSettingException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FolderSettingsTest extends TestCase
{
    public function testWillThrowExceptionWhenSettingsIsInvalid(): void
    {
        $this->assertFalse($this->isValid(['foo' => 'baz']));
    }

    public function test_attributes_must_have_valid_types(): void
    {
        $this->assertFalse($this->isValid([Key::ENABLE_NOTIFICATIONS => 'baz']));
        $this->assertFalse($this->isValid([Key::NEW_COLLABORATOR_NOTIFICATION => 'baz']));
        $this->assertFalse($this->isValid([Key::ONLY_COLLABORATOR_INVITED_BY_USER_NOTIFICATION => 'baz']));
        $this->assertFalse($this->isValid([Key::NOTIFy_ON_UPDATE => 'baz']));
        $this->assertFalse($this->isValid([Key::NOTIFY_ON_NEW_BOOKMARK => 'baz']));
        $this->assertFalse($this->isValid([Key::NOTIFY_ON_BOOKMARK_DELETED => 'baz']));
        $this->assertFalse($this->isValid([Key::NEW_COLLABORATOR_NOTIFICATION => 'baz']));
        $this->assertFalse($this->isValid([Key::NOTIFY_ON_COLLABORATOR_EXIT => 'baz']));
        $this->assertFalse($this->isValid([Key::NOTIFY_ON_COLLABORATOR_EXIT_ONLY_WHEN_HAS_WRITE_PERMISSION => 'baz']));
    }

    #[Test]
    public function whenHasUnknownSettingKey(): void
    {
        $this->assertFalse($this->isValid(['foo' => 'baz']));
    }

    public function test_settings_can_be_empty(): void
    {
        $this->expectNotToPerformAssertions();

        new FolderSettings([]);
    }

    private function isValid(array $data, int $errorCode = 1777): bool
    {
        try {
            new FolderSettings($data);
            return true;
        } catch (InvalidFolderSettingException $e) {
            $this->assertEquals($errorCode, $e->getCode());
            return false;
        }
    }
}
