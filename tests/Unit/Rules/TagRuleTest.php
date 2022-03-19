<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\ValueObjects\Tag;
use Illuminate\Support\Str;
use Tests\TestCase;
use App\Rules\TagRule;

class TagRuleTest extends TestCase
{
    public function testWillFailWhenTagIsInvalid(): void
    {
        $rule = new TagRule;

        $this->assertFalse($rule->passes('value', ' '));
        $this->assertFalse($rule->passes('value', 'foo bar'));
        $this->assertFalse($rule->passes('value', 'foo@@'));
        $this->assertFalse($rule->passes('value', Str::random(Tag::MAX_LENGTH + 1)));
    }
}
