<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\ValueObjects\BookmarkTitle;
use App\ValueObjects\Tag;
use Illuminate\Foundation\Http\FormRequest;

final class CreateBookmarkRequest extends FormRequest
{
    public const MAX_TAGS = 15;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'url'     => ['required', 'url'],
            'description' => ['nullable', 'max:200', 'filled'],
            'tags'    => ['nullable', 'max:' . self::MAX_TAGS],
            'tags.*'  => Tag::rules(),
            'title'   => ['nullable', ...BookmarkTitle::rules()]
        ];
    }
}
