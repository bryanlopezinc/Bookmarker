<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\ResourceIdRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class AddBookmarksToFolderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'bookmarks'     => ['required', 'array', 'max:50'],
            'bookmarks.*'   => [new ResourceIdRule(), 'distinct:strict'],
            'make_hidden'   => ['nullable', 'array'],
            'make_hidden.*' => [new ResourceIdRule(), 'distinct:strict',]
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

            $bookmarkIDs = $this->input('bookmarks');

            foreach ($this->input('make_hidden', []) as $key => $id) {
                if (!in_array($id, $bookmarkIDs, true)) {
                    $validator->errors()->add('make_hidden.' . $key, "BookmarkId $id does not exist in bookmarks.");
                }
            }
        });
    }
}
