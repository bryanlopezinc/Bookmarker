<?php

namespace Tests\Unit\ValueObjects;

use App\ValueObjects\FolderSettings;
use App\Enums\FolderSettingKey as Key;
use App\Exceptions\InvalidFolderSettingException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FolderSettingsTest extends TestCase
{
    public function testWillThrowExceptionWhenKeyIsInvalid(): void
    {
        $this->assertFalse($this->isValid(['foo' => 'baz'], 1778));
    }

    public function testWillThrowExceptionWhenValuesAreInvalid(): void
    {
        $this->assertFalse($this->isValid([Key::ENABLE_NOTIFICATIONS => '1']));
        $this->assertFalse($this->isValid([Key::NEW_COLLABORATOR_NOTIFICATION => '0']));
        $this->assertFalse($this->isValid([Key::ONLY_COLLABORATOR_INVITED_BY_USER_NOTIFICATION => 'true']));
        $this->assertFalse($this->isValid([Key::NOTIFy_ON_UPDATE => 'false']));
        $this->assertFalse($this->isValid([Key::NOTIFY_ON_NEW_BOOKMARK => 0]));
        $this->assertFalse($this->isValid([Key::NOTIFY_ON_BOOKMARK_DELETED => 1]));
        $this->assertFalse($this->isValid([Key::NEW_COLLABORATOR_NOTIFICATION => 'baz']));
        $this->assertFalse($this->isValid([Key::NOTIFY_ON_COLLABORATOR_EXIT => 'baz']));
        $this->assertFalse($this->isValid([Key::NOTIFY_ON_COLLABORATOR_EXIT_ONLY_WHEN_HAS_WRITE_PERMISSION => 'baz']));
    }

    #[Test]
    public function valid(): void
    {
        $this->assertTrue($this->isValid([Key::ENABLE_NOTIFICATIONS => true]));
        $this->assertTrue($this->isValid([Key::NEW_COLLABORATOR_NOTIFICATION => false]));
        $this->assertTrue($this->isValid([Key::ONLY_COLLABORATOR_INVITED_BY_USER_NOTIFICATION => true]));
        $this->assertTrue($this->isValid([Key::NOTIFy_ON_UPDATE => false]));
        $this->assertTrue($this->isValid([Key::NOTIFY_ON_NEW_BOOKMARK => true]));
        $this->assertTrue($this->isValid([Key::NOTIFY_ON_BOOKMARK_DELETED => false]));
        $this->assertTrue($this->isValid([Key::NEW_COLLABORATOR_NOTIFICATION => true]));
        $this->assertTrue($this->isValid([Key::NOTIFY_ON_COLLABORATOR_EXIT => false]));
        $this->assertTrue($this->isValid([Key::NOTIFY_ON_COLLABORATOR_EXIT_ONLY_WHEN_HAS_WRITE_PERMISSION => true]));
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
