<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\ValueObjects\Tag;
use Illuminate\Http\Resources\Json\JsonResource;

final class TagResource extends JsonResource
{
    public function __construct(private Tag $tag)
    {
        parent::__construct($tag);
    }

    public function toArray($request)
    {
        return [
            'type' => 'tag',
            'attributes'  => [
                'name' => $this->tag->value,
            ]
        ];
    }
}
