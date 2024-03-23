<?php

declare(strict_types=1);

namespace App\Http\Handlers\SendInvite;

use App\Contracts\FolderRequestHandlerInterface;
use App\DataTransferObjects\SendInviteRequestData;
use App\Models\Folder;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Exceptions\ThrottleRequestsException;

final class RateLimitConstraint implements FolderRequestHandlerInterface
{
    private readonly RateLimiter $rateLimiter;
    private readonly SendInviteRequestData $data;

    public function __construct(SendInviteRequestData $data, RateLimiter $rateLimiter = null)
    {
        $this->rateLimiter = $rateLimiter ??= app(RateLimiter::class);
        $this->data = $data;

        $this->checkForTooManyAttempts();
    }

    private function checkForTooManyAttempts(): void
    {
        $maxInvitesThatCanBeSentPerMinute = 1;

        $key = "invites:{$this->data->authUser->id}:{$this->data->inviteeEmail}";

        if ($this->rateLimiter->tooManyAttempts($key, $maxInvitesThatCanBeSentPerMinute)) {
            throw new ThrottleRequestsException(
                message: 'TooManySentInvites',
                headers: ['resend-invite-after' => $this->rateLimiter->availableIn($key)]
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
    }
}
