<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\View;
use PHPUnit\Framework\TestCase;

final class ViewTest extends TestCase
{
    public function testEscapesHtml(): void
    {
        self::assertSame('&lt;script&gt;&quot;x&quot;&lt;/script&gt;', View::escape('<script>"x"</script>'));
    }
}
