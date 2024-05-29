<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\FetchActivities;

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use Tests\TestCase as BaseTestCase;
use Tests\Traits\CreatesCollaboration;

abstract class TestCase extends BaseTestCase
{
    use WithFaker;
    use CreatesCollaboration;

    final protected function fetchActivitiesTestResponse(array $query): TestResponse
    {
        return $this->getJson(
            route('fetchFolderActivities', $query)
        );
    }
}
