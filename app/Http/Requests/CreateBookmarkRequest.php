<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\UrlRule;
use App\ValueObjects\BookmarkTitle;
use App\ValueObjects\Tag;
use Illuminate\Foundation\Http\FormRequest;

final class CreateBookmarkRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'url' => ['required', new UrlRule],
            'description' => ['nullable', 'max:200', 'filled'],
            'tags' => ['nullable', 'filled', join(':', ['max', setting('MAX_BOOKMARKS_TAGS')])],
            'tags.*' => Tag::rules(['distinct:strict']),
            'title' => ['nullable', ...BookmarkTitle::rules()]
        ];
    }
}
