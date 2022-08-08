<?php

namespace App\Jobs;

use App\Mail\VerificationCodeMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\ValueObjects\Email;
use App\ValueObjects\VerificationCode;
use Illuminate\Support\Facades\Mail;

final class SendVerificationCodeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private  Email $userEmail, private VerificationCode $code)
    {
    }

    public function handle(): void
    {
        Mail::to($this->userEmail->value)->send(new VerificationCodeMail($this->code));
    }
}
