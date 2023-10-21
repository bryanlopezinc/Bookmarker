<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\PaginationData;
use App\Rules\ResourceIdRule;
use App\Rules\TagRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class FetchUserBookmarksRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'source_id' => ['nullable', new ResourceIdRule()],
            'tags'      => ['nullable', 'filled', 'max:15'],
            'tags.*'    => [new TagRule],
            'untagged'  => ['nullable', 'boolean', 'filled'],
            'sort'      => ['nullable', 'string', 'filled', Rule::in(['oldest', 'newest'])],
            ...PaginationData::new()->asValidationRules()
        ];
    }
}
