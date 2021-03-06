<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use Tests\TestCase;
use App\Http\Requests\UpdateBookmarkRequest;
use App\Rules\ResourceIdRule;
use App\ValueObjects\Tag;

class UpdateBookmarkRequestTest extends TestCase
{
    public function testRuleIs(): void
    {
        $request = new UpdateBookmarkRequest;

        $this->assertEquals($request->rules(), [
            'tags' => ['filled', 'max:15'],
            'tags.*' => Tag::rules(['distinct:strict']),
            'title' => ["filled", "string", "max:100", "required_without_all:tags,description",],
            'id' => ["required", new ResourceIdRule],
            'description' => ['nullable', 'max:200', 'filled']
        ]);
    }
}
