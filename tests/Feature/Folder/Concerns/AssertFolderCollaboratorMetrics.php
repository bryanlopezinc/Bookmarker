<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\Concerns;

use App\Enums\CollaboratorMetricType;
use App\Models\FolderCollaboratorMetric;
use App\Models\FolderCollaboratorMetricSummary;
use PHPUnit\Framework\Assert as PHPUnit;

trait AssertFolderCollaboratorMetrics
{
    protected function assertNoMetricsRecorded(int $collaboratorId, int $folderId, CollaboratorMetricType $type): void
    {
        //Order of keys matters to ensure composite index is used.
        $values = [
            'collaborator_id' => $collaboratorId,
            'folder_id'       => $folderId,
            'metrics_type'    => $type->value,
        ];

        $folderCollaboratorMetrics = FolderCollaboratorMetric::query()->where($values)->get();
        $folderCollaboratorMetricsSummary = FolderCollaboratorMetricSummary::query()->where($values)->firstOrNew();

        PHPUnit::assertEmpty($folderCollaboratorMetrics);
        PHPUnit::assertFalse($folderCollaboratorMetricsSummary->exists);
    }

    protected function assertFolderCollaboratorMetric(int $collaboratorId, int $folderId, CollaboratorMetricType $type, int $count = 1): void
    {
        //Order of keys matters to ensure composite index is used.
        $values = [
            'collaborator_id' => $collaboratorId,
            'folder_id'       => $folderId,
            'metrics_type'    => $type->value,
        ];

        $folderCollaboratorMetric = FolderCollaboratorMetric::query()->where($values)->firstOrNew();

        PHPUnit::assertTrue($folderCollaboratorMetric->exists);
        PHPUnit::assertEquals($count, $folderCollaboratorMetric->count);
    }

    protected function assertFolderCollaboratorMetricsSummary(int $collaboratorId, int $folderId, CollaboratorMetricType $type, int $count = 1): void
    {
        //Order of keys matters to ensure composite index is used.
        $values = [
            'collaborator_id' => $collaboratorId,
            'folder_id'       => $folderId,
            'metrics_type'    => $type->value,
        ];

        $folderCollaboratorMetricsSummary = FolderCollaboratorMetricSummary::query()->where($values)->firstOrNew();

        PHPUnit::assertTrue($folderCollaboratorMetricsSummary->exists);
        PHPUnit::assertEquals($count, $folderCollaboratorMetricsSummary->count);
    }
}
