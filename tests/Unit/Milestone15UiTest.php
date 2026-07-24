<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class Milestone15UiTest extends TestCase
{
    public function testProfileAndCapacityToolbarAreWired(): void
    {
        $root = dirname(__DIR__, 2);
        $bootstrap = (string) file_get_contents($root . '/bootstrap/app.php');
        $layout = (string) file_get_contents($root . '/views/layouts/app.php');
        $capacity = (string) file_get_contents($root . '/views/capacity/overview.php');
        $css = (string) file_get_contents($root . '/public/assets/css/app.css');

        self::assertStringContainsString("'/profile'", $bootstrap);
        self::assertStringContainsString('My Profile', $layout);
        self::assertStringContainsString('capacity-overview-actions', $capacity);
        self::assertStringContainsString('.capacity-overview-actions .btn{flex:0 0 auto;width:auto}', $css);
    }

    public function testPrimaryCapacityViewsDoNotExposeCompetingEffortLabels(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['overview.php', 'show.php', 'form.php', 'remove.php'] as $view) {
            $contents = (string) file_get_contents($root . '/views/capacity/' . $view);
            self::assertDoesNotMatchRegularExpression('/planned|actual/i', $contents, $view);
        }
    }
}
