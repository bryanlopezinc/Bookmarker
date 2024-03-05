<?php

declare(strict_types=1);

namespace App\Repositories\Folder;

use App\Contracts\FolderSettingSchemaProviderInterface as SchemaProvider;
use App\Contracts\HasHttpRuleInterface;

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
            $settingRules = $setting->rules;

            if ($setting->Id === 'version') {
                continue;
            }

            $rules[$setting->Id] = $this->replaceStrictRulesWithHttpRules($settingRules);
        }

        return $rules;
    }

    private function replaceStrictRulesWithHttpRules(array $rules): array
    {
        foreach ($rules as $key => $validationRule) {
            if ($validationRule instanceof HasHttpRuleInterface) {
                $rules[$key] = $validationRule->getRuleForHttpInputValidation();
            }
        }

        return $rules;
    }

    public function exists(string $settingId): bool
    {
        return $settingId !== 'version' && $this->repository->exists($settingId);
    }
}
