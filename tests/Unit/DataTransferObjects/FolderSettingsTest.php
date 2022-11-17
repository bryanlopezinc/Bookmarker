<?php

namespace Tests\Unit\DataTransferObjects;

use App\DataTransferObjects\Builders\FolderSettingsBuilder;
use App\DataTransferObjects\FolderSettings;
use Exception;
use Illuminate\Support\Arr;
use Tests\TestCase;

class FolderSettingsTest extends TestCase
{
    public function test_settings_must_be_valid(): void
    {
        $this->expectExceptionCode(1777);

        new FolderSettings(['foo' => 'baz']);
    }

    public function test_cannot_have_additional_properties(): void
    {
        foreach ([
            'notifications',
            'notifications.newCollaborator',
            'notifications.collaboratorExit'
        ] as $setting) {
            $passedValidation = true;
            $settings = FolderSettings::default()->toArray();
            Arr::set($settings, "$setting.foo", true);

            try {
                new FolderSettings($settings);
            } catch (Exception $e) {
                $passedValidation = false;
                $this->assertEquals($e->getCode(), 1777);
            }

            if ($passedValidation) {
                throw new Exception("Failed asserting that exception was thrown for setting [$setting]");
            }
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
            $passedValidation = true;

            $this->assertTrue(Arr::has($settings, $setting));
            Arr::forget($settings, $setting);

            try {
                new FolderSettings($settings);
            } catch (Exception $e) {
                $passedValidation = false;
                $this->assertEquals($e->getCode(), 1777);
            }

            if ($passedValidation) {
                throw new Exception("Failed asserting that exception was thrown for setting [$setting]");
            }
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
            $passedValidation = true;

            $this->assertTrue(Arr::has($settings, $property), "Failed asserting that settings has prop $property");
            Arr::set($settings, $property, $invalidType);

            try {
                new FolderSettings($settings);
            } catch (Exception $e) {
                $passedValidation = false;
                $this->assertEquals($e->getCode(), 1777);
            }

            if ($passedValidation) {
                throw new Exception("Failed asserting that property [$property] only accepts $type type");
            }
        }
    }

    public function test_settings_cannot_be_empty(): void
    {
        $this->expectExceptionCode(1777);

        new FolderSettings([]);
    }

    public function test_new_collaborator_notification_settings_must_be_valid(): void
    {
        $this->expectExceptionCode(1778);

        (new FolderSettingsBuilder())
            ->notifyOnNewCollaborator(false)
            ->notifyOnNewCollaboratorOnlyInvitedByMe(true)
            ->build();
    }

    public function test_new_collaborator_exit_notifications_must_be_valid(): void
    {
        $this->expectExceptionCode(1778);

        (new FolderSettingsBuilder())
            ->notifyOnCollaboratorExit(false)
            ->notifyOnCollaboratorExitOnlyWhenHasWritePermission(true)
            ->build();
    }

    public function test_version_must_be_in_semver_format(): void
    {
        $this->expectExceptionCode(1777);

        (new FolderSettingsBuilder())
            ->version('major.2.beta')
            ->build();
    }

    public function test_version_must_be_known(): void
    {
        $this->expectExceptionCode(1779);

        (new FolderSettingsBuilder())
            ->version('1.0.1')
            ->build();
    }
}
