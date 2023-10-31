<?php

declare(strict_types=1);

namespace App\Enums;

enum TwoFaMode: string
{
    case NONE  = 'None';
    case EMAIL = 'Email';
}
