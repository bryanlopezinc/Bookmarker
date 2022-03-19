<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\ResourceIdRule;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateBookmarkRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = collect((new CreateBookmarkRequest())->rules())->except('url')->put('id', ['required', new ResourceIdRule]);

        $rules->put('title', collect($rules['title'])->reject('nullable')->push('required_without_all:tags')->values()->all());
        $rules->put('tags', collect($rules['tags'])->reject('nullable')->values()->all());

        return $rules->all();
    }
}
