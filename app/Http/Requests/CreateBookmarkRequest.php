<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\ValueObjects\BookmarkTitle;
use App\ValueObjects\Tag;
use Illuminate\Foundation\Http\FormRequest;

final class CreateBookmarkRequest extends FormRequest
{
    /** The maximum tags that can be attached to a bookmark */
    public const MAX_TAGS = 15;

    public function rules(): array
    {
        return [
            'url' => ['required', 'url'],
            'description' => ['nullable', 'max:200', 'filled'],
            'tags' => ['nullable', 'filled', 'max:' . self::MAX_TAGS],
            'tags.*' => Tag::rules(),
            'title' => ['nullable', ...BookmarkTitle::rules()]
        ];
    }
}
