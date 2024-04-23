<?php

declare(strict_types=1);

namespace App\Importing\Http\Resources;

use App\Importing\Models\Import;
use Illuminate\Http\Resources\Json\JsonResource;

final class ImportResource extends JsonResource
{
    public function __construct(private readonly Import $import)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        $stats = $this->import->statistics;

        return [
            'type' => 'UserImport',
            'attributes' => [
                'id'          => $this->import->public_id->present(),
                'status'      => $this->import->status->category(),
                'imported_at' => $this->import->created_at->toDateTimeString(),
                'stats'       => [
                    'imported'    => $stats->totalImported,
                    'found'       => $stats->totalFound,
                    'skipped'     => $stats->totalSkipped,
                    'failed'      => $stats->totalFailed,
                    'unProcessed' => $stats->totalUnProcessed
                ],
                'reason_for_failure' => $this->when(
                    $this->import->status->failed(),
                    fn () => $this->import->status->reason()
                )
            ]
        ];
    }
}
