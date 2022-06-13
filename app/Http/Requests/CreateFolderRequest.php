<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\ResourceIdRule;
use App\ValueObjects\FolderDescription;
use App\ValueObjects\FolderName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateFolderRequest extends FormRequest
{
    public function rules(): array
    {
        $rules = [
            'name' => ['string', 'max:' . FolderName::MAX, 'filled'],
            'description' => ['nullable', 'string', 'max:' . FolderDescription::MAX],
            'is_public' => ['nullable', 'bool']
        ];

        if ($this->routeIs('createFolder')) {
            $rules['name'] = ['required', ...$rules['name']];
        }

        if ($this->routeIs('updateFolder')) {
            $rules['name'] = [Rule::requiredIf(!$this->has('description')), ...$rules['name']];
            $rules['folder'] = ['required', new ResourceIdRule];
        }

        return $rules;
    }
}
