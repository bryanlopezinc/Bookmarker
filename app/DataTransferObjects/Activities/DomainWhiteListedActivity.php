<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Activities;

use App\Models\User;
use Illuminate\Contracts\Support\Arrayable;

final class DomainWhiteListedActivity implements Arrayable
{
    public function __construct(
        public readonly User $collaborator,
        public readonly string $domain,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $collaborator = new User($data['collaborator']);

        $collaborator->exists = true;

        return new DomainWhiteListedActivity($collaborator, $data['domain']);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return [
            'domain'       => $this->domain,
            'collaborator' => $this->collaborator->activityLogContextVariables(),
        ];
    }
}
