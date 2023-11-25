<?php

declare(strict_types=1);

namespace App\Utils;

use App\ValueObjects\FolderSettings;
use App\Enums\FolderSettingKey as Key;
use App\Exceptions\InvalidFolderSettingException;

final class FolderSettingsValidator
{
    private const VALID = [
        Key::ENABLE_NOTIFICATIONS,
        Key::ONLY_COLLABORATOR_INVITED_BY_USER_NOTIFICATION,
        Key::NOTIFy_ON_UPDATE,
        Key::NOTIFY_ON_NEW_BOOKMARK,
        Key::NOTIFY_ON_COLLABORATOR_EXIT,
        Key::NOTIFY_ON_COLLABORATOR_EXIT_ONLY_WHEN_HAS_WRITE_PERMISSION,
        Key::NOTIFY_ON_BOOKMARK_DELETED,
        Key::NEW_COLLABORATOR_NOTIFICATION
    ];

    /**
     * @throws InvalidFolderSettingException
     */
    public function validate(FolderSettings $settings): void
    {
        $invalidKeys = array_diff(array_keys($settings->toArray()), self::VALID);

        if (count($invalidKeys) > 0) {
            throw new InvalidFolderSettingException('Unknown folder settings: ' . implode(',', $invalidKeys), 1778);
        }

        foreach ($settings->toArray() as $key => $value) {
            if (!is_bool($value)) {
                throw new InvalidFolderSettingException("Invalid setting value for setting {$key}", 1777);
            }
        }
    }
}
