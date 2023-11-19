<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\ResourceIdRule;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateCollaboratorActionRequest extends FormRequest
{
    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            'folder_id'    => ['required', new ResourceIdRule()],
            'addBookmarks' => ['sometimes', 'boolean'],
        ];
    }
}
