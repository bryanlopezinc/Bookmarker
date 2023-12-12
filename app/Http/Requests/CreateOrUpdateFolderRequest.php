<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\FolderVisibility;
use App\Rules\FolderSettingsRule;
use App\Rules\ResourceIdRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'settings'        => $this->folderSettingsRules(),
            'folder'          => [Rule::requiredIf(!$this->isCreateFolderRequest()), new ResourceIdRule()],
            'password'        => $this->passwordRules(),
            'folder_password' => [Rule::requiredIf(FolderVisibility::fromRequest($this)->isPasswordProtected()), 'string', 'filled']
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
                [Rule::requiredIf(!$this->hasAny('description', 'visibility', 'folder_password'))]
            )
        ];
    }

    private function passwordRules(): array
    {
        return [
            'filled',
            'string',
            Rule::requiredIf(!$this->isCreateFolderRequest() && $this->input('visibility') === 'public'),
        ];
    }

    private function folderSettingsRules(): array
    {
        return [
            Rule::when(
                $this->isCreateFolderRequest(),
                ['sometimes', 'bail', 'json', 'filled', new FolderSettingsRule()]
            )
        ];
    }
}
