<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ExampleTest extends TestCase
{
    public function test_basic_arithmetic_is_correct(): void
    {
        $result = 2 + 2;

        $this->assertSame(4, $result);
    }
}
