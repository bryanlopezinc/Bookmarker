<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DataTransferObjects\User;
use Illuminate\Http\Resources\Json\JsonResource;

final class UserResource extends JsonResource
{
    public function __construct(private User $user)
    {
        parent::__construct($user);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request): array
    {
        return [
            'type' => 'user',
            'attributes'      => [
                'firstname'  => $this->user->firstName->value,
                'lastname'   => $this->user->lastName->value,
                'username'   => $this->user->username->value,
                'bookmarks_count' => $this->user->bookmarksCount->value,
                'favorites_count' => $this->user->favoritesCount->value,
                'folders_count' => $this->user->foldersCount->value,
                'has_verified_email' => $this->user->hasVerifiedEmail
            ]
        ];
    }
}
