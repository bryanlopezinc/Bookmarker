<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\IdGeneratorInterface;
use App\Models\BlacklistedDomain;
use App\Models\Folder;
use App\Models\User;
use App\ValueObjects\Url;

final class BlacklistDomain
{
    private readonly IdGeneratorInterface $idGenerator;

    public function __construct(IdGeneratorInterface $idGenerator = null)
    {
        $this->idGenerator = $idGenerator ??= app(IdGeneratorInterface::class);
    }

    public function create(Folder $folder, User $user, Url $url): BlacklistedDomain
    {
        $registerableDomain = $url->getDomain();

        return BlacklistedDomain::query()->create([
            'public_id'       => $this->idGenerator->generate(),
            'folder_id'       => $folder->id,
            'given_url'       => $url->toString(),
            'resolved_domain' => $registerableDomain->getRegisterable(),
            'domain_hash'     => $registerableDomain->getRegisterableHash(),
            'created_by'      => $user->id
        ]);
    }
}
