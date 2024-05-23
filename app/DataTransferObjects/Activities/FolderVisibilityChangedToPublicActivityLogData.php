<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Activities;

use App\Models\User;
use Illuminate\Contracts\Support\Arrayable;

final class FolderVisibilityChangedToPublicActivityLogData implements Arrayable
{
    public function __construct(public readonly User $collaborator)
    {
    }

    public static function fromArray(array $data): self
    {
        $collaborator = new User($data['collaborator']);

        $collaborator->exists = true;

        return new FolderVisibilityChangedToPublicActivityLogData($collaborator);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return [
            'version'      => '1.0.0',
            'collaborator' => $this->collaborator->activityLogContextVariables()
        ];
    }
}
