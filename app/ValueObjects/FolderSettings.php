<?php

declare(strict_types=1);

namespace App\ValueObjects;

use Error;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Contracts\Support\Arrayable;
use App\Contracts\FolderSettingValueInterface;
use App\Enums\NewCollaboratorNotificationMode;
use App\Enums\CollaboratorExitNotificationMode;
use App\Exceptions\InvalidFolderSettingException;
use App\Repositories\Folder\FolderSettingsSchema as Schema;

/**
 * @property-read bool $notificationsAreEnabled
 * @property-read bool $notificationsAreDisabled
 * @property-read bool $newCollaboratorNotificationIsDisabled
 * @property-read bool $newCollaboratorNotificationIsEnabled
 * @property-read NewCollaboratorNotificationMode $newCollaboratorNotificationMode
 * @property-read bool $folderUpdatedNotificationIsEnabled
 * @property-read bool $folderUpdatedNotificationIsDisabled
 * @property-read bool $newBookmarksNotificationIsEnabled
 * @property-read bool $newBookmarksNotificationIsDisabled
 * @property-read bool $bookmarksRemovedNotificationIsEnabled
 * @property-read bool $bookmarksRemovedNotificationIsDisabled
 * @property-read bool $collaboratorExitNotificationIsEnabled
 * @property-read bool $collaboratorExitNotificationIsDisabled
 * @property-read CollaboratorExitNotificationMode $collaboratorExitNotificationMode
 * @property-read int $maxCollaboratorsLimit
 * @property-read int $maxBookmarksLimit
 * @property-read AcceptInviteConstraints $acceptInviteConstraints
 */
final class FolderSettings implements Arrayable
{
    private readonly array $settings;
    private readonly array $resolvedSetting;

    public function __construct(array $settings)
    {
        $default = $this->default();

        if ( ! empty($settings) && ! array_key_exists('version', $settings)) {
            $settings['version'] = $default['version'];
        }

        $this->settings = $settings;

        $this->validate($this->settings);

        $this->resolvedSetting = array_replace_recursive($default, $this->settings);
    }

    private function validate(array $settings): void
    {
        if (empty($settings)) {
            return;
        }

        $repository = new Schema();

        $validator = Validator::make($settings, $repository->rules());

        if ($validator->fails()) {
            throw new InvalidFolderSettingException($validator->errors()->all());
        }
    }

    public static function make(string|FolderSettings|array $settings = null): self
    {
        if (is_string($settings)) {
            $settings = json_decode($settings, true, flags: JSON_THROW_ON_ERROR);
        }

        if ($settings instanceof FolderSettings) {
            $settings = $settings->toArray();
        }

        return new self($settings ?? []);
    }

    public static function default(): array
    {
        $repository = new Schema();

        $default = [];

        foreach ($repository->schema() as $schema) {
            Arr::set($default, $schema->Id, $schema->defaultValue);
        }

        return $default;
    }

    private function resolve(string $classProperty): bool|int|FolderSettingValueInterface
    {
        $notifications = $this->resolvedSetting['notifications'];

        return match ($classProperty) {
            'maxCollaboratorsLimit'                 => $this->resolvedSetting['max_collaborators_limit'],
            'maxBookmarksLimit'                     => $this->resolvedSetting['max_bookmarks_limit'],
            'acceptInviteConstraints'               => new AcceptInviteConstraints($this->resolvedSetting['accept_invite_constraints']),
            'notificationsAreEnabled'                => $notifications['enabled'],
            'newCollaboratorNotificationIsEnabled'   => $notifications['new_collaborator']['enabled'],
            'newCollaboratorNotificationMode'        => NewCollaboratorNotificationMode::from($notifications['new_collaborator']['mode']),
            'folderUpdatedNotificationIsEnabled'     => $notifications['folder_updated']['enabled'],
            'newBookmarksNotificationIsEnabled'      => $notifications['new_bookmarks']['enabled'],
            'bookmarksRemovedNotificationIsEnabled'  => $notifications['bookmarks_removed']['enabled'],
            'collaboratorExitNotificationIsEnabled'  => $notifications['collaborator_exit']['enabled'],
            'collaboratorExitNotificationMode'       => CollaboratorExitNotificationMode::from($notifications['collaborator_exit']['mode']),
            'notificationsAreDisabled'               => ! $this->resolve('notificationsAreEnabled'),
            'newCollaboratorNotificationIsDisabled'  => ! $this->resolve('newCollaboratorNotificationIsEnabled'),
            'folderUpdatedNotificationIsDisabled'    => ! $this->resolve('folderUpdatedNotificationIsEnabled'),
            'newBookmarksNotificationIsDisabled'     => ! $this->resolve('newBookmarksNotificationIsEnabled'),
            'bookmarksRemovedNotificationIsDisabled' => ! $this->resolve('bookmarksRemovedNotificationIsEnabled'),
            'collaboratorExitNotificationIsDisabled' => ! $this->resolve('collaboratorExitNotificationIsEnabled'),
            default => throw new Error(sprintf("Call to undefined property %s::$%s", __CLASS__, $classProperty)),
        };
    }

    /**
     * @param  string $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->resolve($name);
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return void
     */
    public function __set($name, $value)
    {
        throw new Error(sprintf('Cannot modify or set property %s::$%s', __CLASS__, $name));
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    public function toArray(): array
    {
        return $this->settings;
    }
}
