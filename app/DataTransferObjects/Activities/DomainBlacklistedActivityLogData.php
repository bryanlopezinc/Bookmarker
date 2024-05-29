<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Activities;

use App\Models\User;
use App\ValueObjects\Url;
use Illuminate\Contracts\Support\Arrayable;

final class DomainBlacklistedActivityLogData implements Arrayable
{
    public function __construct(
        public readonly User $collaborator,
        public readonly Url $url,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $collaborator = new User($data['collaborator']);

        $collaborator->exists = true;

        return new self($collaborator, new Url($data['url']));
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return [
            'url'          => $this->url->toString(),
            'collaborator' => $this->collaborator->activityLogContextVariables(),
        ];
    }
}
