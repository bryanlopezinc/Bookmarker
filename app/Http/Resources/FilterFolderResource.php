<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Folder;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Exception;

final class FilterFolderResource extends JsonResource
{
    public function __construct(private Folder $folder)
    {
        parent::__construct($folder);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        /** @var array */
        $fullResponse = (new FolderResource($this->folder))->toArray($request);
        $filteredResponse = $fullResponse;
        $fields = $request->input('fields', []);

        if (empty($fields)) {
            return $fullResponse;
        }

        $filteredResponse['attributes'] = [];

        foreach ($fields as $field) {
            $value = Arr::get($fullResponse, "attributes.{$field}", function () use ($field) {
                throw new Exception("Invalid value attributes. {$field}"); // @codeCoverageIgnore
            });

            Arr::set($filteredResponse, "attributes.{$field}", $value);
        }

        return $filteredResponse;
    }
}
