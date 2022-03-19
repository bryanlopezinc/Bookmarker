<?php

namespace Tests\Unit\Repositories;

use App\DataTransferObjects\Builders\UserBuilder;
use App\Repositories\CreateUserRepository;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class CreateUserRepositoryTest extends TestCase
{
    use WithFaker;

    public function testWillThrowExceptionIfPasswordIsNotHashed(): void
    {
        $this->expectExceptionMessage('User password must be hashed');

        $user = UserBuilder::new()->password($this->faker->password)->build();

        (new CreateUserRepository)->create($user);
    }
}
