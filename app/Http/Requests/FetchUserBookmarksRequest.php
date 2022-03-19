<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\ResourceIdRule;
use App\Rules\TagRule;
use Illuminate\Foundation\Http\FormRequest;

final class FetchUserBookmarksRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'site_id' => ['nullable', new ResourceIdRule],
            'tag' => ['nullable', new TagRule]
        ];
    }
}
