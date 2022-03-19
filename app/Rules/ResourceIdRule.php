<?php

declare(strict_types=1);

namespace App\Rules;

use App\Exceptions\InvalidResourceIdException;
use App\ValueObjects\ResourceId;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Validation\Concerns\ValidatesAttributes;

final class ResourceIdRule implements Rule
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
        if (!$this->validateInteger($attribute, $value)) {
            $this->message = sprintf('The %s attribute is invalid', $attribute);

            return false;
        }

        try {
            new ResourceId((int) $value);

            return true;
        } catch (InvalidResourceIdException) {
            $this->message = sprintf('The %s attribute is invalid', $attribute);

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
