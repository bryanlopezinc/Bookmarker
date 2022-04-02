<?php

namespace Tests\Feature;

use App\DeviceDetector\DeviceType;
use App\Mail\NewLoginMail;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Database\Factories\ClientFactory;
use Tests\TestCase;

class LoginUserTest extends TestCase
{
    protected function getTestResponse(array $parameters = []): TestResponse
    {
        return $this->postJson(route('loginUser'), $parameters);
    }

    public function testWillReturnValidationErrorsWhenCredentialsAreInvalid(): void
    {
        $client = ClientFactory::new()->asPasswordClient()->create();

        $user = UserFactory::new()->create();

        $this->getTestResponse([
            'username'  => $user->username,
            'password'  => 'wrongPassword',
            'client_id' => $client->id,
            'client_secret' => $client->secret,
            'grant_type' => 'password'
        ])->assertStatus(400);

        $this->getTestResponse([
            'username'  =>  UserFactory::randomUsername(),
            'password'  => 'password',
            'client_id' => $client->id,
            'client_secret' => $client->secret,
            'grant_type' => 'password'
        ])->assertStatus(400);

        $this->getTestResponse([
            'username'  => $user->username,
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

        $client = ClientFactory::new()->asPasswordClient()->create();

        $user = UserFactory::new()->create();

        $this->getTestResponse([
            'username'  => $user->username,
            'password'  => 'password',
            'client_id' => $client->id,
            'client_secret' => $client->secret,
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

        Mail::assertSent(function (NewLoginMail $mail) use ($user) {
            $this->assertSame($user->email, $mail->to[0]['address']);
            $this->assertSame('Canada', $mail->loginInfo->location->country);
            $this->assertSame('Montreal', $mail->loginInfo->location->city);
            $this->assertSame('Macintosh', $mail->loginInfo->device->name);
            $this->assertSame(DeviceType::PC->value, $mail->loginInfo->device->type->value);

            return true;
        });
    }
}
