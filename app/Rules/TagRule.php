<?php

declare(strict_types=1);

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Validator;

final class TagRule implements Rule
{
    protected string|array $message;

    /**
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $attribute = explode('.', $attribute)[0];

        $validator = Validator::make([$attribute => $value], [
            $attribute => ['filled', 'max:22', 'string']
        ]);

        $this->message = $validator->errors()->all();

        return $validator->passes();
    }

    /**
     * @return string|array
     */
    public function message()
    {
        return $this->message;
    }
}
