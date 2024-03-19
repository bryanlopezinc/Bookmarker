<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\ResourceIdRule;
use App\Rules\TagRule;
use App\Rules\UrlRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateOrUpdateBookmarkRequest extends FormRequest
{
    public function rules(): array
    {
        $isCreateBookmarkRequest = $this->routeIs('createBookmark');

        return [
            'url'         => [Rule::requiredIf($isCreateBookmarkRequest), new UrlRule()],
            'id'          => [Rule::requiredIf( ! $isCreateBookmarkRequest), new ResourceIdRule()],
            'description' => ['nullable', 'max:200', 'filled'],
            'tags'        => ['filled', 'max:15', 'array', Rule::when($isCreateBookmarkRequest, 'nullable')],
            'tags.*'      => ['distinct:strict', new TagRule()],
            'title'       => [
                Rule::when($isCreateBookmarkRequest, 'nullable', 'required_without_all:tags,description'),
                'filled',
                'string',
                'max:100'
            ]
        ];
    }
}
