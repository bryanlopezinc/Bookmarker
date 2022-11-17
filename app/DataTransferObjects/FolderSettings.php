<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Exception;
use Illuminate\Support\Arr;
use JsonSchema\Validator;

final class FolderSettings
{
    private const VERSIONS = ['1.0.0'];

    private readonly string $schema;

    /**
     * @param array<string,string|array|bool> $settings
     */
    public function __construct(private readonly array $settings)
    {
        $this->schema = file_get_contents(base_path('database/JsonSchema/folder_settings_1.0.0.json'));

        $this->validate();
    }

    public static function default(): self
    {
        return new self([
            'version' => '1.0.0',
            'notifications' => [
                'enabled' => true,
                'newCollaborator' => [
                    'notify' => true,
                    'onlyCollaboratorsInvitedByMe' => false,
                ],
                'updated' => true,
                'bookmarksAdded' => true,
                'bookmarksRemoved' => true,
                'collaboratorExit' => [
                    'notify' => true,
                    'onlyWhenCollaboratorHasWritePermission' => false,
                ]
            ]
        ]);
    }

    private function validate(): void
    {
        $validator = new Validator;
        $settings = json_decode(json_encode($this->settings));

        $validator->validate($settings, json_decode($this->schema));

        if (!$validator->isValid()) {
            throw new Exception(
                'The given settings is invalid. errors : ' . json_encode($validator->getErrors(), JSON_PRETTY_PRINT),
                1777
            );
        }

        if (!in_array($this->settings['version'], self::VERSIONS, true)) {
            throw new Exception('The given settings version is invalid.', 1779);
        }

        $this->ensureHasValidState();
    }

    private function ensureHasValidState(): void
    {
        $errorMessages = [];

        if ($this->get('notifications.newCollaborator') == [
            'notify' => false,
            'onlyCollaboratorsInvitedByMe' => true,
        ]) {
            $errorMessages[] = "The newCollaborator settings combination is invalid";
        }

        if ($this->get('notifications.collaboratorExit') == [
            'notify' => false,
            'onlyWhenCollaboratorHasWritePermission' => true,
        ]) {
            $errorMessages[] = "The collaboratorExit settings combination is invalid";
        }

        if (!empty($errorMessages)) {
            throw new Exception(
                'The given settings is invalid. errors : ' . json_encode($errorMessages, JSON_PRETTY_PRINT),
                1778
            );
        }
    }

    private function get(string $key): mixed
    {
        return Arr::get($this->settings, $key, fn () => throw new Exception('Invalid key ' . $key));
    }

    /**
     * @return array<string,string|array|bool>
     */
    public function toArray(): array
    {
        return $this->settings;
    }
}
