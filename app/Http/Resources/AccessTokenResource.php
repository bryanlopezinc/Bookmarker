<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

final class AccessTokenResource extends JsonResource
{
    public function __construct(private readonly User $user, private readonly string $tokenResponse)
    {
        parent::__construct($user);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        $userResource = (new UserResource($this->user))->toArray($request);

        $userResource['token'] = json_decode($this->tokenResponse, true);

        return $userResource;
    }
}
