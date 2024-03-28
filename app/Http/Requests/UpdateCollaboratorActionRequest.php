<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Feature;
use App\Rules\ResourceIdRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

final class UpdateCollaboratorActionRequest extends FormRequest
{
    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        $onAndOfRule = 'in:enable,disable';

        $requiredIfNone = Rule::requiredIf( ! $this->hasAny(
            Arr::except(Feature::publicIdentifiers(), Feature::ADD_BOOKMARKS->value)
        ));

        return [
            'folder_id'               => ['required', new ResourceIdRule()],
            'addBookmarks'            => [$onAndOfRule, $requiredIfNone],
            'removeBookmarks'         => ['sometimes', $onAndOfRule],
            'inviteUsers'             => ['sometimes', $onAndOfRule],
            'updateFolder'            => ['sometimes', $onAndOfRule],
            'removeUser'              => ['sometimes', $onAndOfRule],
            'updateFolderName'        => ['sometimes', $onAndOfRule],
            'updateFolderDescription' => ['sometimes', $onAndOfRule],
        ];
    }
}
