<?php

declare(strict_types=1);

namespace Tests\Traits;

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Bus;

trait CreatesBookmark
{
    use WithFaker;

    /**
     * @param array<string,mixed> $data
     *
     * ```php
     *   $data = [
     *         'url' => string,
     *          'tags' => array,
     *    ]
     * ```
     */
    private function saveBookmark(array $data = []): void
    {
        Bus::fake();

        $data['url'] = $data['url'] ?? $this->faker->url;

        if (isset($data['tags'])) {
            $data['tags'] =  implode(',', $data['tags']);
        }

        $this->postJson(route('createBookmark'), $data)->assertCreated();
    }
}
