<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

trait AssertValidPaginationData
{
    private function assertValidPaginationData(TestCase $testCase, string $routeName, array $requiredParams = []): void
    {
        $testResponse = function (array $data) use ($testCase, $routeName, $requiredParams) {
            return $testCase->getJson(route($routeName, array_merge($requiredParams, $data)));
        };

        $testResponse(['page' => '-1'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['page' => 'The page must be at least 1.']);

        $testResponse(['page' => '2001'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['page' => 'The page must not be greater than 2000.']);

        $testResponse(['per_page' => '3'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page' => 'The per page must be at least 15.']);

        $testResponse(['per_page' => '51'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page' => 'The per page must not be greater than 39.']);
    }
}
