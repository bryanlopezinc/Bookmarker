<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\MalformedURLException;
use App\Exceptions\UserNotFoundException;
use App\Mail\EmailNotRegisteredMail;
use App\Mail\EmailNotVerifiedMail;
use App\Utils\MailParser;
use App\Repositories\UserRepository;
use App\ValueObjects\Url;
use Illuminate\Support\Facades\Mail;

final class CreateBookmarkFromMailMessageService
{
    public function __construct(private UserRepository $userRepository, private CreateBookmarkService $service)
    {
    }

    public function create(string $mimeMessage): void
    {
        $message = new MailParser($mimeMessage);

        $email = $message->from();

        if (is_null($email)) {
            return;
        }

        try {
            $user = $this->userRepository->findByEmailOrSecondaryEmail(
                $email,
                ['id', 'email_verified_at']
            );
        } catch (UserNotFoundException $e) {
            Mail::to($email)->queue(new EmailNotRegisteredMail());

            throw $e;
        }

        if (!$user->email_verified_at) {
            Mail::to($email)->queue(new EmailNotVerifiedMail());
            return;
        }

        try {
            $this->service->fromMail(new Url(trim((string)$message->getTextContent())), $user->id);
        } catch (MalformedURLException) {
        }
    }
}
