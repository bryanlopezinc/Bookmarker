<?php

declare(strict_types=1);

namespace App\Http\Handlers\SendInvite;

use App\Contracts\FolderRequestHandlerInterface;
use App\DataTransferObjects\SendInviteRequestData;
use App\Models\Folder;
use Illuminate\Cache\RateLimiter;

final class HitRateLimit implements FolderRequestHandlerInterface
{
    private readonly RateLimiter $rateLimiter;
    private readonly SendInviteRequestData $data;

    public function __construct(SendInviteRequestData $data, RateLimiter $rateLimiter = null)
    {
        $this->rateLimiter = $rateLimiter ??= app(RateLimiter::class);
        $this->data = $data;
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        $key = "invites:{$this->data->authUser->id}:{$this->data->inviteeEmail}";

        $this->rateLimiter->hit(key: $key, decaySeconds: 60);
    }
}
