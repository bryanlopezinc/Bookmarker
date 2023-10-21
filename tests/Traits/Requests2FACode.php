<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Cache\User2FACodeRepository;
use App\ValueObjects\TwoFACode;

trait Requests2FACode
{
    private function get2FACode(int $userId): int
    {
        $code = TwoFACode::generate();

        /** @var User2FACodeRepository */
        $repository = app(User2FACodeRepository::class);

        $repository->put($userId, $code);

        return $code->value();
    }
}
