<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\EmailVerificationCodeRepository as PendingVerifications;
use App\Exceptions\HttpException;
use App\Mail\TwoFACodeMail;
use App\Models\User;
use App\ValueObjects\TwoFACode;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;

final class AddEmailToAccountService
{
    public function __construct(private PendingVerifications $pendingVerifications)
    {
    }

    public function __invoke(User $authUser, string $secondaryEmail): void
    {
        $this->ensureHasNotReachedMaxEmailLimit($authUser);

        $this->ensureHasNoPendingVerification($authUser->id);

        $verificationCode = TwoFACode::generate();

        $this->pendingVerifications->put($authUser->id, $secondaryEmail, $verificationCode);

        Mail::to($secondaryEmail)->queue(new TwoFACodeMail($verificationCode));
    }

    private function ensureHasNotReachedMaxEmailLimit(User $authUser): void
    {
        $authUser->loadCount(['secondaryEmails']);

        if ($authUser->secondary_emails_count === 3) {
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
