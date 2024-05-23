<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\MalformedURLException;
use App\Exceptions\UserNotFoundException;
use App\Mail\EmailNotRegisteredMail;
use App\Mail\EmailNotVerifiedMail;
use App\Models\SecondaryEmail;
use App\Models\User;
use App\Utils\MailParser;
use App\ValueObjects\Url;
use Illuminate\Support\Facades\Mail;

final class CreateBookmarkFromMailMessageService
{
    public function __construct(private CreateBookmarkService $service)
    {
    }

    public function create(string $mimeMessage): void
    {
        $message = new MailParser($mimeMessage);

        $email = $message->from();

        if (is_null($email)) {
            return;
        }

        /** @var User */
        $user = User::query()
            ->select(['id', 'email_verified_at'])
            ->where('users.email', $email)
            ->orWhere('id', SecondaryEmail::select('user_id')->where('email', $email))
            ->firstOrNew();

        if ( ! $user->exists) {
            Mail::to($email)->queue(new EmailNotRegisteredMail());

            throw new UserNotFoundException();
        }

        if ( ! $user->email_verified_at) {
            Mail::to($email)->queue(new EmailNotVerifiedMail());

            return;
        }

        try {
            $this->service->fromMail(new Url(trim((string)$message->getTextContent())), $user->id);
        } catch (MalformedURLException) {
        }
    }
}
