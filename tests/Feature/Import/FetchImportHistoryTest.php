<?php

namespace Tests\Feature\Import;

use Database\Factories\ImportFactory;
use Database\Factories\ImportHistoryFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use App\Import\BookmarkImportStatus as Status;

class FetchImportHistoryTest extends TestCase
{
    use WithFaker;

    protected function fetchImportHistoryResponse(array $parameters = []): TestResponse
    {
        return $this->getJson(route('fetchImportHistory', $parameters));
    }

    #[Test]
    public function path(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/imports/{import_id}/history', 'fetchImportHistory');
    }

    #[Test]
    public function willReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->fetchImportHistoryResponse(['import_id' => $this->faker->uuid])->assertUnauthorized();
    }

    #[Test]
    public function willReturnNotFoundWhenImportIdIsInvalid(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->fetchImportHistoryResponse(['import_id' => 'f00'])->assertNotFound();
    }

    #[Test]
    public function willSortByLatest(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $import = ImportFactory::new()->create(['user_id' => $user->id]);

        $importHistories = ImportHistoryFactory::times(3)->create(['import_id' => $import->import_id]);

        $this->fetchImportHistoryResponse(['import_id' => $import->import_id])
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.attributes.url', $importHistories[2]->url)
            ->assertJsonPath('data.1.attributes.url', $importHistories[1]->url)
            ->assertJsonPath('data.2.attributes.url', $importHistories[0]->url)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'type',
                        'attributes' => [
                            'url',
                            'document_line_number',
                            'status',
                            'tags_count',
                            'has_tags'
                        ]
                    ]
                ]
            ]);
    }

    #[Test]
    public function filterImports(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $import = ImportFactory::new()->create(['user_id' => $user->id]);

        $factory = ImportHistoryFactory::new(['import_id' => $import->import_id]);

        $importHistories = [
            $factory->create(),
            $factory->failed(reason: Status::FAILED_DUE_TO_INVALID_TAG)->create(),
            $factory->failed(reason: Status::FAILED_DUE_TO_MERGE_TAGS_EXCEEDED)->create(),
            $factory->failed(reason: Status::FAILED_DUE_TO_SYSTEM_ERROR)->create(),
            $factory->failed(reason: Status::FAILED_DUE_TO_INVALID_URL)->create(),
            $factory->failed(reason: Status::FAILED_DUE_TO_TOO_MANY_TAGS)->create(),
            $factory->skipped(reason: Status::SKIPPED_DUE_TO_INVALID_TAG)->create(),
            $factory->skipped(reason: Status::SKIPPED_DUE_TO_MERGE_TAGS_EXCEEDED)->create(),
            $factory->skipped(reason: Status::SKIPPED_DUE_TO_TOO_MANY_TAGS)->create(),
        ];

        $this->fetchImportHistoryResponse(['filter' => 'failed', 'import_id' => $import->import_id])
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.attributes.url', $importHistories[5]->url)
            ->assertJsonPath('data.1.attributes.url', $importHistories[4]->url)
            ->assertJsonPath('data.2.attributes.url', $importHistories[3]->url)
            ->assertJsonPath('data.3.attributes.url', $importHistories[2]->url)
            ->assertJsonPath('data.4.attributes.url', $importHistories[1]->url);

        $this->fetchImportHistoryResponse(['filter' => 'skipped', 'import_id' => $import->import_id])
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.attributes.url', $importHistories[8]->url)
            ->assertJsonPath('data.1.attributes.url', $importHistories[7]->url)
            ->assertJsonPath('data.2.attributes.url', $importHistories[6]->url);

        $this->fetchImportHistoryResponse(['filter' => 'foo', 'import_id' => $import->import_id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['filter' => 'The selected filter is invalid.']);
    }

    #[Test]
    public function willReturnNotFoundWhenImportDoesNotExist(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->fetchImportHistoryResponse(['import_id' => $this->faker->uuid])->assertNotFound();
    }

    #[Test]
    public function willReturnNotFoundWhenImportDoesNotBelongToUser(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $import = ImportFactory::new()->create(['user_id' => UserFactory::new()->create()->id]);

        $this->loginUser(UserFactory::new()->create());
        $this->fetchImportHistoryResponse(['import_id' => $import->import_id])->assertNotFound();
    }

    #[Test]
    public function whenStatusIsSuccess(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $import = ImportFactory::new()->create(['user_id' => $user->id]);

        ImportHistoryFactory::new()->create(['import_id' => $import->import_id]);

        $this->fetchImportHistoryResponse(['import_id' => $import->import_id])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(5, 'data.0.attributes')
            ->assertJsonPath('data.0.attributes.status', 'success')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'type',
                        'attributes' => [
                            'url',
                            'document_line_number',
                            'status',
                            'tags_count',
                            'has_tags'
                        ]
                    ]
                ]
            ]);
    }

    #[Test]
    public function whenImportHasTags(): void
    {
        $this->markTestIncomplete('Assert will show correct tags data');
    }

    #[Test]
    public function whenStatusIsFailed(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $import = ImportFactory::new()->create(['user_id' => $user->id]);

        ImportHistoryFactory::new()->failed()->create(['import_id' => $import->import_id]);

        $this->fetchImportHistoryResponse(['import_id' => $import->import_id])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(6, 'data.0.attributes')
            ->assertJsonPath('data.0.attributes.status', 'failed')
            ->assertJsonPath('data.0.attributes.status_reason', 'FailedDueToSystemError')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'type',
                        'attributes' => [
                            'url',
                            'document_line_number',
                            'status',
                            'tags_count',
                            'has_tags',
                            'status_reason'
                        ]
                    ]
                ]
            ]);
    }

    #[Test]
    public function whenImportWasSkipped(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $import = ImportFactory::new()->create(['user_id' => $user->id]);

        ImportHistoryFactory::new()->skipped()->create(['import_id' => $import->import_id]);

        $this->fetchImportHistoryResponse(['import_id' => $import->import_id])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(6, 'data.0.attributes')
            ->assertJsonPath('data.0.attributes.status', 'skipped')
            ->assertJsonPath('data.0.attributes.status_reason', 'SkippedDueToInvalidTag')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'type',
                        'attributes' => [
                            'url',
                            'document_line_number',
                            'status',
                            'tags_count',
                            'has_tags',
                            'status_reason'
                        ]
                    ]
                ]
            ]);
    }
}
