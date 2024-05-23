<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Activities;

use App\Models\User;
use Illuminate\Contracts\Support\Arrayable;

final class FolderNameChangedActivityLogData implements Arrayable
{
    public function __construct(
        public readonly User $collaborator,
        public readonly string $oldName,
        public readonly string $newName
    ) {
    }

    public static function fromArray(array $data): self
    {
        $collaborator = new User($data['collaborator']);

        $collaborator->exists = true;

        return new FolderNameChangedActivityLogData(
            $collaborator,
            $data['from'],
            $data['to']
        );
    }

    /**
     * @inheritdoc
     */
    public function toArray(): array
    {
        return [
            'version'      => '1.0.0',
            'from'         => $this->oldName,
            'to'           => $this->newName,
            'collaborator' => $this->collaborator->activityLogContextVariables()
        ];
    }
}
