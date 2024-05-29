<?php

declare(strict_types=1);

namespace App\Http\Handlers\WhitelistDomain;

use App\Models\BlacklistedDomain;
use App\Models\Folder;
use App\Models\Scopes\WherePublicIdScope;
use App\ValueObjects\PublicId\BlacklistedDomainId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Scope;

final class BlacklistedDomainRepository implements Scope
{
    private readonly BlacklistedDomainId $id;
    private array $record;

    public function __construct(BlacklistedDomainId $id)
    {
        $this->id = $id;
    }

    public function apply(Builder $builder, EloquentModel $model)
    {
        $builder->withCasts(['blacklistedDomainData' => 'array']);

        $builder->addSelect([
            'blacklistedDomainData' => BlacklistedDomain::query()
                ->selectRaw("JSON_OBJECT('id', id, 'resolved_domain', resolved_domain)")
                ->whereColumn('folder_id', $model->getQualifiedKeyName())
                ->tap(new WherePublicIdScope($this->id))
        ]);
    }

    public function __invoke(Folder $result): void
    {
        $this->record = $result->blacklistedDomainData ?? [];
    }

    public function getRecord(): BlacklistedDomain
    {
        return tap(new BlacklistedDomain($this->record), function (BlacklistedDomain $model) {
            $model->exists = true;
        });
    }

    public function exists(): bool
    {
        return $this->record !== [];
    }
}
