<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\ResourceIdRule;
use App\ValueObjects\Tag;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteBookmarkTagsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id' => ['required', new ResourceIdRule],
            'tags' => ['required', 'filled', 'array'],
            'tags.*' => Tag::rules(),
        ];
    }
}
