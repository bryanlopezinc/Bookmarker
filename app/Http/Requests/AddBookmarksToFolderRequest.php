<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\ResourceIdRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AddBookmarksToFolderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'bookmarks' => ['required', 'array', 'max:30'],
            'bookmarks.*' => [new ResourceIdRule],
            'folder' => ['required', new ResourceIdRule],
            'make_hidden' => ['nullable', 'array'],
            'make_hidden.*' => [new ResourceIdRule, Rule::forEach(function () {
                return [
                    function ($attribute, $value, $fail) {
                        if (!in_array($value, $this->input('bookmarks'), true)) {
                            $fail("'BookmarkId $value does not exist in bookmarks.'");
                        }
                    }
                ];
            })]
        ];
    }
}
