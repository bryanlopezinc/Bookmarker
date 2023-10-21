<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\FolderPermission;
use App\PaginationData;
use App\Rules\ResourceIdRule;
use App\UAC;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class FetchFolderCollaboratorsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'folder_id' => ['required', new ResourceIdRule()],
            'permissions' => [
                'sometimes',
                'array',
                'in:readOnly,addBookmarks,removeBookmarks,inviteUser,updateFolder'
            ],
            'permissions.*' => ['distinct:strict', 'filled'],
            ...PaginationData::new()->asValidationRules()
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
        $validator->after(function (Validator $validator) {
            if (filled($validator->failed())) {
                return;
            }

            $filter = collect($this->input('permissions', [])); // @phpstan-ignore-line

            $filter->when($filter->contains('readOnly') && $filter->count() > 1, function () use ($validator) {
                $validator->errors()->add(
                    'permissions',
                    'Cannot request collaborator with only view permissions with any other permission'
                );
            });
        });
    }

    public function getFilter(): ?UAC
    {
        $filtersCount = count($this->validated('permissions', []));

        if ($filtersCount === 0) {
            return null;
        }

        if ($filtersCount === 4) {
            return UAC::all();
        }

        if ($this->validated('permissions.0') === 'readOnly') {
            return new UAC([FolderPermission::VIEW_BOOKMARKS]);
        }

        return UAC::fromRequest($this, 'permissions');
    }
}
