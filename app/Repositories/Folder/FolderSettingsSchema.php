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
use App\Utils\FolderSettingNormalizers as Normalizers;
use Illuminate\Support\Arr;

final class FolderSettingsSchema implements FolderSettingSchemaProviderInterface
{
    /**
     * @var array<string,FolderSettingSchema>
     */
    private static array $schema;

    /**
     * @var array<array<string,array>>
     */
    private static array $rules;

    /**
     * @return array<string,array>[]
     */
    public function rules(): array
    {
        if (isset(self::$rules)) {
            return self::$rules;
        }

        $rules = [Str::random(32) => [new FolderSettingsRootNodesRule($this)]];

        foreach ($this->schema() as $id => $schema) {
            $rules[$id] = $schema->rules;
        }

        return self::$rules = $rules;
    }

    public function exists(string $settingId): bool
    {
        return array_key_exists($settingId, $this->schema());
    }

    /**
     * @return array<FolderSettingSchema>
     */
    public function schema(): array
    {
        if (isset(self::$schema)) {
            return self::$schema;
        }

        return self::$schema = collect($this->definition())->mapWithKeys(function (array $schema) {
            $schemaDTO = new FolderSettingSchema(
                $schema['id'],
                $schema['default'],
                $schema['rules'],
                $schema['rulesExternal'] ?? $schema['rules'],
                $schema['type'],
                $schema['normalizer'] ?? new Normalizers\NoNormalizer(),
            );

            return [(string)$schema['id'] => $schemaDTO];
        })->all();
    }

    public function findById(string $id): FolderSettingSchema
    {
        return $this->schema()[$id];
    }

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
                'id'            => 'max_collaborators_limit',
                'default'       => -1,
                'type'          => 'integer',
                'normalizer'    => new Normalizers\IntegerValueNormalizer(),
                'rules'         => $maxCollaboratorsLimitKeyRules = [
                    'sometimes',
                    'int',
                    'min:-1',
                    new IntegerRule(),
                    'max:' . setting('MAX_FOLDER_COLLABORATORS_LIMIT')
                ],
                'rulesExternal' => Arr::except($maxCollaboratorsLimitKeyRules, 3),
            ],
            [
                'id'      => 'accept_invite_constraints',
                'type'    => 'array',
                'default' => [],
                'rules'   => [
                    'sometimes',
                    'array',
                    new DistinctRule(),
                    'in:InviterMustBeAnActiveCollaborator,InviterMustHaveRequiredPermission'
                ],
            ],
            [
                'id'      => 'notifications',
                'default' => [],
                'type'    => 'array',
                'rules'   => [
                    'sometimes',
                    'array:enabled,new_collaborator,collaborator_exit,folder_updated,new_bookmarks,bookmarks_removed'
                ],
            ],
            [
                'id'            => 'notifications.enabled',
                'rules'         => ['sometimes', $booleanRule],
                'rulesExternal' => ['sometimes', 'bool'],
                'default'       => true,
                'type'          => 'boolean',
                'normalizer'    => new Normalizers\BooleanValueNormalizer(),
            ],
            [
                'id'      => 'notifications.new_collaborator',
                'rules'   => ['sometimes', 'array:enabled,mode'],
                'default' => [],
                'type'    => 'array',
            ],
            [
                'id'            => 'notifications.new_collaborator.enabled',
                'rules'         => ['sometimes', $booleanRule],
                'rulesExternal' => ['sometimes', 'bool'],
                'default'       => true,
                'type'          => 'boolean',
                'normalizer'    => new Normalizers\BooleanValueNormalizer(),
            ],
            [
                'id'      => 'notifications.new_collaborator.mode',
                'rules'   => ['sometimes', 'string', 'in:*,invitedByMe'],
                'default' => '*',
                'type'    => 'string',
            ],
            [
                'id'      => 'notifications.collaborator_exit',
                'rules'   => ['sometimes', 'array:enabled,mode'],
                'default' => [],
                'type'    => 'array',
            ],
            [
                'id'            => 'notifications.collaborator_exit.enabled',
                'rules'         => ['sometimes', $booleanRule],
                'rulesExternal' => ['sometimes', 'bool'],
                'default'       => true,
                'type'          => 'boolean',
                'normalizer'    => new Normalizers\BooleanValueNormalizer(),
            ],
            [
                'id'      => 'notifications.collaborator_exit.mode',
                'rules'   => ['sometimes', 'string', 'in:*,hasWritePermission'],
                'default' => '*',
                'type'    => 'string',
            ],
            [
                'id'      => 'notifications.folder_updated',
                'rules'   => ['sometimes', 'array:enabled'],
                'default' => [],
                'type'    => 'array',
            ],
            [
                'id'            => 'notifications.folder_updated.enabled',
                'rules'         => ['sometimes', $booleanRule],
                'rulesExternal' => ['sometimes', 'bool'],
                'default'       => true,
                'type'          => 'boolean',
                'normalizer'    => new Normalizers\BooleanValueNormalizer(),
            ],
            [
                'id'      => 'notifications.new_bookmarks',
                'rules'   => ['sometimes', 'array:enabled'],
                'default' => [],
                'type'    => 'array',
            ],
            [
                'id'            => 'notifications.new_bookmarks.enabled',
                'rules'         => ['sometimes', $booleanRule],
                'rulesExternal' => ['sometimes', 'bool'],
                'default'       => true,
                'type'          => 'boolean',
                'normalizer'    => new Normalizers\BooleanValueNormalizer(),
            ],
            [
                'id'      => 'notifications.bookmarks_removed',
                'rules'   => ['sometimes', 'array:enabled'],
                'default' => [],
                'type'    => 'array',
            ],
            [
                'id'            => 'notifications.bookmarks_removed.enabled',
                'rules'         => ['sometimes', $booleanRule],
                'rulesExternal' => ['sometimes', 'bool'],
                'default'       => true,
                'type'          => 'boolean',
                'normalizer'    => new Normalizers\BooleanValueNormalizer(),
            ]
        ];
    }
}
