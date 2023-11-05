<?php

declare(strict_types=1);

namespace App\Http;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;

final class CreateFavoritesResponse implements Responsable
{
    /**
     * @param int[] $created
     */
    public function __construct(private readonly array $created)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toResponse($request)
    {
        $callback = fn (int $id) => (string) $id;

        $data = [
            'created'  => array_values(array_map($callback, $this->created)),
            'conflict' => $request->collect('bookmarks')->diff($this->created)->map($callback)->values()->all()
        ];

        return new JsonResponse($data, JsonResponse::HTTP_CREATED);
    }
}
