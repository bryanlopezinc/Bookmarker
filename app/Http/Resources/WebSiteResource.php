<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DataTransferObjects\WebSite;
use Illuminate\Http\Resources\Json\JsonResource;

final class WebSiteResource extends JsonResource
{
    public function __construct(private WebSite $webSite)
    {
        parent::__construct($webSite);
    }

    public function toArray($request): array
    {
        return [
            'type' => 'site',
            'attributes' => [
                'id'   => $this->webSite->id->toInt(),
                'name' => $this->webSite->name->value,
            ]
        ];
    }
}
