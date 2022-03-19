<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DataTransferObjects\User;
use Illuminate\Http\Resources\Json\JsonResource;

final class CreatedUserResource extends JsonResource
{
    public function __construct(private User $user, private string $tokenResponse)
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

    /**
     * {@inheritdoc}
     */
    public function withResponse($request, $response)
    {
        $response->setStatusCode($response::HTTP_CREATED);
    }
}
