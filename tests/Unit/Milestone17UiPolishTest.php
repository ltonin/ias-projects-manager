<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class Milestone17UiPolishTest extends TestCase
{
    public function testSharedVisualLanguageDefinesLightTablesToolbarsAndKeyboardFocus(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2).'/public/assets/css/app.css');
        self::assertStringContainsString('Milestone 17: shared low-noise visual language', $css);
        self::assertStringContainsString('.filter-toolbar', $css);
        self::assertStringContainsString('.table>thead>tr>th', $css);
        self::assertStringContainsString('background:#f7f8f9', $css);
        self::assertStringContainsString('a:focus-visible,button:focus-visible', $css);
        self::assertStringContainsString('.app-sidebar{color:#fff!important;background:var(--sidebar-bg)!important', $css);
        self::assertStringContainsString('.table-actions{width:1%', $css);
    }

    public function testManagementPagesUseLightFiltersAndContextualActions(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['views/projects/index.php','views/admin/people/index.php','views/project_participants/index.php','views/person_hour_allocations/index.php'] as $file) {
            self::assertStringContainsString('filter-toolbar', (string) file_get_contents($root.'/'.$file), $file);
        }
        foreach (['views/admin/people/index.php','views/admin/users/index.php'] as $file) {
            $view = (string) file_get_contents($root.'/'.$file);
            self::assertStringContainsString('table-actions', $view, $file);
            self::assertStringContainsString('dropdown-toggle', $view, $file);
            self::assertStringContainsString('aria-label="Actions for', $view, $file);
        }
    }

    public function testInactiveRolesArePlainTextWhileMeaningfulStatesRemainBadges(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['views/admin/people/index.php','views/admin/users/index.php','views/project_participants/index.php'] as $file) {
            $view = (string) file_get_contents($root.'/'.$file);
            self::assertStringContainsString('<span class="text-secondary">Inactive</span>', $view);
            self::assertStringContainsString('badge text-bg-success', $view);
        }
    }
}
