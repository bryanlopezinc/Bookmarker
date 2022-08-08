<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\User;
use App\Exceptions\InvalidUsernameException;
use App\QueryColumns\UserAttributes;
use App\Repositories\UserRepository;
use App\Cache\VerificationCodesRepository;
use App\Http\Requests\RequestVerificationCodeRequest as Request;
use App\Jobs\SendVerificationCodeJob;
use App\Contracts\VerificationCodeGeneratorInterface;
use App\ValueObjects\{Email, Username};
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;

final class RequestVerificationCodeService
{
    public function __construct(
        private readonly VerificationCodesRepository $tokens,
        private readonly UserRepository $userRepository,
        private readonly VerificationCodeGeneratorInterface $codeGenerator
    ) {
    }

    public function __invoke(Request $request): void
    {
        $maxRequestsPerMinute = 1;

        $this->ensureValidCrendentials($user = $this->getUser($request), $request);

        $verificationCodeSent = RateLimiter::attempt($this->key($user), $maxRequestsPerMinute, function () use ($user) {
            $this->tokens->put(
                $user->id,
                $verificationCode = $this->codeGenerator->generate(),
                now()->addMinutes(setting('VERIFICATION_CODE_EXPIRE'))
            );

            SendVerificationCodeJob::dispatch($user->email, $verificationCode);
        });

        if (!$verificationCodeSent) {
            throw new  ThrottleRequestsException('Too Many Requests');
        }
    }

    private function key(User $user): string
    {
        return 'sent2fa::' . $user->id->toInt();
    }

    private function getUser(Request $request): User|false
    {
        $attributes = UserAttributes::only('id,password,email');

        try {
            return $this->userRepository->findByUsername(Username::fromRequest($request), $attributes);
        } catch (InvalidUsernameException) {
            return $this->userRepository->findByEmail(new Email($request->validated('username')), $attributes);
        }
    }

    private function ensureValidCrendentials(User|false $user, Request $request): void
    {
        $exception = new HttpResponseException(response()->json([
            'message' => 'Invalid Credentials'
        ], Response::HTTP_UNAUTHORIZED));

        if (!$user) {
            throw $exception;
        }

        if (!app(Hasher::class)->check($request->input('password'), $user->password)) {
            throw $exception;
        }
    }
}
