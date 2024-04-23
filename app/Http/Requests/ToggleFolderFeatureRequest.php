<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Feature;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

final class ToggleFolderFeatureRequest extends FormRequest
{
    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        $requiredIfNone = Rule::requiredIf( ! $this->hasAny(
            Arr::except(Feature::publicIdentifiers(), Feature::ADD_BOOKMARKS->value)
        ));

        $rules = [
            'addBookmarks' => ['in:enable,disable', $requiredIfNone],
        ];

        foreach([
            'removeBookmarks',
            'inviteUsers',
            'removeUser',
            'updateFolderName',
            'updateFolderDescription',
            'joinFolder',
            'updateFolderIcon',
        ] as $attribute) {
            $rules[$attribute] = ['sometimes', 'in:enable,disable'];
        }

        return $rules;
    }
}
