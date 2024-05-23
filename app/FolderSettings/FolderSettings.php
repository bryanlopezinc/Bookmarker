<?php

declare(strict_types=1);

namespace App\FolderSettings;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\FolderSettings\Settings\Version;
use App\Exceptions\InvalidFolderSettingException;
use Error;

/**
 * @method Settings\Activities\LogActivities                       logActivities()
 * @method Settings\Activities\ActivitiesVisibility                activitiesVisibility()
 * @method Settings\Version                                        version()
 * @method Settings\Notifications\Notifications                    notifications()
 * @method Settings\AcceptInviteConstraints                        acceptInviteConstraints()
 * @method Settings\MaxBookmarksLimit                              maxBookmarksLimit()
 * @method Settings\MaxCollaboratorsLimit                          maxCollaboratorsLimit()
 * @method Settings\Notifications\BookmarksRemovedNotification     bookmarksRemovedNotification()
 * @method Settings\Notifications\CollaboratorExitNotification     collaboratorExitNotification()
 * @method Settings\Notifications\CollaboratorExitNotificationMode collaboratorExitNotificationMode()
 * @method Settings\Notifications\FolderUpdatedNotification        folderUpdatedNotification()
 * @method Settings\Notifications\NewBookmarksNotification         newBookmarksNotification()
 * @method Settings\Notifications\NewCollaboratorNotification      newCollaboratorNotification()
 * @method Settings\Notifications\NewCollaboratorNotificationMode  newCollaboratorNotificationMode()
 */
final class FolderSettings
{
    /**
     * @var array<string,class-string<SettingInterface>>
     */
    private const KEYS = [
        'version'                                => Settings\Version::class,
        'activities.enabled'                     => Settings\Activities\LogActivities::class,
        'activities.visibility'                  => Settings\Activities\ActivitiesVisibility::class,
        'notifications.enabled'                   => Settings\Notifications\Notifications::class,
        'accept_invite_constraints'              => Settings\AcceptInviteConstraints::class,
        'max_bookmarks_limit'                    => Settings\MaxBookmarksLimit::class,
        'max_collaborators_limit'                => Settings\MaxCollaboratorsLimit::class,
        'notifications.bookmarks_removed.enabled' => Settings\Notifications\BookmarksRemovedNotification::class,
        'notifications.collaborator_exit.enabled' => Settings\Notifications\CollaboratorExitNotification::class,
        'notifications.collaborator_exit.mode'    => Settings\Notifications\CollaboratorExitNotificationMode::class,
        'notifications.folder_updated.enabled'    => Settings\Notifications\FolderUpdatedNotification::class,
        'notifications.new_bookmarks.enabled'     => Settings\Notifications\NewBookmarksNotification::class,
        'notifications.new_collaborator.enabled'  => Settings\Notifications\NewCollaboratorNotification::class,
        'notifications.new_collaborator.mode'     => Settings\Notifications\NewCollaboratorNotificationMode::class,
    ];

    /**
     * @var array<string,class-string<SettingInterface>>
     */
    private static array $methodClassMap;

    /**
     * @var array<string,SettingInterface>
     */
    private readonly array $settings;

    public function __construct(array $settings = [])
    {
        if ( ! empty($settings) && ! array_key_exists('version', $settings)) {
            $settings['version'] = (new Version())->value();
        }

        $this->settings = $this->map($settings);

        $this->buildMethodClassMap();
    }

    /**
     * @param array<SettingInterface> $keys
     */
    public static function fromKeys(array $keys): self
    {
        $instance = new self();

        return new self($instance->getArrayableValues($keys));
    }

    private function getArrayableValues(array $settings): array
    {
        return collect($settings)
            ->flatMap(fn (SettingInterface $setting) => $setting->toArray())
            ->all();
    }

    private function map(array $settings): array
    {
        $result = [];

        foreach (array_keys($this->dot($settings)) as $key) {
            if ( ! array_key_exists($key, self::KEYS)) {
                throw new InvalidFolderSettingException(["The {$key} value is invalid."]);
            }

            $settingClass = self::KEYS[$key];

            $method = $this->getDynamicMethodName($settingClass);

            $result[$method] = $settingClass::fromArray($settings);
        }

        return $result;
    }

    private function getDynamicMethodName(string $class): string
    {
        return Str::camel(class_basename($class));
    }

    private function buildMethodClassMap(): void
    {
        if (isset(self::$methodClassMap)) {
            return;
        }

        foreach (self::KEYS as $class) {
            $method = $this->getDynamicMethodName($class);

            self::$methodClassMap[$method] = $class;
        }
    }

    /**
     * Arr::dot() with a little tweaking to avoid dot numeric arrays
     *
     * @see \Illuminate\Support\Arr::dot()
     */
    private function dot(array $array, string $prepend = ''): array
    {
        $results = [];

        foreach ($array as $key => $value) {
            if (is_array($value) && ! empty($value) && Arr::isAssoc($value)) {
                $results = array_merge($results, $this->dot($value, $prepend . $key . '.'));
            } else {
                $results[$prepend . $key] = $value;
            }
        }

        return $results;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    public function toArray(): array
    {
        return $this->getArrayableValues($this->settings);
    }

    /**
     * Handle dynamic method calls into the folder settings.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return SettingInterface
     */
    public function __call($method, $parameters)
    {
        if (array_key_exists($method, $this->settings)) {
            return $this->settings[$method];
        }

        if (array_key_exists($method, self::$methodClassMap)) {
            return new self::$methodClassMap[$method]();
        }

        throw new Error(sprintf(
            'Call to undefined method %s::%s()',
            self::class,
            $method
        ));
    }
}
