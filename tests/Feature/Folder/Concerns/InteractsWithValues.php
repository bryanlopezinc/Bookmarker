<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\Concerns;

use BackedEnum;
use Exception;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Assert as PHPUnit;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestSuite;
use Tests\TestCase;

trait InteractsWithValues
{
    private static int $remainingTestMethodsCount;
    private static array $interacted = [];
    private static array $shouldBeInteractedWith;

    #[Before]
    protected function setTraitProperties(): void
    {
        $testCaseExtendsLaravelTestCase = is_subclass_of($this, TestCase::class);

        $setUpFn = function () use ($testCaseExtendsLaravelTestCase) {
            if ( ! isset(self::$remainingTestMethodsCount)) {
                self::$remainingTestMethodsCount = TestSuite::fromClassName(__CLASS__)->count();
            }

            $shouldBeInteractedWith = $this->shouldBeInteractedWith();

            if (is_subclass_of($shouldBeInteractedWith, BackedEnum::class)) {
                $shouldBeInteractedWith = array_column($shouldBeInteractedWith::cases(), 'value');
            }

            foreach ($shouldBeInteractedWith as $value) {
                self::$shouldBeInteractedWith[$value] = true;
            }

            if ($testCaseExtendsLaravelTestCase) {
                Event::listen(function (RequestHandled $event) {
                    $this->setInteractedFromRequest($event);
                });
            }
        };

        if ($testCaseExtendsLaravelTestCase) {
            $this->afterApplicationCreated($setUpFn);
        } else {
            $setUpFn();
        }
    }

    protected function shouldBeInteractedWith(): mixed
    {
        throw new Exception(sprintf('method: %s has not been implemented.', __FUNCTION__));
    }

    protected function setInteractedFromRequest(RequestHandled $event): void
    {
        if ( ! $event->response->isSuccessful()) {
            return;
        }

        $event->request
            ->collect()
            ->merge($event->request->route()->parameters())
            ->filter()
            ->each(function ($value, string $key) {
                $this->setInteracted(Arr::flatten(Arr::wrap($value)));

                $this->setInteracted([$key]);
            });
    }

    #[After]
    protected function assertValuesWhereInteractedWith(): void
    {
        self::$remainingTestMethodsCount--;

        if (self::$remainingTestMethodsCount > 0) {
            return;
        }

        PHPUnit::assertNotEmpty($shouldBeInteractedWith = array_keys(self::$shouldBeInteractedWith));

        PHPUnit::assertEquals(
            array_diff($shouldBeInteractedWith, $this->getInteracted()),
            []
        );
    }

    protected function getInteracted(): array
    {
        return array_keys(self::$interacted);
    }

    protected function setInteracted(array $values): void
    {
        $missing = function ($value) {
            return array_key_exists($value, self::$shouldBeInteractedWith) && ! array_key_exists($value, self::$interacted);
        };

        foreach ($values as $key => $value) {
            if ($missing($key)) {
                self::$interacted[$key] = true;
            }

            if ( ! is_int($value) && ! is_string($value)) {
                continue;
            }

            if ($missing($value)) {
                self::$interacted[$value] = true;
            }
        }
    }
}
