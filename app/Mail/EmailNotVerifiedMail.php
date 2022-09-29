<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;

final class EmailNotVerifiedMail extends Mailable
{
    public function build(): self
    {
        return $this->view('emails.emailnotverified');
    }
}
