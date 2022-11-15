<?php

declare(strict_types=1);

namespace App\Services;

use App\Cache\SecondaryEmailVerificationCodeRepository as PendingVerifications;
use App\Exceptions\HttpException;
use App\Mail\TwoFACodeMail;
use App\Repositories\UserRepository;
use App\ValueObjects\Email;
use App\ValueObjects\TwoFACode;
use App\ValueObjects\UserID;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;

final class AddEmailToAccountService
{
    public function __construct(
        private UserRepository $userRepository,
        private PendingVerifications $pendingVerifications
    ) {
    }

    public function __invoke(UserID $userID, Email $secondaryEmail): void
    {
        $this->validateAction($userID, $secondaryEmail);

        $verificationCode = TwoFACode::generate();

        $this->pendingVerifications->put($userID, $secondaryEmail, $verificationCode, now()->addMinutes(5));

        Mail::to($secondaryEmail->value)->queue(new TwoFACodeMail($verificationCode));
    }

    private function validateAction(UserID $userID, Email $email): void
    {
        $userSecondaryEmails = $this->userRepository->getUserSecondaryEmails($userID);

        if (count($userSecondaryEmails) === setting('MAX_SECONDARY_EMAIL')) {
            throw HttpException::forbidden([
                'message' => 'Max emails reached',
                'error_code' => 142
            ]);
        }

        $this->ensureHasNoPendingVerification($userID);
    }

    /**
     * Secondary emails are verified one at a time since there is a limit on how many secondary emails a user can have
     * therefore it makes no sense to allow a user add any amount of emails (and send out useless emails) only to return a "max email reached"
     * response when the user is trying to verify an email with a verification code.
     */
    private function ensureHasNoPendingVerification(UserID $userID): void
    {
        if ($this->pendingVerifications->has($userID)) {
            throw new HttpException([
                'message' => 'Verify email',
                'error_code' => 3118
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
