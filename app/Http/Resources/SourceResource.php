<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Source;
use Illuminate\Http\Resources\Json\JsonResource;

final class SourceResource extends JsonResource
{
    public function __construct(private Source $source)
    {
        parent::__construct($source);
    }

    public function toArray($request): array
    {
        return [
            'type'       => 'source',
            'attributes' => [
                'id'   => $this->source->id,
                'name' => $this->source->name,
            ]
        ];
    }
}
