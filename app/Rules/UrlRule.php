<?php

declare(strict_types=1);

namespace App\Rules;

use App\Exceptions\MalformedURLException;
use App\ValueObjects\Url;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Validation\Concerns\ValidatesAttributes;

final class UrlRule implements Rule
{
    use ValidatesAttributes;

    protected string|array $message;

    /**
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $this->message = "The $attribute must be a valid url";

        if (!is_string($value)) {
            return false;
        }

        try {
            new Url($value);
            
            return true;
        } catch (MalformedURLException) {
            return false;
        }
    }

    /**
     * @return string|array
     */
    public function message()
    {
        return $this->message;
    }
}
