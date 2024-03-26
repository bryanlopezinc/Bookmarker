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
        $onAndOfRule = 'in:enable,disable';

        return [
            'folder_id'        => ['required', new ResourceIdRule()],
            'addBookmarks'     => ['string', $onAndOfRule, Rule::requiredIf( ! $this->hasAny('removeBookmarks', 'inviteUsers', 'updateFolder', 'removeUser'))],
            'removeBookmarks'  => ['sometimes', 'string', $onAndOfRule],
            'inviteUsers'      => ['sometimes', 'string', $onAndOfRule],
            'updateFolder'     => ['sometimes', 'string', $onAndOfRule],
            'removeUser'       => ['sometimes', 'string', $onAndOfRule],
        ];
    }
}
