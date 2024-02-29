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

    public function __construct(SendInviteRequestData $data, RateLimiter $rateLimiter)
    {
        $this->rateLimiter = $rateLimiter;
        $this->data = $data;
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        $maxInvitesThatCanBeSentPerMinute = 1;
        $decaySeconds = 60;

        $key = "invites:{$this->data->authUser->id}:{$this->data->inviteeEmail}";

        if ($this->rateLimiter->tooManyAttempts($key, $maxInvitesThatCanBeSentPerMinute)) {
            $availableIn = $this->rateLimiter->availableIn($key);

            throw new ThrottleRequestsException(
                message: 'TooManySentInvites',
                headers: ['resend-invite-after' => $availableIn]
            );
        }

        $this->rateLimiter->hit($key, $decaySeconds);
    }
}
