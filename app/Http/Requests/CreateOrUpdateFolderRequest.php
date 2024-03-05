<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\FolderVisibility;
use App\Rules\FolderSettings\FolderSettingsRootNodesRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Repositories\Folder\HttpFolderSettingSchema as Schema;

final class CreateOrUpdateFolderRequest extends FormRequest
{
    protected function isCreateFolderRequest(): bool
    {
        return $this->routeIs('createFolder');
    }

    public function rules(): array
    {
        return [
            'name'            => $this->folderNameRules(),
            'description'     => ['nullable', 'string', 'max:150'],
            'visibility'      => ['nullable', 'string', 'in:public,private,collaborators,password_protected'],
            'settings'        => ['bail', 'sometimes', 'array', 'filled', new FolderSettingsRootNodesRule(new Schema())],
            'password'        => ['sometimes', 'filled', 'string'],
            'folder_password' => [Rule::requiredIf(FolderVisibility::fromRequest($this)->isPasswordProtected()), 'string', 'filled'],
            ...$this->folderSettingsRules()
        ];
    }

    private function folderNameRules(): array
    {
        return [
            'string',
            'max:50',
            'filled',
            Rule::requiredIf($this->isCreateFolderRequest()),
            Rule::when(
                !$this->isCreateFolderRequest(),
                [Rule::requiredIf(!$this->hasAny('description', 'visibility', 'folder_password', 'settings'))]
            )
        ];
    }

    private function folderSettingsRules(): array
    {
        $schema = new Schema();

        $rules = [];

        foreach ($schema->rules() as $key => $value) {
            $rules["settings.{$key}"] = $value;
        }

        return $rules;
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach (array_keys($this->folderSettingsRules()) as $key) {
            $attributes[$key] = $key;
        }

        return $attributes;
    }
}
