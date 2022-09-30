<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use App\ValueObjects\TwoFACode;

final class TwoFACodeMail extends Mailable
{
    public function __construct(private TwoFACode $twoFACode)
    {
    }

    public function get2FACode(): TwoFACode
    {
        return $this->twoFACode;
    }

    public function build(): self
    {
        return $this->view('emails.verificationCode', [
            'code' => $this->twoFACode->code()
        ]);
    }
}
