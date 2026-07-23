<?php
declare(strict_types=1);
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class Milestone11Point1LayoutTest extends TestCase
{
    private string$css;
    protected function setUp():void{$this->css=(string)file_get_contents(dirname(__DIR__,2).'/public/assets/css/app.css');}
    public function testAnnualGridUsesSharedResponsiveGeometry():void
    {
        self::assertStringContainsString('.effort-table {',$this->css);
        self::assertStringContainsString('table-layout: fixed',$this->css);
        self::assertStringContainsString('width: 100%',$this->css);
        self::assertStringContainsString('--effort-hierarchy-width: 15rem',$this->css);
        self::assertStringContainsString('--effort-annual-width: 5.5rem',$this->css);
        self::assertStringNotContainsString('width: 106rem',$this->css);
        self::assertStringNotContainsString('min-width: 106rem',$this->css);
    }
    public function testInputsAndShellCannotImposeDesktopOverflow():void
    {
        self::assertMatchesRegularExpression('/\\.effort-cell\\s*\\{[^}]*width:\\s*100%[^}]*min-width:\\s*0[^}]*max-width:\\s*100%/s',$this->css);
        self::assertMatchesRegularExpression('/\\.app-shell\\s*\\{[^}]*width:100%/s',$this->css);
        self::assertMatchesRegularExpression('/\\.app-main\\s*\\{[^}]*flex:1 1 auto[^}]*min-width:0[^}]*max-width:none/s',$this->css);
        self::assertStringNotContainsString('.app-main{width:100vw',$this->css);
    }
    public function testSidebarDesktopBackgroundOverridesResponsiveOffcanvasTransparency():void
    {
        self::assertStringContainsString('background-color:var(--sidebar-bg)!important',$this->css);
        self::assertStringContainsString('.sidebar-link:focus-visible',$this->css);
        self::assertStringContainsString('outline:3px solid var(--sidebar-focus)',$this->css);
        self::assertGreaterThanOrEqual(4.5,$this->contrast('#f1f5f9','#15324b'));
        self::assertGreaterThanOrEqual(4.5,$this->contrast('#c7d3dc','#15324b'));
        self::assertGreaterThanOrEqual(4.5,$this->contrast('#ffffff','#5c000c'));
        self::assertGreaterThanOrEqual(4.5,$this->contrast('#52606d','#ffffff'));
    }
    public function testInstitutionalThemeAndCompactHeaderAreCentralized():void
    {
        $layout=(string)file_get_contents(dirname(__DIR__,2).'/views/layouts/app.php');
        self::assertStringContainsString('--unipd-burgundy:#9b0014',$this->css);
        self::assertStringContainsString('--sidebar-active-bg:#5c000c',$this->css);
        self::assertStringContainsString('background:var(--sidebar-active-bg)',$this->css);
        self::assertStringNotContainsString('background:#0d6efd',$this->css);
        self::assertStringContainsString('<header class="app-header">',$layout);
        self::assertStringContainsString('IASLab Projects Manager',$layout);
        self::assertStringContainsString("asset('img/iaslab-logo.svg')",$layout);
        self::assertStringContainsString('alt="IASLab"',$layout);
        self::assertFileExists(dirname(__DIR__,2).'/public/assets/img/iaslab-logo.svg');
    }
    public function testSidebarActiveStateHasNoWhiteStripAndReadableHierarchy():void
    {
        self::assertStringNotContainsString('--sidebar-active-border',$this->css);
        self::assertMatchesRegularExpression('/\\.sidebar-link\\.active[^}]*border-left:0[^}]*box-shadow:none/s',$this->css);
        self::assertStringContainsString('--sidebar-active-text:#ffffff',$this->css);
        self::assertMatchesRegularExpression('/\\.sidebar-link\\{[^}]*font-size:\\.96rem/s',$this->css);
        self::assertMatchesRegularExpression('/\\.sidebar-section-heading\\{[^}]*font-size:\\.82rem[^}]*letter-spacing:\\.06em/s',$this->css);
    }
    public function testDesktopRailUsesRealWidthAndMobileKeepsExpandedOffcanvas():void
    {
        self::assertStringContainsString('--sidebar-expanded-width:248px',$this->css);
        self::assertStringContainsString('--sidebar-collapsed-width:56px',$this->css);
        self::assertStringContainsString('flex-basis:var(--sidebar-collapsed-width)',$this->css);
        self::assertStringContainsString('transition:width .2s ease,flex-basis .2s ease',$this->css);
        self::assertMatchesRegularExpression('/@media \\(max-width:767\\.98px\\)\\{\\.app-sidebar\\{width:var\\(--sidebar-expanded-width\\);flex-basis:var\\(--sidebar-expanded-width\\)/',$this->css);
        self::assertStringNotContainsString('width:100vw',$this->css);
    }
    public function testHeaderIdentityIsProminentWithoutBecomingTall():void
    {
        self::assertMatchesRegularExpression('/\\.app-header\\{[^}]*flex:0 0 64px/s',$this->css);
        self::assertMatchesRegularExpression('/\\.app-logo\\{[^}]*width:42px[^}]*height:42px/s',$this->css);
        self::assertMatchesRegularExpression('/\\.app-title\\{[^}]*font-size:1\\.28rem[^}]*font-weight:700[^}]*line-height:1\\.2/s',$this->css);
        self::assertStringContainsString('align-items:center;gap:.7rem',$this->css);
    }
    private function contrast(string$a,string$b):float
    {
        $luminance=static function(string$hex):float{$values=[];foreach([1,3,5]as$offset){$value=hexdec(substr($hex,$offset,2))/255;$values[]=$value<=.04045?$value/12.92:(($value+.055)/1.055)**2.4;}return.2126*$values[0]+.7152*$values[1]+.0722*$values[2];};
        $one=$luminance($a);$two=$luminance($b);return(max($one,$two)+.05)/(min($one,$two)+.05);
    }
}
