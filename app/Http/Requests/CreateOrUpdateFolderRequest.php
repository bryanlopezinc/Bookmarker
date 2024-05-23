<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\FolderVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Rules\FolderSettingRule;

final class CreateOrUpdateFolderRequest extends FormRequest
{
    public function rules(): array
    {
        $maxIconFileSize = setting('MAX_FOLDER_ICON_SIZE');

        return [
            'name'            => $this->folderNameRules(),
            'description'     => ['nullable', 'string', 'max:150'],
            'visibility'      => ['nullable', 'string', 'in:public,private,collaborators,password_protected'],
            'settings'        => ['bail', 'sometimes', 'array', 'filled', new FolderSettingRule()],
            'password'        => ['sometimes', 'filled', 'string'],
            'icon'            => ['nullable', 'image', "max:{$maxIconFileSize}"],
            'folder_password' => [Rule::requiredIf(FolderVisibility::fromRequest($this)->isPasswordProtected()), 'string', 'filled'],
        ];
    }

    private function folderNameRules(): array
    {
        $isCreateFolderRequest = $this->routeIs('createFolder');

        return [
            'string',
            'max:50',
            'filled',
            Rule::requiredIf($isCreateFolderRequest),
            Rule::when(
                ! $isCreateFolderRequest,
                [Rule::requiredIf( ! $this->hasAny('description', 'visibility', 'folder_password', 'settings', 'icon'))]
            )
        ];
    }
}
