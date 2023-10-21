<?php

declare(strict_types=1);

namespace App\Http\Requests;

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
            'name'  => [
                'string',
                'max:50',
                'filled',
                Rule::requiredIf($this->isCreateFolderRequest()),
                Rule::when(!$this->isCreateFolderRequest(), [Rule::requiredIf(!$this->hasAny('description', 'visibility'))])
            ],
            'description' => ['nullable', 'string', 'max:150'],
            'visibility'  => ['nullable', 'string', 'in:public,private'],
            'settings'    => [Rule::when($this->isCreateFolderRequest(), ['sometimes', 'bail', 'json', 'filled', new FolderSettingsRule()])],
            'folder'      => [Rule::requiredIf(!$this->isCreateFolderRequest()), new ResourceIdRule()],
            'password'    => [Rule::requiredIf(!$this->isCreateFolderRequest() && $this->input('visibility') === 'public'), 'filled', 'string']
        ];
    }
}
