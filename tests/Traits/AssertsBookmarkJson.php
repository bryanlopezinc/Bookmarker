<?php

declare(strict_types=1);

namespace Tests\Traits;

use Illuminate\Testing\AssertableJsonString;

trait AssertsBookmarkJson
{
    protected function assertBookmarkJson(array $data): void
    {
        $testJson = new AssertableJsonString($data);

        $testJson->assertCount(15, 'attributes')
            ->assertCount(3, 'attributes.created_on')
            ->assertStructure([
                'type',
                'attributes' => [
                    'id',
                    'title',
                    'web_page_link',
                    'has_preview_image',
                    'preview_image_url',
                    'description',
                    'has_description',
                    'source',
                    'tags',
                    'has_tags',
                    'tags_count',
                    'is_healthy',
                    'is_user_favorite',
                    'has_duplicates',
                    'created_on' => [
                        'date_readable',
                        'date_time',
                        'date',
                    ]
                ]
            ]);
    }
}
