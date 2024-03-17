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
            ['max_collaborators_limit' => 1001],
            ["{$key}.max_collaborators_limit" => "The {$key}.max_collaborators_limit must not be greater than 1000."]
        );

        $json(
            ['accept_invite_constraints' => 'foo'],
            ["{$key}.accept_invite_constraints" => 'The settings.accept_invite_constraints must be an array.']
        );

        $json(
            ['accept_invite_constraints' => ['InviterMustHaveRequiredPermission', 'InviterMustHaveRequiredPermission']],
            ["{$key}.accept_invite_constraints" => 'The settings.accept_invite_constraints field has a duplicate value.']
        );

        $json(
            ['notifications.enabled' => 'foo'],
            ["{$key}.notifications.enabled" => "The {$key}.notifications.enabled field must be true or false."]
        );

        $json(
            ['notifications.new_collaborator.enabled' => 'foo'],
            ["{$key}.notifications.new_collaborator.enabled" => "The {$key}.notifications.new_collaborator.enabled field must be true or false."]
        );

        $json(
            ['notifications.new_collaborator.mode' => 'foo'],
            ["{$key}.notifications.new_collaborator.mode" => "The selected {$key}.notifications.new_collaborator.mode is invalid."]
        );

        $json(
            ['notifications.folder_updated.enabled' => null],
            ["{$key}.notifications.folder_updated.enabled" => "The {$key}.notifications.folder_updated.enabled field must be true or false."]
        );

        $json(
            ['notifications.new_bookmarks.enabled' => 'foo'],
            ["{$key}.notifications.new_bookmarks.enabled" => "The {$key}.notifications.new_bookmarks.enabled field must be true or false."]
        );

        $json(
            ['notifications.bookmarks_removed.enabled' => 'foo'],
            ["{$key}.notifications.bookmarks_removed.enabled" => "The {$key}.notifications.bookmarks_removed.enabled field must be true or false."]
        );

        $json(
            ['notifications.collaborator_exit.enabled' => 'foo'],
            ["{$key}.notifications.collaborator_exit.enabled" => "The {$key}.notifications.collaborator_exit.enabled field must be true or false."]
        );

        $json(
            ['notifications.collaborator_exit.mode' => 'foo'],
            ["{$key}.notifications.collaborator_exit.mode" => "The selected {$key}.notifications.collaborator_exit.mode is invalid."]
        );
    }
}
