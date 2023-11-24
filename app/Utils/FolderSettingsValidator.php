<?php

declare(strict_types=1);

namespace App\Utils;

use App\ValueObjects\FolderSettings;
use App\Exceptions\InvalidJsonException;
use App\Enums\FolderSettingKey as Key;
use App\Exceptions\InvalidFolderSettingException;

final class FolderSettingsValidator
{
    private static ?string $jsonSchema = null;

    private JsonValidator $jsonValidator;

    public function __construct(JsonValidator $jsonValidator = null)
    {
        $this->jsonValidator = $jsonValidator ?: new JsonValidator();

        if (self::$jsonSchema === null) {
            self::$jsonSchema = json_encode($this->getSchema(), JSON_THROW_ON_ERROR);
        }
    }

    private function getSchema(): array
    {
        return [
            '$schema'              => 'http://json-schema.org/draft-07/schema#',
            'type'                 => 'object',
            'additionalProperties' => false,
            'properties' => [
                Key::ENABLE_NOTIFICATIONS                                       => ['type' => 'boolean'],
                Key::ONLY_COLLABORATOR_INVITED_BY_USER_NOTIFICATION             => ['type' => 'boolean'],
                Key::NOTIFy_ON_UPDATE                                           => ['type' => 'boolean'],
                Key::NOTIFY_ON_NEW_BOOKMARK                                     => ['type' => 'boolean'],
                Key::NOTIFY_ON_COLLABORATOR_EXIT                                => ['type' => 'boolean'],
                Key::NOTIFY_ON_COLLABORATOR_EXIT_ONLY_WHEN_HAS_WRITE_PERMISSION => ['type' => 'boolean'],
                Key::NOTIFY_ON_BOOKMARK_DELETED                                 => ['type' => 'boolean'],
                Key::NEW_COLLABORATOR_NOTIFICATION                              => ['type' => 'boolean'],
            ],
        ];
    }

    /**
     * @throws InvalidFolderSettingException
     */
    public function validate(FolderSettings $settings): void
    {
        try {
            $this->jsonValidator->validate($settings->toArray(), self::$jsonSchema);
        } catch (InvalidJsonException $th) {
            throw new InvalidFolderSettingException($th->getMessage(), 1777);
        }
    }
}
