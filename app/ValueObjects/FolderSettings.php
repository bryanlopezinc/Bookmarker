<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Utils\FolderSettingsValidator;
use Illuminate\Contracts\Support\Arrayable;
use App\Enums\NewCollaboratorNotificationMode;
use App\Enums\CollaboratorExitNotificationMode;
use BackedEnum;
use Error;

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
 */
final class FolderSettings implements Arrayable
{
    private readonly array $settings;
    private readonly array $resolvedSetting;

    public function __construct(array $settings)
    {
        $default = $this->default();

        $this->settings = array_replace(['version' => $default['version']], $settings);

        $this->validate($this->settings);

        $this->resolvedSetting = array_replace_recursive($default, $this->settings);
    }

    public static function make(string|FolderSettings|array $settings = null): self
    {
        if (is_string($settings)) {
            $settings = json_decode($settings, true, flags: JSON_THROW_ON_ERROR);
        }

        if ($settings instanceof FolderSettings) {
            $settings = $settings->toArray();
        }

        if (is_null($settings)) {
            $settings = [];
        }

        return new self($settings);
    }

    public static function default(): array
    {
        return [
            'version' => '1.0.0',
            'maxCollaboratorsLimit' => -1,
            'notifications' => [
                'enabled' => true,
                'newCollaborator' => [
                    'enabled' => true,
                    'mode'    => '*'
                ],
                'collaboratorExit' => [
                    'enabled' => true,
                    'mode'    => '*'
                ],
                'folderUpdated'    => ['enabled' => true],
                'newBookmarks'     => ['enabled' => true],
                'bookmarksRemoved' => ['enabled' => true],
            ]
        ];
    }

    private function resolve(string $classProperty): bool|BackedEnum|int
    {
        $notifications = $this->resolvedSetting['notifications'];

        return match ($classProperty) {
            'maxCollaboratorsLimit'                 => $this->resolvedSetting['maxCollaboratorsLimit'],
            'notificationsAreEnabled'                => $notifications['enabled'],
            'newCollaboratorNotificationIsEnabled'   => $notifications['newCollaborator']['enabled'],
            'newCollaboratorNotificationMode'        => NewCollaboratorNotificationMode::from($notifications['newCollaborator']['mode']),
            'folderUpdatedNotificationIsEnabled'     => $notifications['folderUpdated']['enabled'],
            'newBookmarksNotificationIsEnabled'      => $notifications['newBookmarks']['enabled'],
            'bookmarksRemovedNotificationIsEnabled'  => $notifications['bookmarksRemoved']['enabled'],
            'collaboratorExitNotificationIsEnabled'  => $notifications['collaboratorExit']['enabled'],
            'collaboratorExitNotificationMode'       => CollaboratorExitNotificationMode::from($notifications['collaboratorExit']['mode']),
            'notificationsAreDisabled'               => !$this->resolve('notificationsAreEnabled'),
            'newCollaboratorNotificationIsDisabled'  => !$this->resolve('newCollaboratorNotificationIsEnabled'),
            'folderUpdatedNotificationIsDisabled'    => !$this->resolve('folderUpdatedNotificationIsEnabled'),
            'newBookmarksNotificationIsDisabled'     => !$this->resolve('newBookmarksNotificationIsEnabled'),
            'bookmarksRemovedNotificationIsDisabled' => !$this->resolve('bookmarksRemovedNotificationIsEnabled'),
            'collaboratorExitNotificationIsDisabled' => !$this->resolve('collaboratorExitNotificationIsEnabled'),
            default => throw new Error(sprintf("Call to undefined property %s::$%s", __CLASS__, $classProperty)),
        };
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->resolve($name);
    }

    /**
     * @param string $name
     * @param mixed $value
     *
     * @return void
     */
    public function __set($name, $value)
    {
        throw new Error(sprintf('Cannot modify or set property %s::$%s', __CLASS__, $name));
    }

    private function validate(array $settings): void
    {
        if (empty($settings)) {
            return;
        }

        $validator = new FolderSettingsValidator();

        $validator->validate($settings);
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
