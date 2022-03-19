<?php

declare(strict_types=1);

namespace App\Rules;

use App\ValueObjects\Tag;
use App\Exceptions\InvalidTagException;
use Illuminate\Contracts\Validation\Rule;

class TagRule implements Rule
{
    private string $message;

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     */
    public function passes($attribute, $value): bool
    {
        try {
            new Tag($value);

            return true;
        } catch (InvalidTagException $e) {
            $this->message = [
                $e::EMPTY_TAG_CODE          => 'Tag cannot be empty',
                $e::INVALID_MAX_LENGHT_CODE => 'Tag length must not be greater than ' . Tag::MAX_LENGTH,
                $e::APLHA_NUM_CODE          => 'Tag can only contain aplha numeric characters',
            ][$e->getCode()];

            return false;
        }
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->message;
    }
}
