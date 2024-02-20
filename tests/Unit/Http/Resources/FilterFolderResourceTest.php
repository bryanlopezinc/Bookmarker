<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\FilterFolderResource;
use App\Models\Folder;
use Tests\TestCase;
use Database\Factories\FolderFactory;
use Illuminate\Testing\AssertableJsonString;

class FilterFolderResourceTest extends TestCase
{
    public function testWillReturnAllAttributesWhenNoFieldsAreRequested(): void
    {
        $this->assertWillReturnPartialResource('', function (AssertableJsonString $json) {
            $json->assertCount(9, 'data.attributes')
                ->assertStructure([
                    "data" => [
                        "type",
                        "attributes" => [
                            "id",
                            "name",
                            "description",
                            "has_description",
                            "date_created",
                            "last_updated",
                            "visibility",
                            'collaborators_count',
                            'storage' => [
                                'items_count',
                                'capacity',
                                'is_full',
                                'available',
                                'percentage_used'
                            ]
                        ]
                    ]
                ]);
        });
    }

    public function testWillReturnSpecifiedAttributes(): void
    {
        foreach ([
            "id",
            "name",
            "description",
            "has_description",
            "date_created",
            "last_updated",
            "visibility",
        ] as $field) {
            $this->assertWillReturnPartialResource($field, function (AssertableJsonString $json) use ($field) {
                $json->assertCount(1, 'data.attributes')
                    ->assertStructure([
                        "data" => [
                            "type",
                            "attributes" => [$field]
                        ]
                    ]);
            });
        }
    }

    public function testWillReturnSpecifiedAttributes_2(): void
    {
        $this->assertWillReturnPartialResource('description,has_description', function (AssertableJsonString $json) {
            $json->assertCount(2, 'data.attributes')
                ->assertStructure([
                    "data" => [
                        "type",
                        "attributes" => [
                            "description",
                            'has_description'
                        ]
                    ]
                ]);
        });
    }

    public function testWillReturnSpecifiedAttributes_3(): void
    {
        $this->assertWillReturnPartialResource('id,name,storage.items_count', function (AssertableJsonString $json) {
            $json->assertCount(3, 'data.attributes')
                ->assertCount(1, 'data.attributes.storage')
                ->assertStructure([
                    "data" => [
                        "type",
                        "attributes" => [
                            "id",
                            'name',
                            'storage' => [
                                'items_count'
                            ]
                        ]
                    ]
                ]);
        });
    }

    public function testWillReturnOnlyStorageAttributes(): void
    {
        $this->assertWillReturnPartialResource('storage', function (AssertableJsonString $json) {
            $json->assertCount(1, 'data.attributes')
                ->assertCount(5, 'data.attributes.storage')
                ->assertStructure([
                    "data" => [
                        "type",
                        "attributes" => [
                            'storage' => [
                                'items_count',
                                'capacity',
                                'is_full',
                                'available',
                                'percentage_used'
                            ]
                        ]
                    ]
                ]);
        });
    }

    public function testWillReturnOnlyStorageData(): void
    {
        foreach ([
            'items_count',
            'capacity',
            'is_full',
            'available',
            'percentage_used'
        ] as $field) {
            $this->assertWillReturnPartialResource("storage.$field", function (AssertableJsonString $json) use ($field) {
                $json->assertCount(1, 'data.attributes')
                    ->assertCount(1, 'data.attributes.storage')
                    ->assertStructure([
                        "data" => [
                            "type",
                            "attributes" => [
                                'storage' => [$field]
                            ]
                        ]
                    ]);
            });
        }
    }

    private function assertWillReturnPartialResource(string $fields, \Closure $assertion): void
    {
        $request = request();

        if ($fields) {
            $request->merge(['fields' => explode(',', $fields)]);
        }

        /** @var Folder */
        $folder = FolderFactory::new()->make(['id' => 200]);
        $folder->tags = collect();
        $folder->bookmarks_count = 2;
        $folder->created_at = now();
        $folder->updated_at = now();

        $response = (new FilterFolderResource($folder))->toResponse($request)->content();

        $assertion(new AssertableJsonString($response));
    }
}
