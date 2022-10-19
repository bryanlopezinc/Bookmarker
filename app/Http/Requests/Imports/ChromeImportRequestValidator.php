<?php

declare(strict_types=1);

namespace App\Http\Requests\Imports;

use App\ValueObjects\Tag;

final class ChromeImportRequestValidator implements RequestValidatorInterface
{
    public function rules(): array
    {
        return [
            'use_timestamp' => ['nullable', 'bool'],
            'html' => ['required', 'file', 'mimes:html', join(':', ['max', setting('MAX_CHROME_FILE_SIZE')])],
            'tags' => ['nullable', join(':', ['max', setting('MAX_BOOKMARKS_TAGS')])],
            'tags.*' => Tag::rules(['distinct:strict']),
        ];
    }
}
