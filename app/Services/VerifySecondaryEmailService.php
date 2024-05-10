<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\EmailVerificationCodeRepository as PendingVerifications;
use App\Exceptions\HttpException;
use App\Exceptions\SecondaryEmailAlreadyVerifiedException;
use App\Models\SecondaryEmail;
use App\ValueObjects\TwoFACode;
use Illuminate\Http\Response;

final class VerifySecondaryEmailService
{
    public function __construct(private PendingVerifications $pendingVerifications)
    {
    }

    /**
     * @throws SecondaryEmailAlreadyVerifiedException
     * @throws HttpException
     */
    public function verify(int $authUserId, string $secondaryEmail, TwoFACode $twoFACode): void
    {
        if ( ! $this->pendingVerifications->has($authUserId)) {
            $this->throwNoPendingVerificationException();
        }

        $verificationData = $this->pendingVerifications->get($authUserId);

        if (
            ! $verificationData->twoFACode->equals($twoFACode) ||
            $verificationData->email !== $secondaryEmail
        ) {
            $this->throwNoPendingVerificationException();
        }

        $record = SecondaryEmail::query()
            ->firstOrCreate(['email' => $secondaryEmail], [
                'user_id'     => $authUserId,
                'verified_at' => now()
            ]);

        $wasRecentlyVerifiedByAuthUser = ! $record->wasRecentlyCreated && $record->user_id === $authUserId;

        if ($wasRecentlyVerifiedByAuthUser) {
            throw new SecondaryEmailAlreadyVerifiedException();
        }

        if ($record->wasRecentlyCreated) {
            return;
        }

        $this->throwNoPendingVerificationException();
    }

    public function throwNoPendingVerificationException(): void
    {
        throw new HttpException(['message' => 'VerificationCodeInvalidOrExpired'], Response::HTTP_NOT_FOUND);
    }
}
