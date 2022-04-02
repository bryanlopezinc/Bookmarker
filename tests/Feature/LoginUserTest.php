<?php

namespace Tests\Feature;

use App\DeviceDetector\DeviceType;
use App\Mail\NewLoginMail;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Database\Factories\ClientFactory;
use Tests\TestCase;
use Laravel\Passport\Client;

class LoginUserTest extends TestCase
{
    private Client $client;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = ClientFactory::new()->asPasswordClient()->create();
        $this->user = UserFactory::new()->create();
    }

    protected function getTestResponse(array $parameters = []): TestResponse
    {
        return $this->postJson(route('loginUser'), $parameters);
    }

    public function testWillReturnValidationErrorsWhenCredentialsAreInvalid(): void
    {
        $this->getTestResponse([
            'username'  => $this->user->username,
            'password'  => 'wrongPassword',
            'client_id' => $this->client->id,
            'client_secret' => $this->client->secret,
            'grant_type' => 'password'
        ])->assertStatus(400);

        $this->getTestResponse([
            'username'  =>  UserFactory::randomUsername(),
            'password'  => 'password',
            'client_id' => $this->client->id,
            'client_secret' => $this->client->secret,
            'grant_type' => 'password'
        ])->assertStatus(400);

        $this->getTestResponse([
            'username'  => $this->user->username,
            'password'  => 'password',
        ])->assertStatus(400);
    }

    public function testWillLoginUser(): void
    {
        Mail::fake();

        Http::fake([
            'ip-api.com/*' => Http::response('{
                    "country": "Canada",
                    "city": "Montreal"
            }'),
        ]);

        $this->getTestResponse([
            'username'  => $this->user->username,
            'password'  => 'password',
            'client_id' => $this->client->id,
            'client_secret' => $this->client->secret,
            'grant_type' => 'password',
            'with_ip' => '24.48.0.1',
            'with_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.3 Safari/605.1.15'
        ])
            ->assertSuccessful()
            ->assertJsonCount(3, 'data')
            ->assertJsonCount(5, 'data.attributes')
            ->assertJsonCount(4, 'data.token')
            ->assertJsonStructure([
                'data' => [
                    'type',
                    'attributes' => [
                        'id',
                        'firstname',
                        'lastname',
                        'username',
                        'bookmarks_count'
                    ],
                    'token' => [
                        'token_type',
                        'expires_in',
                        'access_token',
                        'refresh_token'
                    ]
                ]
            ]);

        Mail::assertSent(function (NewLoginMail $mail) {
            $this->assertSame($this->user->email, $mail->to[0]['address']);
            $this->assertSame('Canada', $mail->loginInfo->location->country);
            $this->assertSame('Montreal', $mail->loginInfo->location->city);
            $this->assertSame('Macintosh', $mail->loginInfo->device->name);
            $this->assertSame(DeviceType::PC->value, $mail->loginInfo->device->type->value);

            return true;
        });
    }

    public function testAttributesMustbeFilledWhenPresent(): void
    {
        $this->getTestResponse([
            'with_ip' => '',
            'with_agent' => ''
        ])->assertUnprocessable()
            ->assertJsonValidationErrorFor('with_ip')
            ->assertJsonValidationErrorFor('with_agent');
    }

    public function testLocationWillBeUnknownWhenIpAttributeIsMissing(): void
    {
        Mail::fake();
        Http::fake();

        $this->getTestResponse([
            'username'  => $this->user->username,
            'password'  => 'password',
            'client_id' => $this->client->id,
            'client_secret' => $this->client->secret,
            'grant_type' => 'password',
            'with_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.3 Safari/605.1.15'
        ])
            ->assertSuccessful();

        Mail::assertSent(function (NewLoginMail $mail) {
            $this->assertTrue($mail->loginInfo->location->isUnknown());

            return true;
        });

        Http::assertNothingSent();
    }

    public function testDeviceWillBeUnknownWhenUserAgentAttributeIsMissing(): void
    {
        Mail::fake();

        $this->getTestResponse([
            'username'  => $this->user->username,
            'password'  => 'password',
            'client_id' => $this->client->id,
            'client_secret' => $this->client->secret,
            'grant_type' => 'password',
        ])->assertSuccessful();

        Mail::assertSent(function (NewLoginMail $mail) {
            $this->assertFalse($mail->loginInfo->device->nameIsKnown());
            $this->assertSame($mail->loginInfo->device->type->value, DeviceType::UNKNOWN->value);

            return true;
        });

        Http::assertNothingSent();
    }
}
