<?php

namespace Tests\Feature;

use App\Jobs\UpdateBookmarkWithHttpResponse;
use App\Mail\EmailNotRegisteredMail;
use App\Models\Bookmark;
use Tests\TestCase;
use Illuminate\Support\Str;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;

class SaveBookFromMailTest extends TestCase
{
    use WithFaker;

    protected function getTestResponse(array $parameters = []): TestResponse
    {
        return $this->post(route('saveBookmarkFromEmail', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibeViaPath('v1/email/save_url', 'saveBookmarkFromEmail');
    }

    public function testWillThrowValidationExceptionWhenRequiredAttrbutesAreMissing(): void
    {
        $this->getTestResponse()->assertJsonValidationErrors(['email', 'rkv']);
        $this->getTestResponse(['email' => 'foo'])->assertJsonValidationErrors(['rkv']);
    }

    public function testInboundKeyMustBeValid(): void
    {
        $this->getTestResponse(['rkv' => 'foo'])->assertJsonValidationErrors(['rkv' => ['Invalid inbound key']]);
    }

    public function testSaveBookmark(): void
    {
        Bus::fake(UpdateBookmarkWithHttpResponse::class);

        $user = UserFactory::new()->create();

        $data = json_decode(associative: true, json: file_get_contents(base_path('tests/stubs/SendGrid/mail.json')));
        $data['email'] = Str::of(file_get_contents(base_path('tests/stubs/SendGrid/email.log')))
            ->replace(':sender', $user->email)
            ->replace('{url}', $this->faker->url)
            ->toString();
        $data['rkv'] = env('SENDGRID_INBOUND_KEY');

        $this->getTestResponse($data)->assertOk();

        $this->assertDatabaseHas(Bookmark::class, ['user_id' => $user->id]);
    }

    public function testCannotSaveBookmarkFromUnregisteredEmail(): void
    {
        Mail::fake();

        $data = json_decode(associative: true, json: file_get_contents(base_path('tests/stubs/SendGrid/mail.json')));
        $data['rkv'] = env('SENDGRID_INBOUND_KEY');

        $data['email'] = Str::of(file_get_contents(base_path('tests/stubs/SendGrid/email.log')))
            ->replace(':sender', $this->faker->unique()->email)
            ->replace('{url}', $this->faker->url)
            ->toString();

        $this->getTestResponse($data)->assertOk();

        Mail::assertSent(EmailNotRegisteredMail::class);
    }

    public function testWillNotSaveBookmarkWhenBookmarkIsInvalid(): void
    {
        $user = UserFactory::new()->create();

        $data = json_decode(associative: true, json: file_get_contents(base_path('tests/stubs/SendGrid/mail.json')));
        $data['email'] = Str::of(file_get_contents(base_path('tests/stubs/SendGrid/email.log')))
            ->replace(':sender', $user->email)
            ->replace('{url}', $this->faker->sentence)
            ->toString();
        $data['rkv'] = env('SENDGRID_INBOUND_KEY');

        $this->getTestResponse($data)->assertOk();

        $this->assertDatabaseMissing(Bookmark::class, ['user_id' => $user->id]);
    }
}