<?php

declare(strict_types=1);

namespace Tests\Unit\Readers;

use App\Readers\DOMReader as Reader;
use App\ValueObjects\Url;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class DOMReaderTest extends TestCase
{
    use WithFaker;

    public function test_default(): void
    {
        $reader = new Reader($this->html(), new Url($this->faker->url));

        $this->assertFalse($reader->getSiteName());
        $this->assertFalse($reader->getPageDescription());
        $this->assertFalse($reader->getPreviewImageUrl());
        $this->assertFalse($reader->getPageTitle());
        $this->assertFalse($reader->getCanonicalUrl());
    }

    public function test_will_first_attempt_to_read_og_description_tag(): void
    {
        $description = implode(' ', $this->faker->sentences());

        $html = $this->html(<<<HTML
                <meta name="description" content="A foo bar site">
                <meta name="twitter:description" content="Twitter Description">
                <meta property="og:description" content="$description">
        HTML);

        $this->assertEquals($description, (new Reader($html, new Url($this->faker->url)))->getPageDescription());
    }

    public function test_will_return_false_when_og_description_tag_is_blank(): void
    {
        $html = $this->html(<<<HTML
                <meta property="og:description" content=" ">
        HTML);

        $this->assertFalse((new Reader($html, new Url($this->faker->url)))->getPageDescription());
    }

    private function html(string $insert = ''): string
    {
        $html = <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta http-equiv="X-UA-Compatible" content="IE=edge">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                 $insert
            </head>
            <body>
            </body>
            </html>
        HTML;

        return $html;
    }

    public function test_will_read_description_meta_tag_If_og_desceription_tag_is_not_found(): void
    {
        $description = $this->faker->sentence;

        $html = $this->html(<<<HTML
                <meta name="description" content="$description">
                <meta name="twitter:description" content="Twitter Description">
        HTML);

        $this->assertEquals($description, (new Reader($html, new Url($this->faker->url)))->getPageDescription());
    }

    public function test_will_return_false_when_description_meta_tag_is_blank(): void
    {
        $html = $this->html(<<<HTML
                <meta name="description" content="  ">
        HTML);

        $this->assertFalse((new Reader($html, new Url($this->faker->url)))->getPageDescription());
    }

    public function test_will_read_twiiter_tag_If_no_description_tags_are_present(): void
    {
        $description = $this->faker->sentence;

        $html = $this->html(<<<HTML
                <meta name="twitter:description" content="$description">
        HTML);

        $this->assertEquals($description, (new Reader($html, new Url($this->faker->url)))->getPageDescription());
    }

    public function test_will_return_false_twitter_description_tag_is_blank(): void
    {
        $html = $this->html(<<<HTML
                <meta name="twitter:description" content="  ">
        HTML);

        $this->assertFalse((new Reader($html, new Url($this->faker->url)))->getPageDescription());
    }

    public function test_will_read_og_Image_tag(): void
    {
        $html = $this->html(<<<HTML
                <meta property="og:image" content="https://image.com/smike.png">
        HTML);

        $this->assertEquals('https://image.com/smike.png', (new Reader($html, new Url($this->faker->url)))->getPreviewImageUrl()->value);
    }

    public function test_will_read_twitter_Image_tag_if_og_image_tag_is_missing(): void
    {
        $html = $this->html(<<<HTML
                <meta name="twitter:image" content="https://twitter.png">
        HTML);

        $this->assertEquals('https://twitter.png', (new Reader($html, new Url($this->faker->url)))->getPreviewImageUrl()->value);
    }

    public function test_will_return_false_If_og_Image_tag_is_invalid(): void
    {
        $html = $this->html(<<<HTML
                <meta property="og:image" content="<script> alert('hacked') </script>">
        HTML);

        $this->assertFalse((new Reader($html, new Url($this->faker->url)))->getPreviewImageUrl());
    }

    public function test_will_return_false_If_twiiter_Image_tag_is_invalid(): void
    {
        $html = $this->html(<<<HTML
                <meta name="twitter:image" content="<script> alert('hacked') </script>">
        HTML);

        $this->assertFalse((new Reader($html, new Url($this->faker->url)))->getPreviewImageUrl());
    }

    public function test_will_first_read_og_title_tag(): void
    {
        $title = implode(' ', $this->faker->sentences());

        $html = $this->html(<<<HTML
                <meta property="og:title" content="$title">
                <meta name="twitter:title" content="BitCoin is down">
                <title>Page Title</title>
        HTML);

        $this->assertEquals($title, (new Reader($html, new Url($this->faker->url)))->getPageTitle());
    }

    public function test_will_return_false_when_og_title_is_blank(): void
    {
        $html = $this->html(<<<HTML
                <meta property="og:title" content="  ">
                <title>Page Title</title>
        HTML);

        $this->assertFalse((new Reader($html, new Url($this->faker->url)))->getPageTitle());
    }

    public function test_will_read_title_tag_if_og_title_tag_Is_absent(): void
    {
        $title = $this->faker->title;

        $html = $this->html(<<<HTML
                <title>$title</title>
                <meta name="twitter:title" content="Why are cryto gurus silent :-)">
        HTML);

        $this->assertEquals($title, (new Reader($html, new Url($this->faker->url)))->getPageTitle());
    }

    public function test_will_return_false_when_title_tag_is_blank(): void
    {
        $html = $this->html(<<<HTML
                <title></title>
        HTML);

        $this->assertFalse((new Reader($html, new Url($this->faker->url)))->getPageTitle());
    }

    public function test_will_read_twitter_tag_if_no_title_tags_are_found(): void
    {
        $title = $this->faker->title;

        $html = $this->html(<<<HTML
                <meta name="twitter:title" content="$title">
        HTML);

        $this->assertEquals($title, (new Reader($html, new Url($this->faker->url)))->getPageTitle());
    }

    public function test_will_return_false_when_twiiter_title_tag_is_blank(): void
    {
        $html = $this->html(<<<HTML
                <meta name="twitter:title" content="  ">
        HTML);

        $this->assertFalse((new Reader($html, new Url($this->faker->url)))->getPageTitle());
    }

    public function test_will_encode_title_tag_content_when_invalid(): void
    {
        $html = $this->html(<<<HTML
                <title><script>alert('hacked')</script></title>
        HTML);

        $this->assertEquals('alert(&#039;hacked&#039;)', (new Reader($html, new Url($this->faker->url)))->getPageTitle());
    }

    public function test_will_encode_twitter_title_tag_content_when_invalid(): void
    {
        $html = $this->html(<<<HTML
                <meta name="twitter:title" content=alert('hacked')>
        HTML);

        $this->assertEquals('alert(&#039;hacked&#039;)', (new Reader($html, new Url($this->faker->url)))->getPageTitle());
    }

    public function test_will_encode_og_title_when_invalid(): void
    {
        $html = $this->html(<<<HTML
                <meta property="og:title" content="<script>alert('hacked')</script>">
        HTML);

        $this->assertEquals('&lt;script&gt;alert(&#039;hacked&#039;)&lt;/script&gt;', (new Reader($html, new Url($this->faker->url)))->getPageTitle());
    }

    public function test_will_read_application_name_tag_first(): void
    {
        $html = $this->html(<<<HTML
                <meta property="og:site_name" content="PlayStation">
                <meta name="application-name" content="Xbox">
                <meta name="twitter:site" content="@USERNAME">
        HTML);

        $this->assertEquals('Xbox', (new Reader($html, new Url($this->faker->url)))->getSiteName());
    }

    public function test_will_read_og_site_name_If_no_application_name_tag(): void
    {
        $html = $this->html(<<<HTML
                <meta property="og:site_name" content="PlayStation">
                <meta name="twitter:site" content="@USERNAME">
        HTML);

        $this->assertEquals('PlayStation', (new Reader($html, new Url($this->faker->url)))->getSiteName());
    }

    public function test_will_read_twitter_tag_If_no_application_name_tags_are_found(): void
    {
        $html = $this->html(<<<HTML
                <meta name="twitter:site" content="@RickRoss">
        HTML);

        $this->assertEquals('@RickRoss', (new Reader($html, new Url($this->faker->url)))->getSiteName());
    }

    public function test_will_first_read_canonical_tag(): void
    {
        $url = 'https://www.foo.com/en/path/to/baz';

        $html = $this->html(<<<HTML
                <link rel="canonical" href="$url">
                <meta property="og:url" content="https://www.rottentomatoes.com/m/thor_love_and_thunder">
        HTML);

        $this->assertEquals(
            $url,
            (new Reader($html, new Url($url)))->getCanonicalUrl()->value
        );
    }

    public function test_will_fallback_to_ogUrl_when_no_canonical_tag(): void
    {
        $url = 'https://www.foo.com/en/path/to/baz';

        $html = $this->html(<<<HTML
                <meta property="og:url" content="$url">
        HTML);

        $this->assertEquals(
            $url,
            (new Reader($html, new Url($url)))->getCanonicalUrl()->value
        );
    }
}
