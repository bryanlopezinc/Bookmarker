<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DataTransferObjects\UserCollaboration;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

final class FilterUserCollaborationResource extends JsonResource
{
    public function __construct(private UserCollaboration $userCollaboration)
    {
        parent::__construct($userCollaboration);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        /** @var array */
        $fullResponse = (new UserCollaborationResource($this->userCollaboration))->toArray($request);
        $filteredResponse = $fullResponse;
        $fields = $request->input('fields', []);

        if (empty($fields)) {
            return $fullResponse;
        }

        $filteredResponse['attributes'] = [];

        foreach ($fields as $field) {
            $value = Arr::get($fullResponse, "attributes.$field", function () use ($field) {
                throw new \Exception("Invalid value attributes. $field");
            });

            Arr::set($filteredResponse, "attributes.$field", $value);
        }

        return $filteredResponse;
    }
}
