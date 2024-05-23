<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\Concerns;

trait TestsFolderSettings
{
    public static function invalidSettingsData(): array
    {
        return [
            [['foo' => 'bar'], ['The foo value is invalid.']],
            [[], ['The settings field must have a value.']],
            [['baz'], ['The 0 value is invalid.']],
            [['version' => '1.0.0'], ['The version value is invalid.']],
            [['activities.enabled' => 'foo'], ['The activities.enabled field must be true or false.']],
            [['activities.visibility' => 'foo'], ['The selected activities.visibility is invalid.']],
            [['max_collaborators_limit' => '1001'], ['The max_collaborators_limit must not be greater than 1000.']],
            [['max_bookmarks_limit' => '201'], ['The max_bookmarks_limit must not be greater than 200.']],
            [['accept_invite_constraints' => 'foo'], ['The accept_invite_constraints must be an array.']],
            [['accept_invite_constraints' => ['InviterMustHaveRequiredPermission', 'InviterMustHaveRequiredPermission']], ['The accept_invite_constraints field has a duplicate value.']],
            [['notifications.enabled' => 'foo'], ['The notifications.enabled field must be true or false.']],
            [['notifications.new_collaborator.enabled' => 'foo'], ['The notifications.new_collaborator.enabled field must be true or false.']],
            [['notifications.new_collaborator.mode' => 'foo'], ['The selected notifications.new_collaborator.mode is invalid.']],
            [['notifications.folder_updated.enabled' => 'foo'], ['The notifications.folder_updated.enabled field must be true or false.']],
            [['notifications.new_bookmarks.enabled' => 'foo'], ['The notifications.new_bookmarks.enabled field must be true or false.']],
            [['notifications.bookmarks_removed.enabled' => 'foo'], ['The notifications.bookmarks_removed.enabled field must be true or false.']],
            [['notifications.collaborator_exit.enabled' => 'foo'], ['The notifications.collaborator_exit.enabled field must be true or false.']],
            [['notifications.collaborator_exit.mode' => 'foo'], ['The selected notifications.collaborator_exit.mode is invalid.']],
        ];
    }
}
