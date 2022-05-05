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
    public function toArray($request)
    {
        return [
            'type' => 'user',
            'attributes'      => [
                'id'         => $this->user->id->toInt(),
                'firstname'  => $this->user->firstname->value,
                'lastname'   => $this->user->lastname->value,
                'username'   => $this->user->username->value,
                'bookmarks_count' => $this->user->bookmarksCount->value,
                'favourites_count' => $this->user->favouritesCount->value
            ]
        ];
    }
}
