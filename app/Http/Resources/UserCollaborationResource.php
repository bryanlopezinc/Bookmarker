<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DataTransferObjects\UserCollaboration;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

final class UserCollaborationResource extends JsonResource
{
    public function __construct(private UserCollaboration $userCollaboration)
    {
        parent::__construct($userCollaboration);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        $folderResource = new FolderResource($this->userCollaboration->collaboration);
        $response = $folderResource->toArray($request);

        Arr::set($response, 'type', 'userCollaboration');
        Arr::set($response, 'attributes.permissions', $this->userCollaboration->permissions->toExternalIdentifiers());

        return $response;
    }
}
