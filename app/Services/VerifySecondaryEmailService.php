<?php

declare(strict_types=1);

namespace App\Services;

use App\Cache\EmailVerificationCodeRepository as PendingVerifications;
use App\Exceptions\HttpException;
use App\Models\SecondaryEmail;
use App\ValueObjects\TwoFACode;
use Illuminate\Http\Response;

final class VerifySecondaryEmailService
{
    public function __construct(private PendingVerifications $pendingVerifications)
    {
    }

    public function verify(int $authUserId, string $secondaryEmail, TwoFACode $twoFACode): void
    {
        if (!$this->pendingVerifications->has($authUserId)) {
            $this->throwNoPendingVerificationException();
        }

        $verificationData = $this->pendingVerifications->get($authUserId);

        if (
            !$verificationData->twoFACode->equals($twoFACode) ||
            $verificationData->email !== $secondaryEmail
        ) {
            $this->throwNoPendingVerificationException();
        }

        $record = SecondaryEmail::query()->firstOrCreate(['email' => $secondaryEmail], [
            'user_id'     => $authUserId,
            'verified_at' => now()
        ]);

        if (!$record->wasRecentlyCreated) {
            throw HttpException::forbidden(['message' => 'EmailAlreadyExists']);
        }

        $this->pendingVerifications->forget($authUserId);
    }

    public function throwNoPendingVerificationException(): void
    {
        throw new HttpException(['message' => 'VerificationCodeInvalidOrExpired'], Response::HTTP_BAD_REQUEST);
    }
}
