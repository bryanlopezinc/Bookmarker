<?php

namespace Tests\Unit\DataTransferObjects\Validator;

use App\DataTransferObjects\Validator\ExecuteAfterSetUpClassAttributes;
use App\DataTransferObjects\Validator\Reflector;
use Tests\TestCase;

class ExecuteAfterSetUpClassAttributesTest extends TestCase
{
    public function testWillCacheClassAttributes(): void
    {
        $reflector = $this->getMockBuilder(Reflector::class)->getMock();
        $reflector->expects($this->once())->method('getClassAttributesInstances')->willReturn($this->getValidators());

        foreach ([1, 2] as $times) {
            for ($i = 0; $i < 10; $i++) {
                (new ExecuteAfterSetUpClassAttributes(new AnonymousClass, $reflector))->execute();
            }
        }

        $this->assertEquals(ExecuteAfterSetUpClassAttributes::getCache()[AnonymousClass::class], $this->getValidators());
        $this->assertEquals(20, TestValidator::$invocationCount);
        $this->assertEquals(20, TestValidator2::$invocationCount);
    }

    private function getValidators(): array
    {
        return [
            new TestValidator,
            new TestValidator2,
        ];
    }
}
