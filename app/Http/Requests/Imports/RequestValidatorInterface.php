<?php

declare(strict_types=1);

namespace App\Http\Requests\Imports;

interface RequestValidatorInterface
{
    /**
     * @return array<string,mixed>
     */
    public function rules(): array;
}
