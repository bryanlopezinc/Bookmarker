<?php

declare(strict_types=1);

namespace App\Http\Handlers\WhitelistDomain;

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
use App\ValueObjects\PublicId\BlacklistedDomainId;
use App\ValueObjects\PublicId\FolderPublicId;

final class Handler
{
    public function handle(FolderPublicId $folderId, User $authUser, BlacklistedDomainId $domainId): void
    {
        $requestHandlersQueue = new RequestHandlersQueue($this->getConfiguredHandlers($authUser, $domainId));

        $query = Folder::select(['id'])->tap(new WherePublicIdScope($folderId));

        $requestHandlersQueue->scope($query);

        $requestHandlersQueue->handle($query->firstOrNew());
    }

    private function getConfiguredHandlers(User $authUser, BlacklistedDomainId $domainId): array
    {
        return [
            new Constraints\FolderExistConstraint(),
            $repository = new BlacklistedDomainRepository($domainId),
            new Constraints\MustBeACollaboratorConstraint($authUser),
            new Constraints\PermissionConstraint($authUser, Permission::WHITELIST_DOMAIN),
            new Constraints\FeatureMustBeEnabledConstraint($authUser, Feature::WHITELIST_DOMAIN),
            new BlacklistRecordExistsConstraint($repository),
            new WhitelistDomain($repository),
            new ConditionallyLogActivity(new LogActivity($authUser, $repository)),
            new CollaboratorMetricsRecorder(CollaboratorMetricType::DOMAINS_WHITELISTED, $authUser->id),
        ];
    }
}
