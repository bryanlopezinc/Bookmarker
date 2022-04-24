<?php

declare(strict_types=1);

namespace App\TwoFA;

use RuntimeException;

final class InvalidVerificationCodeException extends RuntimeException
{
}