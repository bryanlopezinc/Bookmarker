<?php

declare(strict_types=1);

namespace App\Contracts;

interface HasHttpRuleInterface
{
    public function getRuleForHttpInputValidation(): mixed;
}
