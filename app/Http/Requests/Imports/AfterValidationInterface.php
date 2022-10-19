<?php

declare(strict_types=1);

namespace App\Http\Requests\Imports;

use Illuminate\Validation\Validator;

interface AfterValidationInterface
{
    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void;
}
