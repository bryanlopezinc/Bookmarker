<?php

declare(strict_types=1);

namespace App\Importing\Http\Resources;

use App\Importing\Models\ImportHistory;
use Illuminate\Http\Resources\Json\JsonResource;

final class ImportHistoryResource extends JsonResource
{
    public function __construct(private readonly ImportHistory $importHistory)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        $status = $this->importHistory->status;
        $tags = $this->importHistory->tags;

        return [
            'type' => 'ImportHistory',
            'attributes' => [
                'url'                  => $this->importHistory->url,
                'document_line_number' => $this->importHistory->document_line_number,
                'status'               => $status->category(),
                'has_tags'             => $hasTags = $tags->found() !== 0,
                'tags'                 => $this->when($hasTags, [
                    'resolved' => $tags->resolved(),
                    'invalid'  => $tags->invalid(),
                    'found'    => $tags->found()
                ]),
                'status_reason' => $this->when(!$status->isSuccessful(), $status->toWord())
            ]
        ];
    }
}
