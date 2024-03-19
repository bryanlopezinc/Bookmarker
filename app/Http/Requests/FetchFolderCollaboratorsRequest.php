<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\PaginationData;
use App\Rules\RoleNameRule;
use App\UAC;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class FetchFolderCollaboratorsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'        => ['sometimes', 'filled', 'string', 'max:10'],
            'role'        => ['sometimes', new RoleNameRule()],
            'permissions' => [
                'sometimes',
                'array',
                Rule::in(['readOnly', ...UAC::validExternalIdentifiers()]),
            ],
            'permissions.*' => ['distinct:strict', 'filled'],
            ...PaginationData::new()->asValidationRules()
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function (Validator $validator) {
            if (filled($validator->failed())) {
                return;
            }

            $filter = collect($this->input('permissions', []));

            $filter->when($filter->contains('readOnly') && $filter->count() > 1, function () use ($validator) {
                $validator->errors()->add(
                    'permissions',
                    'Cannot request collaborator with only view permissions with any other permission'
                );
            });
        });
    }
}
