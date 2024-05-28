<?php

declare(strict_types=1);

namespace Tests\Unit\FolderSettings;

use App\Enums\FolderActivitiesVisibility;
use App\Exceptions\InvalidFolderSettingException;
use App\FolderSettings\FolderSettings;
use Illuminate\Support\Arr;
use Tests\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    final protected function make(array $settings = []): FolderSettings
    {
        return new FolderSettings($settings);
    }

    final protected function isValid(array $data, string $message = null): bool
    {
        try {
            $this->make($data);
            return true;
        } catch (InvalidFolderSettingException $e) {
            if ($message) {
                $this->assertContains(
                    $message,
                    $e->errorMessages,
                    sprintf('array contains %s', json_encode($e->errorMessages, JSON_PRETTY_PRINT))
                );
            }

            return false;
        }
    }

    final protected function all(): array
    {
        return Arr::undot([
            'version' => '1.0.0',
            'notifications.enabled'    => false,
            'activities.visibility'   => FolderActivitiesVisibility::PRIVATE->value,
            'notifications.enabled'    => false,
            'max_bookmarks_limit'     => 50,
            'max_collaborators_limit' => 30,
            'activities.bookmarks_removed.enabled'   => true,
            'notifications.bookmarks_removed.enabled' => true,
            'notifications.collaborator_exit.enabled' => false,
            'notifications.collaborator_exit.mode'    => 'hasWritePermission',
            'notifications.folder_updated.enabled'    => true,
            'notifications.new_bookmarks.enabled'     => false,
            'notifications.new_collaborator.enabled'  => true,
            'notifications.new_collaborator.mode'     => '*',
            'accept_invite_constraints' => ['InviterMustBeAnActiveCollaborator', 'InviterMustHaveRequiredPermission']
        ]);
    }
}
