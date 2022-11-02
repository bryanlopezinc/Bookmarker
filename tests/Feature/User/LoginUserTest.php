<?php

namespace Tests\Feature\User;

use App\DeviceDetector\DeviceType;
use App\Mail\NewLoginMail;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Database\Factories\ClientFactory;
use Tests\TestCase;
use Laravel\Passport\Client;
use Tests\Traits\Requests2FACode;

class LoginUserTest extends TestCase
{
    use Requests2FACode;

    private Client $client;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = ClientFactory::new()->asPasswordClient()->create();
        $this->user = UserFactory::new()->create();
    }

    protected function loginUserResponse(array $parameters = []): TestResponse
    {
        return $this->postJson(route('loginUser'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/login', 'loginUser');
    }

    public function testWillReturnValidationErrorsWhenCredentialsAreInvalid(): void
    {
        $data = [
            "error" => "invalid_grant",
            "error_description" => "The user credentials were incorrect.",
            "message" => "The user credentials were incorrect."
        ];

        //wrong password
        $this->loginUserResponse([
            'username'  => $this->user->username,
            'password'  => 'wrongPassword',
            'client_id' => $this->client->id,
            'client_secret' => $this->client->secret,
            'grant_type' => 'password',
            'two_fa_code' => '12345',
        ])->assertStatus(400)->assertExactJson($data);

        //username does not exists
        $this->loginUserResponse([
            'username'  =>  UserFactory::randomUsername(),
            'password'  => 'password',
            'client_id' => $this->client->id,
            'client_secret' => $this->client->secret,
            'grant_type' => 'password',
            'two_fa_code' => '12345',
        ])->assertStatus(400)->assertExactJson($data);

        //missing credentials
        $this->loginUserResponse([
            'username'  => $this->user->username,
            'password'  => 'password',
            'two_fa_code' => '12345',
        ])->assertStatus(400)->assertExactJson([
            "error" => "unsupported_grant_type",
            "error_description" => "The authorization grant type is not supported by the authorization server.",
            "message" => "The authorization grant type is not supported by the authorization server.",
            "hint" => "Check that all required parameters have been provided"
        ]);
    }

    public function testUsernameMustBeAnEmailOrUsername(): void
    {
        $this->loginUserResponse([
            'username'  => 'urhen#uh', //invalid username
            'password'  => 'password',
            'client_id' => $this->client->id,
            'client_secret' => $this->client->secret,
            'grant_type' => 'password',
            'two_fa_code' => '12345',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'username' => [
                    'The username must be a valid username or email'
                ]
            ]);

        $this->loginUserResponse([
            'username'  => 'bryanlopez.@yahoo.com',
            'password'  => 'password',
            'client_id' => $this->client->id,
            'client_secret' => $this->client->secret,
            'grant_type' => 'password',
            'two_fa_code' => '12345',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'username' => [
                    'The username must be a valid username or email'
                ]
            ]);
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

        $code = (string)$this->get2FACode($this->user->username, 'password');

        $this->loginUserResponse([
            'username'  => $this->user->username,
            'password'  => 'password',
            'client_id' => $this->client->id,
            'client_secret' => $this->client->secret,
            'grant_type' => 'password',
            'two_fa_code' => $code,
            'with_ip' => '24.48.0.1',
            'with_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.3 Safari/605.1.15'
        ])
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonCount(7, 'data.attributes')
            ->assertJsonCount(4, 'data.token')
            ->assertJson(function (AssertableJson $json) {
                $json->where('data.token.expires_in', function (int $expiresAt) {
                    $this->assertLessThanOrEqual(1, now()->diffInHours(now()->addSeconds($expiresAt)));

                    return true;
                });
                $json->etc();
            })
            ->assertJsonStructure([
                'data' => [
                    'type',
                    'attributes' => [
                        'firstname',
                        'lastname',
                        'username',
                        'bookmarks_count',
                        'favorites_count',
                        'folders_count',
                        'has_verified_email'
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

    public function testCanLoginWithEmail(): void
    {
        $email = $this->user->email;

        Http::fake([
            'ip-api.com/*' => Http::response('{
                    "country": "Canada",
                    "city": "Montreal"
            }'),
        ]);

        $code = (string)$this->get2FACode($email, 'password');

        $this->loginUserResponse([
            'username'  => $email,
            'password'  => 'password',
            'client_id' => $this->client->id,
            'client_secret' => $this->client->secret,
            'grant_type' => 'password',
            'two_fa_code' => $code,
            'with_ip' => '24.48.0.1',
            'with_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.3 Safari/605.1.15'
        ])->assertOk();
    }

    public function testCannotReuseVerificationCode(): void
    {
        $email = $this->user->email;

        Http::fake([
            'ip-api.com/*' => Http::response('{
                    "country": "Canada",
                    "city": "Montreal"
            }'),
        ]);

        $code = (string)$this->get2FACode($email, 'password');

        $this->loginUserResponse([
            'username'  => $email,
            'password'  => 'password',
            'client_id' => $this->client->id,
            'client_secret' => $this->client->secret,
            'grant_type' => 'password',
            'two_fa_code' => $code,
            'with_ip' => '24.48.0.1',
            'with_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.3 Safari/605.1.15'
        ])->assertOk();

        $this->loginUserResponse([
            'username'  => $email,
            'password'  => 'password',
            'client_id' => $this->client->id,
            'client_secret' => $this->client->secret,
            'grant_type' => 'password',
            'two_fa_code' => $code,
        ])->assertStatus(400)->assertExactJson([
            "error" => "invalidVerificationCode",
            "error_description" => "The given verification code is invalid.",
            "message" => "The given verification code is invalid.",
        ]);
    }

    public function testCannotUseCodeAfter_10_Minutes(): void
    {
        $email = $this->user->email;

        Http::fake([
            'ip-api.com/*' => Http::response('{
                    "country": "Canada",
                    "city": "Montreal"
            }'),
        ]);

        $code = (string)$this->get2FACode($email, 'password');

        $this->travel(11)->minutes(function () use ($code, $email) {
            $this->loginUserResponse([
                'username'  => $email,
                'password'  => 'password',
                'client_id' => $this->client->id,
                'client_secret' => $this->client->secret,
                'grant_type' => 'password',
                'two_fa_code' => $code,
            ])->assertStatus(400)->assertExactJson([
                "error" => "invalidVerificationCode",
                "error_description" => "The given verification code is invalid.",
                "message" => "The given verification code is invalid.",
            ]);
        });
    }

    public function testWillNotLoginUserWhenCodeIsInvalid(): void
    {
        $code = $this->get2FACode($this->user->username, 'password');

        //wrong code
        $this->loginUserResponse([
            'username'  => $this->user->username,
            'password'  => 'password',
            'client_id' => $this->client->id,
            'client_secret' => $this->client->secret,
            'grant_type' => 'password',
            'two_fa_code' => '12345',
        ])->assertStatus(400)->assertExactJson([
            "error" => "invalidVerificationCode",
            "error_description" => "The given verification code is invalid.",
            "message" => "The given verification code is invalid.",
        ]);

        //valid code but different user
        $this->loginUserResponse([
            'username'  => UserFactory::new()->create()->username,
            'password'  => 'password',
            'client_id' => $this->client->id,
            'client_secret' => $this->client->secret,
            'grant_type' => 'password',
            'two_fa_code' => (string) $code,
        ])->assertStatus(400)->assertExactJson([
            "error" => "invalidVerificationCode",
            "error_description" => "The given verification code is invalid.",
            "message" => "The given verification code is invalid.",
        ]);
    }

    public function testAttributesMustBeFilledWhenPresent(): void
    {
        $this->loginUserResponse([
            'with_ip' => '',
            'with_agent' => '',
            'two_fa_code' => ''
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['with_ip', 'with_agent', 'two_fa_code']);
    }

    public function testLocationWillBeUnknownWhenIpAttributeIsMissing(): void
    {
        $code = (string)$this->get2FACode($this->user->username, 'password');

        Mail::fake();
        Http::fake();

        $this->loginUserResponse([
            'username'  => $this->user->username,
            'password'  => 'password',
            'client_id' => $this->client->id,
            'client_secret' => $this->client->secret,
            'grant_type' => 'password',
            'two_fa_code' => $code,
            'with_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.3 Safari/605.1.15'
        ])->assertOk();

        Mail::assertSent(function (NewLoginMail $mail) {
            $this->assertTrue($mail->loginInfo->location->isUnknown());

            return true;
        });

        Http::assertNothingSent();
    }

    public function testDeviceWillBeUnknownWhenUserAgentAttributeIsMissing(): void
    {
        $code = (string)$this->get2FACode($this->user->username, 'password');

        Mail::fake();

        $this->loginUserResponse([
            'username'  => $this->user->username,
            'password'  => 'password',
            'client_id' => $this->client->id,
            'client_secret' => $this->client->secret,
            'grant_type' => 'password',
            'two_fa_code' => $code,
        ])->assertOk();

        Mail::assertSent(function (NewLoginMail $mail) {
            $this->assertFalse($mail->loginInfo->device->nameIsKnown());
            $this->assertSame($mail->loginInfo->device->type->value, DeviceType::UNKNOWN->value);

            return true;
        });

        Http::assertNothingSent();
    }

    public function testUserMustVerifyEmailBeforeLogin(): void
    {
        $user = UserFactory::new()->unverified()->create();

        $this->loginUserResponse([
            'username'  => $user->username,
            'password'  => 'password',
            'client_id' => $this->client->id,
            'client_secret' => $this->client->secret,
            'grant_type' => 'password',
            'two_fa_code' => '12345',
        ])->assertStatus(400)->assertExactJson([
            "error" => "userEmailNotVerified",
            "error_description" => "The user email has not been verified.",
            "message" => "The user email has not been verified.",
        ]);
    }
}
