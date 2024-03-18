<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\RoleNameRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\UAC;

final class CreateOrUpdateRoleRequest extends FormRequest
{
    protected function isCreateRoleRequest(): bool
    {
        return $this->routeIs('createFolderRole');
    }

    public function rules(): array
    {
        return [
            ...$this->permissionsRules(),
            'name' => ['required', new RoleNameRule()],
        ];
    }

    private function permissionsRules(): array
    {
        if (!$this->isCreateRoleRequest()) {
            return [];
        }

        return [
            'permissions'   => ['required', 'array', 'filled', Rule::in(UAC::validExternalIdentifiers())],
            'permissions.*' => ['filled', 'distinct:strict'],
        ];
    }
}
