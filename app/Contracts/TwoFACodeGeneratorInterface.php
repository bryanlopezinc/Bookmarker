<?php

declare(strict_types=1);

namespace App\Contracts;

use App\ValueObjects\TwoFACode;

interface TwoFACodeGeneratorInterface
{
    public function generate(): TwoFACode;
}
