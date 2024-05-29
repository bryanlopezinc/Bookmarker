<?php

declare(strict_types=1);

namespace App\Http\Handlers\Blacklisting;

use App\Models\Folder;
use App\Enums\ActivityType;
use App\Models\FolderActivity;
use App\DataTransferObjects\Activities\DomainBlacklistedActivityLogData as ActivityLogData;
use App\Models\User;
use App\ValueObjects\Url;
use Illuminate\Database\Eloquent\{Builder, Model, Scope};

final class LogActivity implements Scope
{
    public function __construct(private readonly Url $url, private readonly User $authUser)
    {
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
        if ($folder->settings->logDomainBlacklistedActivity()->isDisabled()) {
            return;
        }

        $activityData = new ActivityLogData($this->authUser, $this->url);

        $attributes = [
            'folder_id' => $folder->id,
            'type'      => ActivityType::DOMAIN_BLACKLISTED,
            'data'      => $activityData->toArray(),
        ];

        dispatch(static function () use ($attributes) {
            FolderActivity::query()->create($attributes);
        })->afterResponse();
    }
}
