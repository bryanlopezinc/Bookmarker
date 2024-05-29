<?php

declare(strict_types=1);

namespace App\Http\Handlers;

use App\Models\Folder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class ConditionallyLogActivity implements Scope, HasHandlersInterface
{
    private readonly object $logger;
    private bool $logActivity = true;

    public function __construct(object $logger)
    {
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function apply(Builder $builder, Model $model)
    {
        $builder->addSelect(['settings', 'visibility']);
    }

    /**
     * {@inheritdoc}
     */
    public function getHandlers(): array
    {
        if ($this->logActivity) {
            return [$this->logger];
        }

        return [];
    }

    public function __invoke(Folder $folder): void
    {
        $isPrivateFolder = $folder->visibility->isPrivate() || $folder->visibility->isPasswordProtected();

        if ($folder->settings->logActivities()->isDisabled() || $isPrivateFolder) {
            $this->logActivity = false;
        }
    }
}
