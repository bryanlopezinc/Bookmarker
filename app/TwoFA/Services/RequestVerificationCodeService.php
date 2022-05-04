<?php

declare(strict_types=1);

namespace App\TwoFA\Services;

use App\DataTransferObjects\User;
use App\QueryColumns\UserQueryColumns;
use App\Repositories\UserRepository;
use App\TwoFA\Cache\RecentlySentVerificationCodesRepository;
use App\TwoFA\Cache\VerificationCodesRepository;
use App\TwoFA\Requests\RequestVerificationCodeRequest as Request;
use App\TwoFA\SendVerificationCodeJob;
use App\TwoFA\TwoFactorData;
use App\TwoFA\VerificationCodeGeneratorInterface;
use App\ValueObjects\Username;
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
        $user = $this->userRepository->findByUsername(Username::fromRequest($request), UserQueryColumns::new()->id()->password()->email());

        $this->ensureValidCrendentials($user, $request);

        $this->ensureCanRequestNewVerificationCode($user);

        $twoFactorData = new TwoFactorData($user->id, $this->codeGenerator->generate(), $retryAfter = now()->addSeconds(59));

        $this->tokens->put($twoFactorData, now()->addMinutes(10));

        $this->recentTokens->put($user->id, $retryAfter);

        SendVerificationCodeJob::dispatch($user->email, $twoFactorData->verificationCode);
    }

    private function ensureCanRequestNewVerificationCode(User $user): void
    {
        if ($this->recentTokens->has($user->id)) {
            $this->throwException(Response::HTTP_TOO_MANY_REQUESTS, [
                'message' => sprintf('retry after  %s seconds', $this->secondsUntil($this->tokens->get($user->id)->retryAfter))
            ]);
        }
    }

    private function ensureValidCrendentials(User|false $user, Request $request): void
    {
        if (!$user) {
            $this->throwUnauthorizedException();
        }

        if (!app(Hasher::class)->check($request->input('password'), $user->password)) {
            $this->throwUnauthorizedException();
        }
    }

    private function throwUnauthorizedException(): void
    {
        $this->throwException(Response::HTTP_UNAUTHORIZED, [
            'message' => 'Invalid Credentials'
        ]);
    }

    private function throwException(int $status, array $message = []): void
    {
        throw new HttpResponseException(response()->json($message, $status));
    }
}
