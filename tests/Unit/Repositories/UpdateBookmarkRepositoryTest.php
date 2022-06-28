<?php

namespace Tests\Unit\Repositories;

use App\DataTransferObjects\Builders\UpdateBookmarkDataBuilder;
use App\Models\Bookmark;
use App\Repositories\UpdateBookmarkRepository;
use Database\Factories\BookmarkFactory;
use Database\Factories\TagFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UpdateBookmarkRepositoryTest extends TestCase
{
    use WithFaker;

    private UpdateBookmarkRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(UpdateBookmarkRepository::class);
    }

    public function testWillUpdateBookmark(): void
    {
        /** @var Bookmark */
        $model = BookmarkFactory::new()->create();

        $data = UpdateBookmarkDataBuilder::new()
            ->id($model->id)
            ->title($this->faker->word)
            ->description($this->faker->sentence)
            ->tags(TagFactory::new()->count(3)->make()->pluck('name')->all())
            ->UserId($model->user_id)
            ->build();

        $this->repository->update($data);

        $this->assertDatabaseHas(Bookmark::class, [
            'id' => $model->id,
            'has_custom_title' => true,
            'title' => $data->title->value,
            'description' => $data->description->value,
            'description_set_by_user' => true
        ]);
    }

    public function testWillUpdateOnlySpecifiedData(): void
    {
        /** @var Bookmark */
        $model = BookmarkFactory::new()->create();

        $data = UpdateBookmarkDataBuilder::new()
            ->id($model->id)
            ->title($this->faker->word)
            ->UserId($model->user_id)
            ->hasDescription(false)
            ->tags([])
            ->build();

        $this->repository->update($data);

        $this->assertDatabaseHas(Bookmark::class, [
            'id' => $model->id,
            'has_custom_title' => true,
            'title' => $data->title->value,
            'description' => $model->description,
            'description_set_by_user' => false
        ]);
    }
}
