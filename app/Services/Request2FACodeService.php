<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\User;
use App\Exceptions\InvalidUsernameException;
use App\QueryColumns\UserAttributes;
use App\Repositories\UserRepository;
use App\Cache\User2FACodeRepository;
use App\Http\Requests\Request2FACodeRequest as Request;
use App\Contracts\TwoFACodeGeneratorInterface;
use App\Mail\TwoFACodeMail;
use App\ValueObjects\{Email, Username};
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
        private readonly TwoFACodeGeneratorInterface $twoFACodeGenerator
    ) {
    }

    public function __invoke(Request $request): void
    {
        $maxRequestsPerMinute = 1;

        $user = $this->getUser($request);

        $twoFACodeSent = RateLimiter::attempt($this->key($user), $maxRequestsPerMinute, function () use ($user) {
            $this->user2FACodeRepository->put(
                $user->id,
                $twoFACode = $this->twoFACodeGenerator->generate(),
                now()->addMinutes(setting('VERIFICATION_CODE_EXPIRE'))
            );

            Mail::to($user->email->value)->queue(new TwoFACodeMail($twoFACode));
        });

        if (!$twoFACodeSent) {
            throw new  ThrottleRequestsException('Too Many Requests');
        }
    }

    private function key(User $user): string
    {
        return 'sent2fa::' . $user->id->toInt();
    }

    private function getUser(Request $request): User
    {
        $attributes = UserAttributes::only('id,password,email');

        try {
            $user = $this->userRepository->findByUsername(Username::fromRequest($request), $attributes);
        } catch (InvalidUsernameException) {
            $user = $this->userRepository->findByEmail(new Email($request->validated('username')), $attributes);
        }

        if (!$user) {
            throw $this->InvalidCredentialsException();
        }

        if (!app(Hasher::class)->check($request->input('password'), $user->password)) {
            throw $this->InvalidCredentialsException();
        }

        return $user;
    }

    private function InvalidCredentialsException(): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'message' => 'Invalid Credentials'
        ], Response::HTTP_UNAUTHORIZED));
    }
}
