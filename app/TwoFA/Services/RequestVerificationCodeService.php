<?php

declare(strict_types=1);

namespace App\TwoFA\Services;

use App\DataTransferObjects\User;
use App\Exceptions\InvalidUsernameException;
use App\QueryColumns\UserQueryColumns;
use App\Repositories\UserRepository;
use App\TwoFA\Cache\RecentlySentVerificationCodesRepository;
use App\TwoFA\Cache\VerificationCodesRepository;
use App\TwoFA\Requests\RequestVerificationCodeRequest as Request;
use App\TwoFA\{SendVerificationCodeJob, TwoFactorData};
use App\TwoFA\VerificationCodeGeneratorInterface;
use App\ValueObjects\{Email, Username};
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use Illuminate\Support\InteractsWithTime;

final class RequestVerificationCodeService
{
    use InteractsWithTime;

    public function __construct(
        private readonly VerificationCodesRepository $tokens,
        private readonly RecentlySentVerificationCodesRepository $recentTokens,
        private readonly UserRepository $userRepository,
        private readonly VerificationCodeGeneratorInterface $codeGenerator
    ) {
    }

    public function __invoke(Request $request): void
    {
        $this->ensureValidCrendentials($user = $this->getUser($request), $request);

        $this->ensureCanRequestNewVerificationCode($user);

        $twoFactorData = new TwoFactorData($user->id, $this->codeGenerator->generate(), $retryAfter = now()->addSeconds(59));

        $this->tokens->put($twoFactorData, now()->addMinutes(10));

        $this->recentTokens->put($user->id, $retryAfter);

        SendVerificationCodeJob::dispatch($user->email, $twoFactorData->verificationCode);
    }

    private function getUser(Request $request): User|false
    {
        $attributes = UserQueryColumns::new()->id()->password()->email();

        try {
            return $this->userRepository->findByUsername(Username::fromRequest($request), $attributes);
        } catch (InvalidUsernameException) {
            return $this->userRepository->findByEmail(new Email($request->validated('username')), $attributes);
        }
    }

    private function ensureCanRequestNewVerificationCode(User $user): void
    {
        if ($this->recentTokens->has($user->id)) {
            throw new HttpResponseException(response()->json([
                'message' => sprintf('retry after  %s seconds', $this->secondsUntil($this->tokens->get($user->id)->retryAfter))
            ], Response::HTTP_TOO_MANY_REQUESTS,));
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
