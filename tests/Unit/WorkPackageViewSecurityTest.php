<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Models\Project;
use App\Models\WorkPackage;
use App\Support\Flash;
use App\Support\UrlGenerator;
use App\Support\View;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class WorkPackageViewSecurityTest extends TestCase
{
    protected function setUp():void{$_SESSION=[];}
    public function testUnauthorizedDetailModelAndHtmlContainNoNotesAndEscapeValues():void
    {
        $n=new DateTimeImmutable('2026-01-01');$p=new Project(1,'TEST','Test',null,null,null,null,null,null,1,null,null,'active',null,null,null,null,$n,$n);
        $wp=new WorkPackage(1,1,'<script>WP1</script>','Title',null,null,null,null,true,'private-work-package-note',$n,$n,'TEST','Test');
        $redacted=$wp->withoutNotes();self::assertNull($redacted->notes);
        $html=(new View(dirname(__DIR__,2).'/views',new UrlGenerator('https://example.test'),new Flash()))->render('work_packages/show',['title'=>'WP','project'=>$p,'workPackage'=>$redacted,'canViewNotes'=>false,'canManage'=>false,'csrfToken'=>'token']);
        self::assertStringNotContainsString('private-work-package-note',$html);self::assertStringNotContainsString('<script>WP1</script>',$html);self::assertStringContainsString('&lt;script&gt;WP1&lt;/script&gt;',$html);self::assertStringNotContainsString('>Edit<',$html);
    }
}
