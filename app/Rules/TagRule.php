<?php

declare(strict_types=1);

namespace App\Rules;

use Illuminate\Support\Facades\Validator;
use Illuminate\Contracts\Validation\ValidationRule;

final class TagRule implements ValidationRule
{
    protected string|array $message;

    /**
     * {@inheritdoc}
     */
    public function validate($childAttribute, mixed $value, \Closure $fail): void
    {
        // We assume this rule is used to validate a numeric-key array
        // so we get the array key name for validation and to
        //maintain error message format like "The tags.2 must not be greater than X characters."
        $parent = explode('.', $childAttribute)[0];

        $validator = Validator::make(
            data: [$parent => $value],
            rules: [
                $parent => ['filled', 'max:22', 'string', 'bail']
            ],
            attributes: [$parent => $childAttribute]
        );

        if ($validator->fails()) {
            $fail($childAttribute, $validator->errors()->first());
        }
    }

    /**
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $passes = true;

        $fail = function () use (&$passes) {
            $passes = false;
        };

        $this->validate($attribute, $value, $fail);

        return $passes;
    }
}
