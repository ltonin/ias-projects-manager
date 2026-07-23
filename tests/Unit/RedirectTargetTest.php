<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\RedirectTarget;
use PHPUnit\Framework\TestCase;

final class RedirectTargetTest extends TestCase
{
    public function testAllowsInternalAndRejectsUnsafeTargets(): void
    {
        self::assertSame('/admin/users', RedirectTarget::sanitize('/admin/users'));
        self::assertSame('/', RedirectTarget::sanitize('https://evil.test'));
        self::assertSame('/', RedirectTarget::sanitize('//evil.test/path'));
        self::assertSame('/', RedirectTarget::sanitize('/safe\\evil'));
    }
}
