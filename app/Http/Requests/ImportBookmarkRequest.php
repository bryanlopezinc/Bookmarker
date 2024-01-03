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

    private const VALID_SOURCES = [
        self::CHROME,
        self::POCKET,
        self::SAFARI,
        self::INSTAPAPER,
        self::FIREFOX
    ];

    public function rules(): array
    {
        return [
            'source'                 => ['required', 'string', 'filled', Rule::in(self::VALID_SOURCES)],
            'html'                   => ['required', 'file', 'mimes:html', 'max:1000'],
            'tags'                   => ['sometimes', 'array', 'max:15'],
            'tags.*'                 => ['distinct:strict', new TagRule()],
            'include_bookmark_tags'  => ['sometimes', 'bool'],
            'bookmark_tags_exceeded' => ['sometimes', 'in:slice,skip_bookmark,fail_import'],
            'invalid_bookmark_tag'   => ['sometimes', 'string', 'in:skip_bookmark,fail_import,skip_tag'],
            'tags_merge_overflow'    => ['sometimes', 'in:skip_bookmark,fail_import,ignore_all_tags'],
            'merge_strategy'         => ['sometimes', 'string', 'in:user_defined_tags_first,import_file_tags_first'],
        ];
    }
}
