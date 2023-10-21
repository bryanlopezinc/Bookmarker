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

    private Reader $reader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reader = new Reader;
    }

    public function test_default(): void
    {
        $this->reader->loadHTML($this->html(), new Url($this->faker->url));

        $this->assertFalse($this->reader->getSiteName());
        $this->assertFalse($this->reader->getPageDescription());
        $this->assertFalse($this->reader->getPreviewImageUrl());
        $this->assertFalse($this->reader->getPageTitle());
        $this->assertFalse($this->reader->getCanonicalUrl());
    }

    public function testWillReadOpenGraphDescriptionTagBeforeOtherDescriptionTags(): void
    {
        $description = implode(' ', $this->faker->sentences());

        $html = $this->html(<<<HTML
                <meta name="description" content="A foo bar site">
                <meta name="twitter:description" content="Twitter Description">
                <meta property="og:description" content="$description">
        HTML);

        $this->reader->loadHTML($html, new Url($this->faker->url));

        $this->assertEquals($description, $this->reader->getPageDescription());
    }

    public function test_will_return_false_when_og_description_tag_is_blank(): void
    {
        $html = $this->html(<<<HTML
                <meta property="og:description" content=" ">
        HTML);

        $this->reader->loadHTML($html, new Url($this->faker->url));

        $this->assertFalse($this->reader->getPageDescription());
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

    public function test_will_read_description_meta_tag_when_og_description_tag_is_not_present(): void
    {
        $description = $this->faker->sentence;

        $html = $this->html(<<<HTML
                <meta name="description" content="$description">
                <meta name="twitter:description" content="Twitter Description">
        HTML);

        $this->reader->loadHTML($html, new Url($this->faker->url));

        $this->assertEquals($description, $this->reader->getPageDescription());
    }

    public function test_will_return_false_when_description_meta_tag_is_blank(): void
    {
        $html = $this->html(<<<HTML
                <meta name="description" content="  ">
        HTML);

        $this->reader->loadHTML($html, new Url($this->faker->url));

        $this->assertFalse($this->reader->getPageDescription());
    }

    public function test_will_read_twitter_tag_when_no_description_tags_are_present(): void
    {
        $description = $this->faker->sentence;

        $html = $this->html(<<<HTML
                <meta name="twitter:description" content="$description">
        HTML);

        $this->reader->loadHTML($html, new Url($this->faker->url));

        $this->assertEquals($description, $this->reader->getPageDescription());
    }

    public function test_will_return_false_when_twitter_description_tag_is_blank(): void
    {
        $html = $this->html(<<<HTML
                <meta name="twitter:description" content="  ">
        HTML);

        $this->reader->loadHTML($html, new Url($this->faker->url));

        $this->assertFalse($this->reader->getPageDescription());
    }

    public function test_will_read_og_Image_tag(): void
    {
        $html = $this->html(<<<HTML
                <meta property="og:image" content="https://image.com/smike.png">
        HTML);

        $this->reader->loadHTML($html, new Url($this->faker->url));

        $this->assertEquals('https://image.com/smike.png', $this->reader->getPreviewImageUrl()->toString());
    }

    public function test_will_read_twitter_Image_tag_when_og_image_tag_is_missing(): void
    {
        $html = $this->html(<<<HTML
                <meta name="twitter:image" content="https://twitter.png">
        HTML);

        $this->reader->loadHTML($html, new Url($this->faker->url));

        $this->assertEquals('https://twitter.png', $this->reader->getPreviewImageUrl()->toString());
    }

    public function test_will_return_false_when_og_Image_tag_is_invalid(): void
    {
        foreach ([
            "<script> alert('hacked') </script>",
            "ldap://ds.example.com:389",
        ] as $content) {
            $html = $this->html(<<<HTML
                <meta property="og:image" content="$content">
            HTML);

            $this->reader->loadHTML($html, new Url($this->faker->url));

            $this->assertFalse($this->reader->getPreviewImageUrl(), "failed asserting that $content is invalid");
        }
    }

    public function test_will_return_false_when_twitter_Image_tag_is_invalid(): void
    {
        foreach ([
            "<script> alert('hacked') </script>",
            "ldap://ds.example.com:389",
        ] as $content) {
            $html = $this->html(<<<HTML
                <meta name="twitter:image" content="$content">
            HTML);

            $this->reader->loadHTML($html, new Url($this->faker->url));

            $this->assertFalse($this->reader->getPreviewImageUrl(), "failed asserting that $content is invalid");
        }
    }

    public function test_will_first_read_og_title_tag(): void
    {
        $title = implode(' ', $this->faker->sentences());

        $html = $this->html(<<<HTML
                <meta property="og:title" content="$title">
                <meta name="twitter:title" content="BitCoin is down">
                <title>Page Title</title>
        HTML);

        $this->reader->loadHTML($html, new Url($this->faker->url));

        $this->assertEquals($title, $this->reader->getPageTitle());
    }

    public function test_will_return_false_when_og_title_is_blank(): void
    {
        $html = $this->html(<<<HTML
                <meta property="og:title" content="  ">
                <title>Page Title</title>
        HTML);

        $this->reader->loadHTML($html, new Url($this->faker->url));

        $this->assertFalse($this->reader->getPageTitle());
    }

    public function test_will_read_title_tag_when_og_title_tag_Is_absent(): void
    {
        $title = $this->faker->title;

        $html = $this->html(<<<HTML
                <title>$title</title>
                <meta name="twitter:title" content="Why are crypto gurus silent :-)">
        HTML);

        $this->reader->loadHTML($html, new Url($this->faker->url));

        $this->assertEquals($title, $this->reader->getPageTitle());
    }

    public function test_will_return_false_when_title_tag_is_blank(): void
    {
        $html = $this->html(<<<HTML
                <title></title>
        HTML);

        $this->reader->loadHTML($html, new Url($this->faker->url));

        $this->assertFalse($this->reader->getPageTitle());
    }

    public function test_will_read_twitter_tag_when_no_title_tags_are_found(): void
    {
        $title = $this->faker->title;

        $html = $this->html(<<<HTML
                <meta name="twitter:title" content="$title">
        HTML);

        $this->reader->loadHTML($html, new Url($this->faker->url));

        $this->assertEquals($title, $this->reader->getPageTitle());
    }

    public function test_will_return_false_when_twitter_title_tag_is_blank(): void
    {
        $html = $this->html(<<<HTML
                <meta name="twitter:title" content="  ">
        HTML);

        $this->reader->loadHTML($html, new Url($this->faker->url));

        $this->assertFalse($this->reader->getPageTitle());
    }

    public function test_will_encode_title_tag_content_when_invalid(): void
    {
        $html = $this->html(<<<HTML
                <title><script>alert('hacked')</script></title>
        HTML);

        $this->reader->loadHTML($html, new Url($this->faker->url));

        $this->assertEquals('alert(&#039;hacked&#039;)', $this->reader->getPageTitle());
    }

    public function test_will_encode_twitter_title_tag_content_when_invalid(): void
    {
        $html = $this->html(<<<HTML
                <meta name="twitter:title" content=alert('hacked')>
        HTML);

        $this->reader->loadHTML($html, new Url($this->faker->url));

        $this->assertEquals('alert(&#039;hacked&#039;)', $this->reader->getPageTitle());
    }

    public function test_will_encode_og_title_when_invalid(): void
    {
        $html = $this->html(<<<HTML
                <meta property="og:title" content="<script>alert('hacked')</script>">
        HTML);

        $this->reader->loadHTML($html, new Url($this->faker->url));

        $this->assertEquals('&lt;script&gt;alert(&#039;hacked&#039;)&lt;/script&gt;', $this->reader->getPageTitle());
    }

    public function test_will_read_application_name_tag_first(): void
    {
        $html = $this->html(<<<HTML
                <meta property="og:site_name" content="PlayStation">
                <meta name="application-name" content="Xbox">
                <meta name="twitter:site" content="@USERNAME">
        HTML);

        $this->reader->loadHTML($html, new Url($this->faker->url));

        $this->assertEquals('Xbox', $this->reader->getSiteName());
    }

    public function test_will_read_og_site_name_when_no_application_name_tag(): void
    {
        $html = $this->html(<<<HTML
                <meta property="og:site_name" content="PlayStation">
                <meta name="twitter:site" content="@USERNAME">
        HTML);

        $this->reader->loadHTML($html, new Url($this->faker->url));

        $this->assertEquals('PlayStation', $this->reader->getSiteName());
    }

    public function test_will_read_twitter_tag_when_no_application_name_tags_are_found(): void
    {
        $html = $this->html(<<<HTML
                <meta name="twitter:site" content="@RickRoss">
        HTML);

        $this->reader->loadHTML($html, new Url($this->faker->url));

        $this->assertEquals('@RickRoss', $this->reader->getSiteName());
    }

    public function test_will_first_read_canonical_tag(): void
    {
        $url = 'https://www.foo.com/en/path/to/baz';

        $html = $this->html(<<<HTML
                <link rel="canonical" href="$url">
                <meta property="og:url" content="https://www.rottentomatoes.com/m/thor_love_and_thunder">
        HTML);

        $this->reader->loadHTML($html, new Url($url));

        $this->assertEquals(
            $url,
            $this->reader->getCanonicalUrl()->toString()
        );
    }

    public function test_will_return_false_when_canonical_tag_is_invalid(): void
    {
        foreach ([
            "<script> alert('hacked') </script>",
            "ldap://ds.example.com:389",
        ] as $content) {
            $html = $this->html(<<<HTML
                <link rel="canonical" href="$content">
            HTML);

            $this->reader->loadHTML($html, new Url($this->faker->url));

            $this->assertFalse($this->reader->getCanonicalUrl(), "failed asserting that $content is invalid");
        }
    }

    public function test_will_fallback_to_ogUrl_when_no_canonical_tag(): void
    {
        $url = 'https://www.foo.com/en/path/to/baz';

        $html = $this->html(<<<HTML
                <meta property="og:url" content="$url">
        HTML);

        $this->reader->loadHTML($html, new Url($url));

        $this->assertEquals(
            $url,
            $this->reader->getCanonicalUrl()->toString()
        );
    }

    public function test_will_return_false_when_og_url_is_invalid(): void
    {
        foreach ([
            "<script> alert('hacked') </script>",
            "ldap://ds.example.com:389",
        ] as $content) {
            $html = $this->html(<<<HTML
                <meta property="og:url" content="$content">
            HTML);

            $this->reader->loadHTML($html, new Url($this->faker->url));

            $this->assertFalse($this->reader->getCanonicalUrl(), "failed asserting that $content is invalid");
        }
    }
}
