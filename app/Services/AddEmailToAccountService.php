<?php

declare(strict_types=1);

namespace App\Services;

use App\Cache\EmailVerificationCodeRepository as PendingVerifications;
use App\Exceptions\HttpException;
use App\Mail\TwoFACodeMail;
use App\Repositories\UserRepository;
use App\ValueObjects\TwoFACode;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;

final class AddEmailToAccountService
{
    public function __construct(
        private UserRepository $userRepository,
        private PendingVerifications $pendingVerifications
    ) {
    }

    public function __invoke(int $authUserId, string $secondaryEmail): void
    {
        $this->ensureHasNotReachedMaxEmailLimit($authUserId);

        $this->ensureHasNoPendingVerification($authUserId);

        $verificationCode = TwoFACode::generate();

        $this->pendingVerifications->put($authUserId, $secondaryEmail, $verificationCode);

        Mail::to($secondaryEmail)->queue(new TwoFACodeMail($verificationCode));
    }

    private function ensureHasNotReachedMaxEmailLimit(int $authUserId): void
    {
        $userSecondaryEmails = $this->userRepository->getUserSecondaryEmails($authUserId);

        if (count($userSecondaryEmails) === 3) {
            throw HttpException::forbidden(['message' => 'MaxEmailsLimitReached']);
        }
    }

    /**
     * Secondary emails are verified one at a time since there is a limit on how many secondary emails a user can have
     * therefore it makes no sense to allow a user add any amount of emails (and send out useless emails)
     * only to return a "max email reached"
     * response when the user is trying to verify an email with a verification code.
     */
    private function ensureHasNoPendingVerification(int $authUserId): void
    {
        if ($this->pendingVerifications->has($authUserId)) {
            throw new HttpException(['message' => 'AwaitingVerification'], Response::HTTP_BAD_REQUEST);
        }
    }
}
