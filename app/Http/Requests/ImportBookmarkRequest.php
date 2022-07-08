<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ImportBookmarkRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'source' => ['required', 'string', 'filled', Rule::in(['chromeExport'])],
            'export_file' => ['required', 'file', 'mimes:html', 'size:20000']
        ];
    }
}
