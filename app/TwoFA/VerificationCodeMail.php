<?php

declare(strict_types=1);

namespace App\TwoFA;

use Illuminate\Mail\Mailable;

final class VerificationCodeMail extends Mailable
{
    public function __construct(private VerificationCode $verificationCode)
    {
    }

    public function getVerificationCode(): VerificationCode
    {
        return $this->verificationCode;
    }

    public function build(): self
    {
        return $this->view('emails.verificationCode', [
            'code' => $this->verificationCode->code()
        ]);
    }
}
