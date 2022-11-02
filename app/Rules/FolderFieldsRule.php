<?php

declare(strict_types=1);

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule as RuleContract;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Rule;

final class FolderFieldsRule implements RuleContract
{
    private const ALLOWED = [
        "id",
        "name",
        "description",
        "has_description",
        "date_created",
        "last_updated",
        "is_public",
        'tags',
        'has_tags',
        'tags_count',
        'storage',
        'storage.items_count',
        'storage.capacity',
        'storage.is_full',
        'storage.available',
        'storage.percentage_used'
    ];

    private MessageBag $errors;

    public function __construct()
    {
        $this->errors = new MessageBag;
    }

    /**
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $validator = Validator::make([$attribute => $value], [
            $attribute => ['array', Rule::in(self::ALLOWED)],
            "$attribute.*" => ['distinct:strict']
        ]);

        $this->errors->addIf(
            $this->hasDuplicateStorageTypes($value),
            $attribute,
            'Cannot request storage with a storage child field'
        );

        $this->errors->merge($validator->errors());

        return $this->errors->isEmpty();
    }

    private function hasDuplicateStorageTypes(array $fields): bool
    {
        $hasStorageType = collect($fields)->filter(function (string $field) {
            return in_array($field, [
                'storage.items_count',
                'storage.capacity',
                'storage.is_full',
                'storage.available',
                'storage.percentage_used'
            ], true);
        })->isNotEmpty();

        return in_array('storage', $fields, true) && $hasStorageType;
    }

    /**
     * @return string|array
     */
    public function message()
    {
        return $this->errors->all();
    }
}
