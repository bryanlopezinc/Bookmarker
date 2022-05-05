<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class PaginatedResourceCollection extends AnonymousResourceCollection
{
    public function __construct(private Paginator $paginator, string $collects)
    {
        parent::__construct($paginator, $collects);
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @param mixed $paginated
     */
    public function paginationInformation($request, $paginated, array $default): array
    {
        unset(
            $default['links']['last'],
            $default['meta']['from'],
            $default['meta']['to']
        );

        $default['meta']['has_more_pages'] = $this->paginator->hasMorePages();

        if (!$this->paginator->hasMorePages()) {
            unset($default['links']['next']);
        }

        if ($this->paginator->currentPage() === 1) {
            $default['links']['prev'] = $default['links']['first'];
        }

        return $default;
    }
}
