<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Exceptions\InvalidUsernameException;
use App\Repositories\UserRepository;
use App\Repositories\User2FACodeRepository;
use App\Exceptions\UserNotFoundException;
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
        private readonly UserRepository $userRepository,
        private Hasher $hasher
    ) {
    }

    public function __invoke(Request $request): void
    {
        $user = $this->getUser($request);

        $rateLimiterKey = "2FARequests:{$user->id}";

        if ( ! $this->hasher->check($request->input('password'), $user->password)) {
            throw $this->invalidCredentialsException();
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
        $attributes = ['id', 'password', 'email'];

        try {
            return $this->userRepository->findByUsername((new Username($request->input('username')))->value, $attributes);
        } catch (InvalidUsernameException) {
            return $this->userRepository->findByEmail($request->validated('username'), $attributes);
        } catch (UserNotFoundException) {
            throw $this->invalidCredentialsException();
        }
    }

    private function invalidCredentialsException(): HttpResponseException
    {
        return new HttpResponseException(
            response()->json(
                ['message' => 'InvalidCredentials'],
                Response::HTTP_UNAUTHORIZED
            )
        );
    }
}
