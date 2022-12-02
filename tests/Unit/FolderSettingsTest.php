<?php

namespace Tests\Unit;

use App\DataTransferObjects\Builders\FolderSettingsBuilder;
use App\DataTransferObjects\FolderSettings;
use Database\Factories\FolderFactory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

/**
 * Assert FolderSettings Object cannot accept invalid settings and invalid settings cannot be save to database.
 */
class FolderSettingsTest extends TestCase
{
    //migrate the latest jsonSchema
    use LazilyRefreshDatabase;

    private static array $default = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (empty(static::$default)) {
            static::$default = FolderSettings::default()->toArray();
        }
    }

    public function testValid(): void
    {
        $settings = FolderSettings::default();
        $this->assertTrue($this->canBeSavedToDB($settings->toArray()));
    }

    public function test_settings_must_be_valid(): void
    {
        $this->assertFalse($this->isValid($settings = ['foo' => 'baz']));
        $this->assertFalse($this->canBeSavedToDB($settings));
    }

    public function test_cannot_have_additional_properties(): void
    {
        foreach ([
            'notifications',
            'notifications.newCollaborator',
            'notifications.collaboratorExit'
        ] as $setting) {
            $settings = FolderSettings::default()->toArray();
            $message = "Failed asserting that exception was thrown for setting [$setting]";

            $this->assertKeyIsDefinedInSettings($setting);
            Arr::set($settings, "$setting.foo", true);

            $this->assertFalse($this->isValid($settings), $message);
            $this->assertFalse($this->canBeSavedToDB($settings), $message);
        }
    }

    private function assertKeyIsDefinedInSettings(string $key): void
    {
        if (!Arr::has(static::$default, $key)) {
            throw new \ErrorException("Undefined array key $key");
        }
    }

    public function testEachSettingKeyMustBePresent(): void
    {
        foreach ($interacted = [
            "version",
            "notifications",
            "notifications.enabled",
            "notifications.newCollaborator",
            "notifications.newCollaborator.notify",
            "notifications.newCollaborator.onlyCollaboratorsInvitedByMe",
            "notifications.updated",
            "notifications.bookmarksAdded",
            "notifications.bookmarksRemoved",
            "notifications.collaboratorExit",
            "notifications.collaboratorExit.notify",
            "notifications.collaboratorExit.onlyWhenCollaboratorHasWritePermission"
        ] as $setting) {
            $settings = FolderSettings::default()->toArray();
            $message = "Failed asserting that exception was thrown for setting [$setting]";

            $this->assertKeyIsDefinedInSettings($setting);
            Arr::forget($settings, $setting);

            $this->assertFalse($this->isValid($settings), $message);
            $this->assertFalse($this->canBeSavedToDB($settings), $message);
        }

        //assert covered all keys in settings
        $this->assertEquals(
            count(FolderSettings::default()->toArray(), COUNT_RECURSIVE),
            count($interacted)
        );
    }

    public function test_attributes_must_have_valid_types(): void
    {
        $this->assertPropertyMustBeType('notifications', 'array');
        $this->assertPropertyMustBeType('notifications', 'NonEmptyArray');
        $this->assertPropertyMustBeType('notifications', 'onlyKnowAttributes');

        $this->assertPropertyMustBeType('notifications.enabled', 'boolean');

        $this->assertPropertyMustBeType('notifications.newCollaborator', 'array');
        $this->assertPropertyMustBeType('notifications.newCollaborator', 'NonEmptyArray');
        $this->assertPropertyMustBeType('notifications.newCollaborator', 'onlyKnowAttributes');
        $this->assertPropertyMustBeType('notifications.newCollaborator.notify', 'boolean');
        $this->assertPropertyMustBeType('notifications.newCollaborator.onlyCollaboratorsInvitedByMe', 'boolean');

        $this->assertPropertyMustBeType('notifications.updated', 'boolean');
        $this->assertPropertyMustBeType('notifications.bookmarksAdded', 'boolean');
        $this->assertPropertyMustBeType('notifications.bookmarksRemoved', 'boolean');

        $this->assertPropertyMustBeType('notifications.collaboratorExit', 'array');
        $this->assertPropertyMustBeType('notifications.collaboratorExit', 'NonEmptyArray');
        $this->assertPropertyMustBeType('notifications.collaboratorExit', 'onlyKnowAttributes');
        $this->assertPropertyMustBeType('notifications.collaboratorExit.notify', 'boolean');
        $this->assertPropertyMustBeType('notifications.collaboratorExit.onlyWhenCollaboratorHasWritePermission', 'boolean');
    }

    private function assertPropertyMustBeType(string $property, string $type): void
    {
        $data = [
            'boolean' => [1, [], 'foo', 'true', 'TRUE', '1', 1.90, new \stdClass],
            'array' => [1, 'array', 'true', 'TRUE', '1', 1.90, new \stdClass],
            'NonEmptyArray' => [[]],
            'onlyKnowAttributes' => [['foo' => 'bar']]
        ];

        foreach ($data[$type] as $invalidType) {
            $settings = FolderSettings::default()->toArray();
            $message = "Failed asserting that property [$property] only accepts $type type";

            $this->assertKeyIsDefinedInSettings($property);
            Arr::set($settings, $property, $invalidType);

            $this->assertFalse($this->isValid($settings), $message);
            $this->assertFalse($this->canBeSavedToDB($settings), $message);
        }
    }

    public function test_settings_cannot_be_empty(): void
    {
        $this->assertFalse($this->isValid([]));
        $this->assertFalse($this->canBeSavedToDB([]));
    }

    public function test_new_collaborator_notification_settings_must_be_valid(): void
    {
        $this->expectExceptionCode(1778);

        (new FolderSettingsBuilder())
            ->disableNewCollaboratorNotification()
            ->enableOnlyCollaboratorsInvitedByMeNotification()
            ->build();
    }

    public function test_new_collaborator_exit_notifications_must_be_valid(): void
    {
        $this->expectExceptionCode(1778);

        (new FolderSettingsBuilder())
            ->disableCollaboratorExitNotification()
            ->enableOnlyCollaboratorWithWritePermissionNotification()
            ->build();
    }

    public function test_version_must_be_in_semver_format(): void
    {
        $settings = FolderSettings::default()->toArray();

        $this->assertKeyIsDefinedInSettings('version');
        Arr::set($settings, 'version', 'major.2.beta');

        $this->assertFalse($this->isValid($settings));
        $this->assertFalse($this->canBeSavedToDB($settings));
    }

    private function isValid(array $data, int $errorCode = 1777): bool
    {
        $valid = true;

        try {
            new FolderSettings($data);
        } catch (\Exception $e) {
            $valid = false;
            $this->assertEquals($errorCode, $e->getCode());
        }

        return $valid;
    }

    private function canBeSavedToDB(array $data): bool
    {
        try {
            FolderFactory::new()->create(['settings' => $data]);
            return true;
        } catch (QueryException $e) {
            $this->assertStringContainsString("Check constraint 'validate_folder_setting' is violated", $e->getMessage());
            return false;
        }
    }
}
