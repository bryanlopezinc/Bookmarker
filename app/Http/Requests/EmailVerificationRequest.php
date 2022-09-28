<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Auth\EmailVerificationRequest as Request;

final class EmailVerificationRequest extends Request
{
    /**
     * {@inheritdoc}
     */
    public function authorize()
    {
        auth()->onceUsingId($this->route('id'));

        return parent::authorize();
    }
}
