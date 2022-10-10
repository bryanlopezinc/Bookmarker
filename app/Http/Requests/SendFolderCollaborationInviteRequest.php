<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\ResourceIdRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SendFolderCollaborationInviteRequest extends FormRequest
{
    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'folder_id' => ['required', new ResourceIdRule],
            'permissions' => ['required', 'array', Rule::in([
                'viewBookmarks'
            ])],
            'permissions.*' => ['filled', 'distinct:strict'],
        ];
    }
}
