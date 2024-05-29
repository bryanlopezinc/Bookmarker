<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Exceptions\InvalidUsernameException;
use App\Repositories\User2FACodeRepository;
use App\Http\Requests\Request2FACodeRequest as Request;
use App\Mail\TwoFACodeMail;
use App\ValueObjects\{TwoFACode, Username};
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

final class Request2FACodeService
{
    public function __construct(
        private readonly User2FACodeRepository $user2FACodeRepository,
        private readonly Hasher $hasher
    ) {
    }

    public function __invoke(Request $request): void
    {
        $user = $this->getUser($request);

        if ( ! $user->exists) {
            $this->throwInvalidCredentialsException();
        }

        $rateLimiterKey = "2FARequests:{$user->id}";

        if ( ! $this->hasher->check($request->input('password'), $user->password)) {
            $this->throwInvalidCredentialsException();
        }

        $twoFACodeSent = RateLimiter::attempt(
            key: $rateLimiterKey,
            maxAttempts: 1,
            callback: function () use ($user) {
                $this->user2FACodeRepository->put($user->id, $twoFACode = TwoFACode::generate());

                Mail::to($user->email)->queue(new TwoFACodeMail($twoFACode));
            }
        );

        if ( ! $twoFACodeSent) {
            throw new ThrottleRequestsException(
                message: 'TooMany2FACodeRequests',
                headers: ['request-2FA-after' => RateLimiter::availableIn($rateLimiterKey)]
            );
        }
    }

    private function getUser(Request $request): User
    {
        $query = User::query()->select(['id', 'password', 'email']);

        try {
            $username = new Username($request->input('username'));

            return $query->where('username', $username->value)->firstOrNew();
        } catch (InvalidUsernameException) {
            return $query->where('email', $request->validated('username'))->firstOrNew();
        }
    }

    private function throwInvalidCredentialsException(): void
    {
        throw new HttpResponseException(
            response()->json(
                ['message' => 'InvalidCredentials'],
                Response::HTTP_UNAUTHORIZED
            )
        );
    }
}
