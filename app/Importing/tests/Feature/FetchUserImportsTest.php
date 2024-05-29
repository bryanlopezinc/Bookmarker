<?php

declare(strict_types=1);

namespace App\Importing\tests\Feature;

use App\Importing\Repositories\ImportStatRepository;
use App\Importing\Enums\ImportBookmarksStatus as Status;
use App\Importing\DataTransferObjects\ImportStats;
use App\Importing\Models\Import;
use Database\Factories\ImportFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FetchUserImportsTest extends TestCase
{
    protected function fetchImportsResponse(array $parameters = []): TestResponse
    {
        return $this->getJson(route('fetchUserImports', $parameters));
    }

    #[Test]
    public function path(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/imports', 'fetchUserImports');
    }

    #[Test]
    public function willReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->fetchImportsResponse()->assertUnauthorized();
    }

    #[Test]
    public function willSortByLatest(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        /** @var Import[] */
        $imports = ImportFactory::times(3)->create(['user_id' => $user->id]);

        $this->fetchImportsResponse()
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.attributes.id', $imports[2]->public_id->present())
            ->assertJsonPath('data.1.attributes.id', $imports[1]->public_id->present())
            ->assertJsonPath('data.2.attributes.id', $imports[0]->public_id->present())
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'type',
                        'attributes' => [
                            'id',
                            'status',
                            'imported_at',
                            'stats'
                        ]
                    ]
                ]
            ]);
    }

    #[Test]
    public function filterImports(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $factory = ImportFactory::new(['user_id' => $user->id]);

        $imports = [
            $factory->pending()->create(),
            $factory->importing()->create(),
            $factory->failed(reason: Status::FAILED_DUE_TO_INVALID_TAG)->create(),
            $factory->failed(reason: Status::FAILED_DUE_TO_MERGE_TAGS_EXCEEDED)->create(),
            $factory->failed(reason: Status::FAILED_DUE_TO_SYSTEM_ERROR)->create(),
            $factory->failed(reason: Status::FAILED_DUE_TO_INVALID_BOOKMARK_URL)->create(),
            $factory->failed(reason: Status::FAILED_DUE_TO_TO_MANY_TAGS)->create(),
        ];

        $this->fetchImportsResponse(['filter' => 'failed'])
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.attributes.id', $imports[6]->public_id->present())
            ->assertJsonPath('data.1.attributes.id', $imports[5]->public_id->present())
            ->assertJsonPath('data.2.attributes.id', $imports[4]->public_id->present())
            ->assertJsonPath('data.3.attributes.id', $imports[3]->public_id->present())
            ->assertJsonPath('data.4.attributes.id', $imports[2]->public_id->present());

        $this->fetchImportsResponse(['filter' => 'pending'])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.id', $imports[0]->public_id->present());

        $this->fetchImportsResponse(['filter' => 'importing'])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.id', $imports[1]->public_id->present());
    }

    #[Test]
    public function whenStatusIsSuccess(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $import = ImportFactory::new()
            ->successful(new ImportStats(100, 3, 104, 0, 1))
            ->create(['user_id' => $user->id]);

        $this->fetchImportsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(4, 'data.0.attributes')
            ->assertJsonPath('data.0.attributes.id', $import->public_id->present())
            ->assertJsonPath('data.0.attributes.status', 'success')
            ->assertJsonPath('data.0.attributes.stats', [
                'imported'    => 100,
                'found'       => 104,
                'skipped'     => 3,
                'failed'      => 1,
                'unProcessed' => 0
            ]);
    }

    #[Test]
    public function whenStatusIsPending(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $import = ImportFactory::new()->pending()->create(['user_id' => $user->id]);

        $this->fetchImportsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(4, 'data.0.attributes')
            ->assertJsonPath('data.0.attributes.id', $import->public_id->present())
            ->assertJsonPath('data.0.attributes.status', 'pending');
    }

    #[Test]
    public function whenStatusIsFailed(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $import = ImportFactory::new()
            ->failed(new ImportStats(100, 3, 104, 0, 1), Status::FAILED_DUE_TO_INVALID_BOOKMARK_URL)
            ->create(['user_id' => $user->id]);

        $this->fetchImportsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(5, 'data.0.attributes')
            ->assertJsonPath('data.0.attributes.id', $import->public_id->present())
            ->assertJsonPath('data.0.attributes.status', 'failed')
            ->assertJsonPath('data.0.attributes.reason_for_failure', 'FailedDueToInvalidUrl')
            ->assertJsonPath('data.0.attributes.stats', [
                'imported'    => 100,
                'found'       => 104,
                'skipped'     => 3,
                'failed'      => 1,
                'unProcessed' => 0
            ]);
    }

    #[Test]
    public function whenStatusIsImporting(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        /** @var ImportStatRepository */
        $cacheRepository = app(ImportStatRepository::class);

        $import = ImportFactory::new()->importing()->create(['user_id' => $user->id]);

        $cacheRepository->put($import->id, new ImportStats(100, 3, 104, 0, 1));

        $this->fetchImportsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(4, 'data.0.attributes')
            ->assertJsonPath('data.0.attributes.id', $import->public_id->present())
            ->assertJsonPath('data.0.attributes.status', 'importing')
            ->assertJsonPath('data.0.attributes.stats', [
                'imported'    => 100,
                'found'       => 104,
                'skipped'     => 3,
                'failed'      => 1,
                'unProcessed' => 0
            ]);
    }
}
