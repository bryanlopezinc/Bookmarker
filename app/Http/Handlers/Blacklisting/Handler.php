<?php

declare(strict_types=1);

namespace App\Http\Handlers\Blacklisting;

use App\Models\Folder;
use App\Http\Handlers\Constraints;
use App\Enums\CollaboratorMetricType;
use App\Enums\Feature;
use App\Enums\Permission;
use App\Http\Handlers\CollaboratorMetricsRecorder;
use App\Http\Handlers\ConditionallyLogActivity;
use App\Http\Handlers\RequestHandlersQueue;
use App\Models\Scopes\WherePublicIdScope;
use App\Models\User;
use App\ValueObjects\PublicId\FolderPublicId;
use App\ValueObjects\Url;

final class Handler
{
    public function handle(FolderPublicId $folderId, User $authUser, Url $url): void
    {
        $requestHandlersQueue = new RequestHandlersQueue($this->getConfiguredHandlers($authUser, $url));

        $query = Folder::select(['id'])->tap(new WherePublicIdScope($folderId));

        $requestHandlersQueue->scope($query);

        $requestHandlersQueue->handle($query->firstOrNew());
    }

    private function getConfiguredHandlers(User $authUser, Url $url): array
    {
        return [
            new Constraints\FolderExistConstraint(),
            new Constraints\MustBeACollaboratorConstraint($authUser),
            new Constraints\PermissionConstraint($authUser, Permission::BLACKLIST_DOMAIN),
            new Constraints\FeatureMustBeEnabledConstraint($authUser, Feature::BLACKLIST_DOMAIN),
            new UniqueConstraint($url),
            new BlacklistDomain($url, $authUser),
            new ConditionallyLogActivity(new LogActivity($url, $authUser)),
            new CollaboratorMetricsRecorder(CollaboratorMetricType::DOMAINS_BLACKLISTED, $authUser->id),
        ];
    }
}
