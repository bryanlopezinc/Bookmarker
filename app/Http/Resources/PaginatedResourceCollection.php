<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Pagination\Paginator;
use Illuminate\Http\Resources\Json\ResourceCollection;

final class PaginatedResourceCollection extends ResourceCollection
{
    public function __construct(private Paginator $paginator, string $collects)
    {
        $this->collects = $collects;

        parent::__construct($paginator->getCollection());
    }

    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        $this->paginator->withQueryString();

        $data = $this->paginator->toArray();

        return [
            'data' => $this->collection,
            'links' => [
                'first' => $data['first_page_url'],
                'prev'  => $this->when($data['prev_page_url'], $data['prev_page_url'], $data['first_page_url']),
                'next'  => $this->when($this->paginator->hasMorePages(), $data['next_page_url']),
            ],
            'meta' => [
                'current_page'   => $data['current_page'],
                'path'           => $data['path'],
                'per_page'       => $data['per_page'],
                'has_more_pages' => $this->paginator->hasMorePages(),
            ]
        ];
    }
}
