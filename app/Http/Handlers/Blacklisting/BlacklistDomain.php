<?php

declare(strict_types=1);

namespace App\Http\Handlers\Blacklisting;

use App\Actions\BlacklistDomain as BlacklistDomainAction;
use App\Models\Folder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use App\Models\User;
use App\ValueObjects\Url;

final class BlacklistDomain implements Scope
{
    private readonly Url $url;
    private readonly User $authUser;
    private readonly BlacklistDomainAction $action;

    public function __construct(Url $url, User $authUser, BlacklistDomainAction $action = null)
    {
        $this->url = $url;
        $this->authUser = $authUser;
        $this->action = $action ??= new BlacklistDomainAction();
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->addSelect(['updated_at']);
    }

    public function __invoke(Folder $folder): void
    {
        $this->action->create($folder, $this->authUser, $this->url);

        $folder->touch('updated_at');
    }
}
