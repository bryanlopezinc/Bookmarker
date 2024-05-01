<?php

declare(strict_types=1);

namespace App\Rules\PublicId;

use App\Contracts\ResourceNotFoundExceptionInterface;
use App\ValueObjects\PublicId\PublicId;
use Illuminate\Contracts\Validation\ValidationRule;
use Closure;

abstract class PublicIdRule implements ValidationRule
{
    abstract protected function make(string $value): PublicId;

    /**
     * {@inheritdoc}
     */
    public function validate($attribute, mixed $value, Closure $fail): void
    {
        try {
            $this->make($value);
        } catch (ResourceNotFoundExceptionInterface) {
            $fail("The {$attribute} attribute is invalid");
        }
    }
}
