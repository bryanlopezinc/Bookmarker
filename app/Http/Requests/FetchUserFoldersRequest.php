<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\PaginationData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class FetchUserFoldersRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'sort' => ['nullable', 'string', 'filled', Rule::in([
                'oldest',
                'newest',
                'most_items',
                'least_items',
                'updated_recently'
            ])],
            ...PaginationData::new()->asValidationRules()
        ];
    }
}
