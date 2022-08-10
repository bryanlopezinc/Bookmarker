<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;
use App\Cache\SecondaryEmailsVerificationCodesRepository as PendingVerifications;
use App\Exceptions\HttpException;
use App\ValueObjects\Email;
use App\ValueObjects\UserID;
use App\ValueObjects\VerificationCode;
use Illuminate\Http\Response;

final class VerifySecondaryEmailService
{
    public function __construct(private UserRepository $userRepository, private PendingVerifications $pendingVerifications)
    {
    }

    public function verify(UserID $userID, Email $secondaryEmail, VerificationCode $verificationCode): void
    {
        if (!$this->pendingVerifications->has($userID)) {
            throw new HttpException(['message' => 'Verification code expired'], Response::HTTP_BAD_REQUEST);
        }

        $verificationData = $this->pendingVerifications->get($userID);
        $unVerifiedEmailAddedByUser = $verificationData->email;
        $verificationCodeGeneratedOnBehalfOfUser = $verificationData->verificationCode;

        if (!$verificationCodeGeneratedOnBehalfOfUser->equals($verificationCode) || !$unVerifiedEmailAddedByUser->equals($secondaryEmail)) {
            throw new HttpException(['message' => 'Invalid verification code'], Response::HTTP_BAD_REQUEST);
        }

        if ($this->userRepository->secondaryEmailExists($secondaryEmail)) {
            throw HttpException::forbidden(['message' => 'Email already exists']);
        }

        $this->userRepository->addSecondaryEmail($secondaryEmail, $userID);

        $this->pendingVerifications->forget($userID);
    }
}
