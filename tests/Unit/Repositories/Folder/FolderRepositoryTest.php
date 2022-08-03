<?php

namespace Tests\Unit\Repositories\Folder;

use App\Collections\TagsCollection;
use App\DataTransferObjects\Folder;
use App\QueryColumns\FolderAttributes;
use Tests\TestCase;
use App\Repositories\Folder\FolderRepository;
use App\Repositories\TagRepository;
use App\ValueObjects\ResourceID;
use Database\Factories\FolderFactory;
use Database\Factories\TagFactory;
use Illuminate\Foundation\Testing\WithFaker;
use ReflectionProperty;

class FolderRepositoryTest extends TestCase
{
    use WithFaker;

    /**
     * Attributes assertions are combinations used throughOut the app.
     */
    public function testWillFetchOnlyRequestedAttributes(): void
    {
        //Assert will return all attributes.
        $this->assertWillReturnOnlyAttributes('', function (Folder $folder) {
            $folderDTOPublicProperties = (new \ReflectionClass($folder::class))->getProperties(ReflectionProperty::IS_PUBLIC);

            $expected = collect($folderDTOPublicProperties)
                ->map(fn (ReflectionProperty $property) => $property->name)
                ->sort()
                ->values()
                ->all();

            $this->assertEquals($expected, collect($folder->toArray())->keys()->sort()->values()->all());
        });

        $this->assertWillReturnOnlyAttributes('id,userId,storage', function (Folder $folder) {
            $this->assertCount(3, $folder->toArray());
            $folder->folderID; // will throw initialization exception if not retrived
            $folder->ownerID;
            $folder->storage;
        });

        $this->assertWillReturnOnlyAttributes('id,userId', function (Folder $folder) {
            $this->assertCount(2, $folder->toArray());
            $folder->folderID;
            $folder->ownerID;
        });

        $this->assertWillReturnOnlyAttributes('privacy', function (Folder $folder) {
            $this->assertCount(1, $folder->toArray());
            $folder->isPublic;
        });

        $this->assertWillReturnOnlyAttributes('id,userId,name,description,privacy', function (Folder $folder) {
            $this->assertCount(5, $folder->toArray());
            $folder->folderID;
            $folder->ownerID;
            $folder->name;
            $folder->description;
            $folder->isPublic;
        });

        $this->assertWillReturnOnlyAttributes('id,userId,name,description,privacy,tags', function (Folder $folder) {
            $this->assertCount(6, $folder->toArray());
            $folder->folderID;
            $folder->ownerID;
            $folder->name;
            $folder->description;
            $folder->isPublic;
            $folder->tags;
        });
    }

    private function assertWillReturnOnlyAttributes(string $attributes, \Closure $assertion): void
    {
        $repository = new FolderRepository;
        $folderID = new ResourceID(FolderFactory::new()->create()->id);

        $folder = $repository->find($folderID, FolderAttributes::only($attributes));

        $assertion($folder);
    }

    public function testWillReturnTags(): void
    {
        $model = FolderFactory::new()->create();
        $repository = new FolderRepository;
        $tags = TagFactory::new()->count(5)->make();

        (new TagRepository)->attach(TagsCollection::make($tags), $model);

        $folder = $repository->find(new ResourceID($model->id));

        $this->assertEquals(
            [],
            $folder->tags->toStringCollection()->diff($tags->pluck('name'))->all(),
        );
    }
}
