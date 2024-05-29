<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\Updating;

use Tests\TestCase as BaseTestCase;
use App\Models\Folder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    final protected function updateFolderResponse(array $parameters = []): TestResponse
    {
        return $this->patchJson(
            route('updateFolder', Arr::only($parameters, ['folder_id'])),
            $parameters
        );
    }

    protected function tearDown(): void
    {
        Str::createRandomStringsNormally();

        parent::tearDown();
    }

    final public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/{folder_id}', 'updateFolder');
    }

    protected function assertUpdated(Folder $original, array $attributes): void
    {
        $updated = Folder::query()->find($original->id);

        $updated->offsetUnset('updated_at');
        $updated->offsetUnset('activities');
        $original->offsetUnset('updated_at');
        $original->offsetUnset('activities');

        $originalToArray = $original->toArray();
        $updatedToArray = $updated->toArray();

        $this->assertEquals(
            Arr::only($updatedToArray, $difference = array_keys($attributes)),
            $attributes
        );

        Arr::forget($updatedToArray, $difference);
        Arr::forget($originalToArray, $difference);

        $this->assertEquals($originalToArray, $updatedToArray);
    }
}
