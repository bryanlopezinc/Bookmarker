<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\RoleNameRule;
use App\UAC;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SendFolderCollaborationInviteRequest extends FormRequest
{
    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        $maxRoles = setting('MAX_ROLES_ATTACHED_TO_INVITES');

        return [
            'email'         => ['required', 'email'],
            'permissions'   => ['sometimes', 'array', Rule::in(['*', ...UAC::validExternalIdentifiers()])],
            'permissions.*' => ['filled', 'distinct:strict'],
            'roles'         => ['array', 'filled', "max:{$maxRoles}"],
            'roles.*'       => ['distinct:strict', new RoleNameRule()],
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            if (filled($validator->failed())) {
                return;
            }

            if (!in_array('*', $permissions = $this->input('permissions', []), true)) {
                return;
            }

            if (count($permissions) > 1) {
                $validator->errors()->add(
                    'permissions',
                    'The permissions field cannot contain any other value with the * wildcard.'
                );
            }
        });
    }
}
