<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Filesystem\ProfileImageFileSystem;
use App\Models\User;
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
                'id'                 => $this->user->id,
                'name'               => $this->user->full_name,
                'username'           => $this->user->username,
                'bookmarks_count'    => $this->user->bookmarks_count,
                'favorites_count'    => $this->user->favorites_count,
                'folders_count'      => $this->user->folders_count,
                'has_verified_email' => $this->user->email_verified_at !== null,
                'profile_image_url'  => (new ProfileImageFileSystem())->publicUrl($this->user->profile_image_path)
            ]
        ];
    }
}
