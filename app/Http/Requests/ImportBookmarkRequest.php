<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\ValueObjects\Tag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ImportBookmarkRequest extends FormRequest
{
    public function rules(): array
    {
        $source = $this->input('source', 0);

        $defaultRules = [
            'source' => ['required', 'string', 'filled', Rule::in(['chromeExportFile', 'pocketExportFile', 'safariExportFile'])],
        ];

        $sourceRulesMap = [
            'chromeExportFile' => $this->chromeImportRules(),
            'pocketExportFile' => $this->pocketImportRules(),
            'safariExportFile' => $this->safariImportRules(),
        ];

        if (!isset($sourceRulesMap[$source])) {
            return $defaultRules;
        }

        return array_merge($defaultRules, $sourceRulesMap[$source]);
    }

    private function safariImportRules(): array
    {
        return [
            'safari_html' => ['required', 'file', 'mimes:html', join(':', ['max', setting('MAX_SAFARI_FILE_SIZE')])],
            'tags' => ['nullable', join(':', ['max', setting('MAX_BOOKMARKS_TAGS')])],
            'tags.*' => Tag::rules(['distinct:strict']),
        ];
    }

    private function chromeImportRules(): array
    {
        return [
            'use_timestamp' => ['nullable', 'bool'],
            'html' => ['required', 'file', 'mimes:html', join(':', ['max', setting('MAX_CHROME_FILE_SIZE')])],
            'tags' => ['nullable', join(':', ['max', setting('MAX_BOOKMARKS_TAGS')])],
            'tags.*' => Tag::rules(['distinct:strict']),
        ];
    }

    private function pocketImportRules(): array
    {
        return [
            'use_timestamp' => ['nullable', 'bool'],
            'ignore_tags' => ['nullable', 'bool'],
            'pocket_export_file' => ['required', 'file', 'mimes:html', join(':', ['max', setting('MAX_POCKET_FILE_SIZE')])],
        ];
    }
}
