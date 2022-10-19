<?php

declare(strict_types=1);

namespace App\Http\Requests\Imports;

use App\ValueObjects\Tag;

final class InstapaperRequestValidator implements RequestValidatorInterface
{
    public function rules(): array
    {
        return [
            'instapaper_html' => ['required', 'file', 'mimes:html', join(':', ['max', setting('MAX_SAFARI_FILE_SIZE')])],
            'tags' => ['nullable', join(':', ['max', setting('MAX_BOOKMARKS_TAGS')])],
            'tags.*' => Tag::rules(['distinct:strict']),
        ];
    }
}
