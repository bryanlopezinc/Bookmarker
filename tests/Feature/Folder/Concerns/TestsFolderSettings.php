<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\Concerns;

use Illuminate\Testing\TestResponse;

use Closure;
use Illuminate\Support\Arr;

trait TestsFolderSettings
{
    protected function assertWillReturnUnprocessableWhenFolderSettingsIsInValid(array $parameters, Closure $response): void
    {
        $key = 'settings';

        $json = function (array $query, array $errors = []) use ($parameters, $response, $key): TestResponse {
            $parameters[$key] = Arr::undot($query);

            $parameters = array_merge($parameters, $query);

            return $response($parameters)->assertUnprocessable()->assertJsonValidationErrors($errors);
        };

        $json(['foo' => 'bar'], [$key => "The given setting foo is invalid."]);
        $json([], [$key => "The {$key} field must have a value."]);
        $json(['baz'], [$key => "The given setting 0 is invalid."]);
        $json(['version' => '1.0.0'], [$key => "The given setting version is invalid."]);

        $json(
            ['maxCollaboratorsLimit' => 1001],
            ["{$key}.maxCollaboratorsLimit" => "The {$key}.maxCollaboratorsLimit must not be greater than 1000."]
        );

        $json(
            ['acceptInviteConstraints' => 'foo'],
            ["{$key}.acceptInviteConstraints" => 'The settings.acceptInviteConstraints must be an array.']
        );

        $json(
            ['acceptInviteConstraints' => ['InviterMustHaveRequiredPermission', 'InviterMustHaveRequiredPermission']],
            ["{$key}.acceptInviteConstraints" => 'The settings.acceptInviteConstraints field has a duplicate value.']
        );

        $json(
            ['notifications.enabled' => 'foo'],
            ["{$key}.notifications.enabled" => "The {$key}.notifications.enabled field must be true or false."]
        );

        $json(
            ['notifications.newCollaborator.enabled' => 'foo'],
            ["{$key}.notifications.newCollaborator.enabled" => "The {$key}.notifications.newCollaborator.enabled field must be true or false."]
        );

        $json(
            ['notifications.newCollaborator.mode' => 'foo'],
            ["{$key}.notifications.newCollaborator.mode" => "The selected {$key}.notifications.newCollaborator.mode is invalid."]
        );

        $json(
            ['notifications.folderUpdated.enabled' => null],
            ["{$key}.notifications.folderUpdated.enabled" => "The {$key}.notifications.folderUpdated.enabled field must be true or false."]
        );

        $json(
            ['notifications.newBookmarks.enabled' => 'foo'],
            ["{$key}.notifications.newBookmarks.enabled" => "The {$key}.notifications.newBookmarks.enabled field must be true or false."]
        );

        $json(
            ['notifications.bookmarksRemoved.enabled' => 'foo'],
            ["{$key}.notifications.bookmarksRemoved.enabled" => "The {$key}.notifications.bookmarksRemoved.enabled field must be true or false."]
        );

        $json(
            ['notifications.collaboratorExit.enabled' => 'foo'],
            ["{$key}.notifications.collaboratorExit.enabled" => "The {$key}.notifications.collaboratorExit.enabled field must be true or false."]
        );

        $json(
            ['notifications.collaboratorExit.mode' => 'foo'],
            ["{$key}.notifications.collaboratorExit.mode" => "The selected {$key}.notifications.collaboratorExit.mode is invalid."]
        );
    }
}
