<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\EmailVerificationEvent;
use App\Models\User as Model;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

final class SendEmailVerificationNotification implements ShouldQueue
{
    public function __construct(private Dispatcher $dispatcher)
    {
    }

    public function handle(EmailVerificationEvent $event): void
    {
        $model = new Model();

        $userAttributes = [
            $model->getKeyName() => $event->getUser()->id->value(),
            $model->getEmailName() => $event->getUser()->email->value
        ];

        $this->dispatcher->send(new Model($userAttributes), new VerifyEmailNotification());
    }
}
