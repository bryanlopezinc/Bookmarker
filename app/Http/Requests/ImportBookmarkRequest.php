<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\TagRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ImportBookmarkRequest extends FormRequest
{
    /** import sources */
    public const CHROME     = 'chromeExportFile';
    public const POCKET     = 'pocketExportFile';
    public const SAFARI     = 'safariExportFile';
    public const INSTAPAPER = 'instapaperFile';
    public const FIREFOX    = 'firefoxFile';

    public function rules(): array
    {
        $rules = match ($this->input('source')) {
            self::CHROME     => $this->chromeImportRules(),
            self::POCKET     => $this->pocketImportRules(),
            self::SAFARI     => $this->safariImportRules(),
            self::INSTAPAPER => $this->paperImportRules(),
            self::FIREFOX    => $this->fireFoxImportRules(),
            default          => []
        };

        return array_merge($rules, [
            'request_id' => ['required', 'uuid'],
            'source'     => [
                'required', 'string', 'filled', Rule::in([
                    self::CHROME,
                    self::POCKET,
                    self::SAFARI,
                    self::INSTAPAPER,
                    self::FIREFOX
                ])
            ]
        ]);
    }

    private function fireFoxImportRules(): array
    {
        return [
            'use_timestamp'       => ['nullable', 'bool'],
            'ignore_tags'         => ['nullable', 'bool'],
            'firefox_export_file' => ['required', 'file', 'mimes:html', 'max:5000'],
        ];
    }

    private function paperImportRules(): array
    {
        return [
            'instapaper_html' => [
                'required', 'file',
                'mimes:html',
                join(':', ['max', setting('MAX_SAFARI_FILE_SIZE')])
            ],
            'tags' => ['nullable', 'max:15'],
            'tags.*' => ['distinct:strict', new TagRule()],
        ];
    }

    private function safariImportRules(): array
    {
        return [
            'safari_html' => ['required', 'file', 'mimes:html', join(':', ['max', setting('MAX_SAFARI_FILE_SIZE')])],
            'tags'        => ['nullable', 'max:15'],
            'tags.*'      => ['distinct:strict', new TagRule()],
        ];
    }

    private function chromeImportRules(): array
    {
        return [
            'use_timestamp' => ['nullable', 'bool'],
            'html'          => ['required', 'file', 'mimes:html', join(':', ['max', setting('MAX_CHROME_FILE_SIZE')])],
            'tags'          => ['nullable', 'max:15'],
            'tags.*'        => ['distinct:strict', new TagRule()],
        ];
    }

    private function pocketImportRules(): array
    {
        return [
            'use_timestamp'      => ['nullable', 'bool'],
            'ignore_tags'        => ['nullable', 'bool'],
            'pocket_export_file' => ['required', 'file', 'mimes:html', 'max:5000'],
        ];
    }
}
