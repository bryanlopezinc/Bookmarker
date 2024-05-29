<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\User;

final class UserResource extends JsonResource
{
    public function __construct(
        private readonly User $potentiallyOutdatedUserRecord,
        private readonly User $currentUserRecord
    ) {
        parent::__construct($currentUserRecord);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        return [
            'exists' => $this->currentUserRecord->exists,
            'id'     => $this->potentiallyOutdatedUserRecord->public_id->present(),
        ];
    }
}
