<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\ResourceIdRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCollaboratorActionRequest extends FormRequest
{
    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            'folder_id'       => ['required', new ResourceIdRule()],
            'addBookmarks'    => ['boolean', Rule::requiredIf(!$this->hasAny('removeBookmarks', 'inviteUser', 'updateFolder'))],
            'removeBookmarks' => ['sometimes', 'boolean'],
            'inviteUser'      => ['sometimes', 'boolean'],
            'updateFolder'    => ['sometimes', 'boolean'],
        ];
    }
}
