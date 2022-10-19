<?php

declare(strict_types=1);

namespace App\Http\Requests\Imports;

final class PocketImportRequestValidator implements RequestValidatorInterface
{
    public function rules(): array
    {
        return [
            'use_timestamp' => ['nullable', 'bool'],
            'ignore_tags' => ['nullable', 'bool'],
            'pocket_export_file' => ['required', 'file', 'mimes:html', join(':', ['max', setting('MAX_POCKET_FILE_SIZE')])],
        ];
    }
}
