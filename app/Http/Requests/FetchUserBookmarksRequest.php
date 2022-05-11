<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\ResourceIdRule;
use App\ValueObjects\Tag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class FetchUserBookmarksRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'site_id' => ['nullable', new ResourceIdRule],
            'tag' => Tag::rules(['nullable']),
            'untagged' => ['nullable', 'boolean', 'filled'],
            'sort' => ['nullable', 'string', 'filled', Rule::in(['oldest', 'newest'])],
        ];
    }
}
