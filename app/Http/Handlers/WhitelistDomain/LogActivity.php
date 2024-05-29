<?php

declare(strict_types=1);

namespace App\Http\Handlers\WhitelistDomain;

use App\Models\Folder;
use App\Enums\ActivityType;
use App\Models\FolderActivity;
use App\DataTransferObjects\Activities\DomainWhiteListedActivity as ActivityLogData;
use App\Models\User;
use Illuminate\Database\Eloquent\{Builder, Model, Scope};

final class LogActivity implements Scope
{
    public function __construct(
        private readonly User $authUser,
        private readonly BlacklistedDomainRepository $repository
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->addSelect(['settings']);
    }

    public function __invoke(Folder $folder): void
    {
        if ($folder->settings->logDomainWhitelistedActivity()->isDisabled()) {
            return;
        }

        $activityData = new ActivityLogData($this->authUser, $this->repository->getRecord()->resolved_domain);

        $attributes = [
            'folder_id' => $folder->id,
            'type'      => ActivityType::DOMAIN_WHITELISTED,
            'data'      => $activityData->toArray(),
        ];

        dispatch(static function () use ($attributes) {
            FolderActivity::query()->create($attributes);
        })->afterResponse();
    }
}
