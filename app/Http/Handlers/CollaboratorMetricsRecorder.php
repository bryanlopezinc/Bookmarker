<?php

declare(strict_types=1);

namespace App\Http\Handlers;

use App\Contracts\FolderRequestHandlerInterface;
use App\Enums\CollaboratorMetricType as Type;
use App\Models\Folder;
use App\Models\FolderCollaboratorMetric;
use App\Models\FolderCollaboratorMetricSummary;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Arr;

final class CollaboratorMetricsRecorder implements FolderRequestHandlerInterface, Scope
{
    private readonly Type $type;
    private readonly int $collaboratorId;
    private readonly int $count;
    private readonly Application $app;

    public function __construct(Type $type, int $collaboratorId, int $count = 1, Application $app = null)
    {
        $this->type = $type;
        $this->collaboratorId = $collaboratorId;
        $this->count = $count;
        $this->app = $app ??= app();
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->addSelect(['user_id']);
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        $incrementByAmount = $this->count;

        $folderBelongsToCollaborator = $this->collaboratorId === $folder->user_id;

        $values = [
            'collaborator_id' => $this->collaboratorId,
            'folder_id'       => $folder->id,
            'metrics_type'    => $this->type->value,
            'count'           => $incrementByAmount
        ];

        if ($folderBelongsToCollaborator) {
            return;
        }

        $pendingDispatch = dispatch(static function () use ($values, $incrementByAmount) {
            FolderCollaboratorMetric::query()->create($values);

            FolderCollaboratorMetricSummary::query()->upsert(
                values: $values,
                uniqueBy: Arr::except($values, 'count'),
                update: ['count' => new Expression("count + {$incrementByAmount}")]
            );
        });

        if ( ! $this->app->runningUnitTests()) {
            $pendingDispatch->afterResponse();
        }
    }
}
