<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\SecondaryEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\QueryException;

class UniqueEmailTest extends TestCase
{
    public function testWillThrowExceptionWhenNewUserEmailExistInSecondaryEmailsTable(): void
    {
        $userWasCreatedSuccessfully = true;
        $secondaryEmail = UserFactory::new()->make()->email;

        SecondaryEmail::query()->create([
            'user_id' => UserFactory::new()->create()->id,
            'email' => $secondaryEmail,
            'verified_at' => now()
        ]);

        try {
            UserFactory::new()->create(['email' => $secondaryEmail]);
        } catch (QueryException $e) {
            $userWasCreatedSuccessfully = false;
            $this->assertStringContainsString('Email must be unique', $e->getMessage());
        }

        $this->assertFalse($userWasCreatedSuccessfully);
    }

    public function testWillThrowExceptionWhenNewSecondaryEmailExistInUsersTable(): void
    {
        $emailWasCreatedSuccessfully = true;
        $newUserEmail = UserFactory::new()->create()->email;

        try {
            SecondaryEmail::query()->create([
                'user_id' => UserFactory::new()->create()->id,
                'email' => $newUserEmail,
                'verified_at' => now()
            ]);
        } catch (QueryException $e) {
            $emailWasCreatedSuccessfully = false;
            $this->assertStringContainsString('Email must be unique', $e->getMessage());
        }

        $this->assertFalse($emailWasCreatedSuccessfully);
    }
}
