<?php

declare(strict_types=1);

namespace App\Repositories\Folder;

use App\Contracts\FolderSettingSchemaProviderInterface as SchemaProvider;

final class HttpFolderSettingSchema implements SchemaProvider
{
    private readonly FolderSettingsSchema $repository;

    public function __construct(FolderSettingsSchema $provider =  null)
    {
        $this->repository = $provider ??= new FolderSettingsSchema();
    }

    /**
     * @return array<string,array>[]
     */
    public function rules(): array
    {
        $rules = [];

        foreach ($this->repository->schema() as $setting) {
            if ($setting->Id === 'version') {
                continue;
            }

            $rules[$setting->Id] = $setting->rulesExternal;
        }

        return $rules;
    }

    public function exists(string $settingId): bool
    {
        return $settingId !== 'version' && $this->repository->exists($settingId);
    }
}
