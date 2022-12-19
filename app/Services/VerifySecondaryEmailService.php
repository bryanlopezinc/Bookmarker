<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;
use App\Cache\SecondaryEmailVerificationCodeRepository as PendingVerifications;
use App\Exceptions\HttpException;
use App\ValueObjects\Email;
use App\ValueObjects\UserID;
use App\ValueObjects\TwoFACode;
use Illuminate\Http\Response;

final class VerifySecondaryEmailService
{
    public function __construct(
        private UserRepository $userRepository,
        private PendingVerifications $pendingVerifications
    ) {
    }

    public function verify(UserID $userID, Email $secondaryEmail, TwoFACode $twoFACode): void
    {
        if (!$this->pendingVerifications->has($userID)) {
            $this->throwNoPendingVerificationException();
        }

        $verificationData = $this->pendingVerifications->get($userID);
        $unVerifiedEmailAddedByUser = $verificationData->email;
        $twoFACodeGeneratedOnBehalfOfUser = $verificationData->twoFACode;

        if (
            !$twoFACodeGeneratedOnBehalfOfUser->equals($twoFACode) ||
            !$unVerifiedEmailAddedByUser->equals($secondaryEmail)
        ) {
            $this->throwNoPendingVerificationException();
        }

        if ($this->userRepository->secondaryEmailExists($secondaryEmail)) {
            throw HttpException::forbidden(['message' => 'Email already exists']);
        }

        $this->userRepository->addSecondaryEmail($secondaryEmail, $userID);

        $this->pendingVerifications->forget($userID);
    }

    public function throwNoPendingVerificationException(): void
    {
        throw new HttpException(['message' => 'Verification code invalid or expired'], Response::HTTP_BAD_REQUEST);
    }
}
