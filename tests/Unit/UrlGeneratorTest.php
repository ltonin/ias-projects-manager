<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\UrlGenerator;
use PHPUnit\Framework\TestCase;

final class UrlGeneratorTest extends TestCase
{
    public function testGeneratesRootUrl(): void
    {
        self::assertSame('https://example.test/projects', (new UrlGenerator('https://example.test'))->to('/projects'));
    }

    public function testGeneratesSubdirectoryUrl(): void
    {
        self::assertSame('https://example.test/research/projects', (new UrlGenerator('https://example.test', '/research'))->to('/projects'));
    }

    public function testGeneratesQueryStringFallback(): void
    {
        self::assertSame('https://example.test/research/index.php?route=projects', (new UrlGenerator('https://example.test', 'research', false))->to('/projects'));
    }
}
