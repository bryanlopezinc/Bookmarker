<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Http\Request;

enum TwoFaMode: string
{
    case NONE  = 'None';
    case EMAIL = 'Email';

    public static function fromRequest(Request $request, string $key = 'two_fa_mode'): self
    {
        return match ($request->input($key)) {
            default => self::NONE,
            'none'  => self::NONE,
            'email' => self::EMAIL,
        };
    }
}
