<?php

declare(strict_types=1);

namespace App\Http\Requests\Imports;

final class EmptyValidator implements RequestValidatorInterface
{
    public function rules(): array
    {
        return [];
    }
}
