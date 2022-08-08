<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\MalformedURLException;
use App\Mail\EmailNotRegisteredMail;
use App\Utils\MailParser;
use App\QueryColumns\UserAttributes;
use App\Repositories\UserRepository;
use App\ValueObjects\Email;
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

        $user = $this->userRepository->findByEmail($email = new Email($message->from()), UserAttributes::only('id'));

        if ($user === false) {
            Mail::to($email->value)->send(new EmailNotRegisteredMail);
            return;
        }

        try {
            $this->service->fromArray([
                'url' => new Url(trim((string)$message->getTextContent())),
                'createdOn' => (string) now(),
                'userID' => $user->id
            ]);
        } catch (MalformedURLException) {
        }
    }
}
