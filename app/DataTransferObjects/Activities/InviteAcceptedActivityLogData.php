<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Activities;

use App\Models\User;
use Illuminate\Contracts\Support\Arrayable;

final class InviteAcceptedActivityLogData implements Arrayable
{
    public function __construct(public readonly User $inviter, public readonly User $invitee)
    {
    }

    public static function fromArray(array $data): self
    {
        $inviter = new User($data['inviter']);
        $invitee = new User($data['invitee']);

        $invitee->exists = true;
        $inviter->exists = true;

        return new InviteAcceptedActivityLogData($inviter, $invitee);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return [
            'version' => '1.0.0',
            'inviter' => $this->inviter->activityLogContextVariables(),
            'invitee' => $this->invitee->activityLogContextVariables()
        ];
    }
}
