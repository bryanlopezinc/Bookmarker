<?php

declare(strict_types=1);

namespace Tests\Unit\Cache\Folder;

use App\Cache\Folder\FolderRepository;
use App\Contracts\FolderRepositoryInterface;
use App\DataTransferObjects\Builders\FolderBuilder;
use App\DataTransferObjects\Folder;
use App\QueryColumns\FolderAttributes;
use App\ValueObjects\ResourceID;
use Database\Factories\FolderFactory;
use Illuminate\Contracts\Cache\Repository;
use Tests\TestCase;

class FolderRepositoryTest extends TestCase
{
    public function testWillRetrieveCachedFolder(): void
    {
        $cache = $this->getMockBuilder(Repository::class)->getMock();
        $repository = $this->getMockBuilder(FolderRepositoryInterface::class)->getMock();

        $repository->expects($this->never())->method('find');
        $cache->expects($this->never())->method('put');
        $cache->expects($this->once())->method('has')->willReturn(true);
        $cache->expects($this->once())->method('get')->willReturn(new Folder([]));

        $this->repositoryInstance($repository, $cache)->find(new ResourceID(2));
    }

    private function repositoryInstance($repository, $cache): FolderRepository
    {
        return new FolderRepository($repository, $cache, 100);
    }

    public function testWillCacheFolderWhenFolderDoesNotExist(): void
    {
        $cache = $this->getMockBuilder(Repository::class)->getMock();
        $repository = $this->getMockBuilder(FolderRepositoryInterface::class)->getMock();

        $folder = (new FolderBuilder())->setID(2)->setName('foo')->build();

        $cache->expects($this->once())->method('has')->willReturn(false);
        $cache->expects($this->never())->method('get');
        $cache->expects($this->once())->method('put')->with(
            $this->isType('string'),
            $this->callback(function (Folder $folderToCache) use ($folder) {
                $this->assertEquals($folderToCache, $folder);
                return true;
            }),
            $this->isType('int')
        );

        $repository->expects($this->once())->method('find')->willReturn($folder);

        $folderRepository = $this->repositoryInstance($repository, $cache);
        $folderRepository->find(new ResourceID(2));
    }

    public function test_will_return_all_attributes_when_folder_exist_in_cache(): void
    {
        $cache = $this->getMockBuilder(Repository::class)->getMock();
        $repository = $this->getMockBuilder(FolderRepositoryInterface::class)->getMock();

        $folder = FolderBuilder::fromModel(FolderFactory::new()->make())->build();

        $cache->expects($this->once())->method('has')->willReturn(true);
        $cache->expects($this->once())->method('get')->willReturn($folder);

        $this->assertEquals(
            $this->repositoryInstance($repository, $cache)->find(new ResourceID(2)),
            $folder
        );
    }

    public function test_will_return_all_attributes_when_folder_DoesNot_exist_in_cache(): void
    {
        $cache = $this->getMockBuilder(Repository::class)->getMock();
        $repository = $this->getMockBuilder(FolderRepositoryInterface::class)->getMock();

        $folder = FolderBuilder::fromModel(FolderFactory::new()->make())->build();

        $cache->expects($this->once())->method('has')->willReturn(false);
        $repository->expects($this->once())->method('find')->willReturn($folder);

        $this->assertEquals($this->repositoryInstance($repository, $cache)->find(new ResourceID(2)), $folder);
    }

    public function test_will_return_only_requested_attributes_when_folder_exist_in_cache(): void
    {
        $cache = $this->getMockBuilder(Repository::class)->getMock();
        $repository = $this->getMockBuilder(FolderRepositoryInterface::class)->getMock();

        $folder = FolderBuilder::fromModel(FolderFactory::new()->make())
            ->setID(4)
            ->setTags(['foo'])
            ->setBookmarksCount(5)
            ->build();

        $cache->expects($this->any())->method('has')->willReturn(true);
        $cache->expects($this->any())->method('get')->willReturn($folder);

        $this->assertWillReturnOnlyRequestedAttributes($this->repositoryInstance($repository, $cache));
    }

    public function test_will_return_only_requested_attributes_when_folder_DoesNotExist_in_cache(): void
    {
        $cache = $this->getMockBuilder(Repository::class)->getMock();
        $repository = $this->getMockBuilder(FolderRepositoryInterface::class)->getMock();

        $folder = FolderBuilder::fromModel(FolderFactory::new()->make())
            ->setID(4)
            ->setTags(['foo'])
            ->setBookmarksCount(5)
            ->build();

        $cache->expects($this->any())->method('has')->willReturn(false);
        $repository->expects($this->any())->method('find')->willReturn($folder);

        $this->assertWillReturnOnlyRequestedAttributes($this->repositoryInstance($repository, $cache));
    }

    private function assertWillReturnOnlyRequestedAttributes(FolderRepository $repository): void
    {
        $assert = function (string $attributes, \Closure $callback) use ($repository) {
            $callback(
                $repository->find(new ResourceID(22), FolderAttributes::only($attributes))
            );
        };

        $assert('', function (Folder $result) {
            $this->assertCount(8, $result->toArray());
        });

        $assert('id,user_id', function (Folder $result) {
            $this->assertCount(2, $result->toArray());
            $result->folderID; //will throw initialization exception if not set
            $result->ownerID;
        });

        $assert('id,user_id,bookmarks_count', function (Folder $result) {
            $this->assertCount(3, $result->toArray());
            $result->folderID;
            $result->ownerID;
            $result->storage;
        });

        $assert('is_public', function (Folder $result) {
            $this->assertCount(1, $result->toArray());
            $result->isPublic;
        });

        $assert('id,user_id,name,description,is_public', function (Folder $folder) {
            $this->assertCount(5, $folder->toArray());
            $folder->folderID;
            $folder->ownerID;
            $folder->name;
            $folder->description;
            $folder->isPublic;
        });

        $assert('id,user_id,name,description,is_public,tags', function (Folder $folder) {
            $this->assertCount(6, $folder->toArray());
            $folder->folderID;
            $folder->ownerID;
            $folder->name;
            $folder->description;
            $folder->isPublic;
            $folder->tags;
        });
    }
}
