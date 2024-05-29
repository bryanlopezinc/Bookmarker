<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Activities;

use App\Models\User;
use Illuminate\Contracts\Support\Arrayable;

final class DescriptionChangedActivityLogData implements Arrayable
{
    public function __construct(
        public readonly User $collaborator,
        public readonly ?string $oldDescription,
        public readonly ?string $newDescription
    ) {
    }

    public static function fromArray(array $data): self
    {
        $collaborator = new User($data['collaborator']);

        $collaborator->exists = true;

        return new DescriptionChangedActivityLogData(
            $collaborator,
            $data['old_description'],
            $data['new_description']
        );
    }

    public function descriptionHasOldValue(): bool
    {
        return $this->oldDescription !== null;
    }

    public function descriptionHasNewValue(): bool
    {
        return $this->newDescription !== null;
    }

    public function descriptionWasRemoved(): bool
    {
        return $this->descriptionHasOldValue() && ! $this->descriptionHasNewValue();
    }

    public function descriptionWasChangedFromBlankToFilled(): bool
    {
        return ! $this->descriptionHasOldValue() && $this->descriptionHasNewValue();
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return [
            'version'         => '1.0.0',
            'new_description' => $this->newDescription,
            'old_description' => $this->oldDescription,
            'collaborator'    => $this->collaborator->activityLogContextVariables()
        ];
    }
}
