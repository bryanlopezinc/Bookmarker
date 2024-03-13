<?php

declare(strict_types=1);

namespace App\Repositories\Folder;

use App\Rules\FolderSettings\BooleanRule;
use App\Rules\DistinctRule;
use Illuminate\Support\Str;
use App\DataTransferObjects\FolderSettingSchema;
use App\Contracts\FolderSettingSchemaProviderInterface;
use App\Rules\FolderSettings\IntegerRule;
use App\Rules\FolderSettings\FolderSettingsRootNodesRule;

final class FolderSettingsSchema implements FolderSettingSchemaProviderInterface
{
    /**
     * @var array<string,FolderSettingSchema>
     */
    private static array $all;

    /**
     * @var array<array<string,array>>
     */
    private static array $rules;

    public function __construct()
    {
        $this->cacheDefinition();

        $this->cacheRules();
    }

    private function cacheDefinition(): void
    {
        if (isset(self::$all)) {
            return;
        }

        self::$all = collect($this->definition())->mapWithKeys(function (array $schema) {
            $schemaDTO = new FolderSettingSchema(
                $schema['id'],
                $schema['default'],
                $schema['rules'],
                $schema['type']
            );

            return [(string)$schema['id'] => $schemaDTO];
        })->all();
    }

    private function cacheRules(): void
    {
        if (isset(self::$rules)) {
            return;
        }

        $rules = [
            Str::random(32) => [new FolderSettingsRootNodesRule($this)]
        ];

        foreach (self::$all as $id => $schema) {
            $rules[$id] = $schema->rules;
        }

        self::$rules = $rules;
    }

    /**
     * @return array<string,array>[]
     */
    public function rules(): array
    {
        return self::$rules;
    }

    public function exists(string $settingId): bool
    {
        return array_key_exists($settingId, self::$all);
    }

    /**
     * @return array<FolderSettingSchema>
     */
    public function schema(): array
    {
        return self::$all;
    }

    /**
     * @return array{
     *  id : string,
     *  default : mixed,
     *  rules : array,
     *  type : string
     * }[]
     */
    private function definition(): array
    {
        $booleanRule = new BooleanRule();

        return [
            [
                'id'      => 'version',
                'default' => '1.0.0',
                'rules'   => ['required', 'string', 'in:1.0.0'],
                'type'    => 'string'
            ],
            [
                'id'      => 'maxCollaboratorsLimit',
                'default' => -1,
                'rules'   => ['sometimes', 'int', 'min:-1', new IntegerRule(), 'max:' . setting('MAX_FOLDER_COLLABORATORS_LIMIT')],
                'type'    => 'integer'
            ],
            [
                'id'      => 'acceptInviteConstraints',
                'default' => [],
                'rules'   => ['sometimes', 'array', new DistinctRule(), 'in:InviterMustBeAnActiveCollaborator,InviterMustHaveRequiredPermission'],
                'type'    => 'array'
            ],
            [
                'id'      => 'notifications',
                'rules'   => ['sometimes', 'array:enabled,newCollaborator,collaboratorExit,folderUpdated,newBookmarks,bookmarksRemoved'],
                'default' => [],
                'type'    => 'array'
            ],
            [
                'id'      => 'notifications.enabled',
                'rules'   => ['sometimes', $booleanRule],
                'default' => true,
                'type'    => 'boolean'
            ],
            [
                'id'      => 'notifications.newCollaborator',
                'rules'   => ['sometimes', 'array:enabled,mode'],
                'default' => [],
                'type'    => 'array'
            ],
            [
                'id'      => 'notifications.newCollaborator.enabled',
                'rules'   => ['sometimes', $booleanRule],
                'default' => true,
                'type'    => 'boolean'
            ],
            [
                'id'      => 'notifications.newCollaborator.mode',
                'rules'   => ['sometimes', 'string', 'in:*,invitedByMe'],
                'default' => '*',
                'type'    => 'string'
            ],
            [
                'id'      => 'notifications.collaboratorExit',
                'rules'   => ['sometimes', 'array:enabled,mode'],
                'default' => [],
                'type'    => 'array'
            ],
            [
                'id'      => 'notifications.collaboratorExit.enabled',
                'rules'   => ['sometimes', $booleanRule],
                'default' => true,
                'type'    => 'boolean'
            ],
            [
                'id'      => 'notifications.collaboratorExit.mode',
                'rules'   => ['sometimes', 'string', 'in:*,hasWritePermission'],
                'default' => '*',
                'type'    => 'string'
            ],
            [
                'id'      => 'notifications.folderUpdated',
                'rules'   => ['sometimes', 'array:enabled'],
                'default' => [],
                'type'    => 'array'
            ],
            [
                'id'      => 'notifications.folderUpdated.enabled',
                'rules'   => ['sometimes', $booleanRule],
                'default' => true,
                'type'    => 'boolean'
            ],
            [
                'id'      => 'notifications.newBookmarks',
                'rules'   => ['sometimes', 'array:enabled'],
                'default' => [],
                'type'    => 'array'
            ],
            [
                'id'      => 'notifications.newBookmarks.enabled',
                'rules'   => ['sometimes', $booleanRule],
                'default' => true,
                'type'    => 'boolean'
            ],
            [
                'id'      => 'notifications.bookmarksRemoved',
                'rules'   => ['sometimes', 'array:enabled'],
                'default' => [],
                'type'    => 'array'
            ],
            [
                'id'      => 'notifications.bookmarksRemoved.enabled',
                'rules'   => ['sometimes', $booleanRule],
                'default' => true,
                'type'    => 'boolean'
            ]
        ];
    }
}
