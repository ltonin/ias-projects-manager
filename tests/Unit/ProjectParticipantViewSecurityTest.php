<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Project;
use App\Models\ProjectParticipant;
use App\Models\ProjectParticipantPage;
use App\Support\Flash;
use App\Support\UrlGenerator;
use App\Support\View;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ProjectParticipantViewSecurityTest extends TestCase
{
    protected function setUp(): void { $_SESSION = []; }

    public function testUnauthorizedDetailAndListsDoNotRenderNotesAndEscapeDisplayValues(): void
    {
        $secret = 'never-deliver-participant-note';
        $participant = $this->participant($secret);
        $safe = $participant->withoutNotes();
        $view = new View(dirname(__DIR__, 2) . '/views', new UrlGenerator('https://example.test'), new Flash());
        $project = $this->project();
        $detail = $view->render('project_participants/show', [
            'title'=>'Participant','project'=>$project,'participant'=>$safe,'canManage'=>false,'canViewNotes'=>false,'csrfToken'=>'token',
        ]);
        $list = $view->render('project_participants/index', [
            'title'=>'Participants','project'=>$project,'page'=>new ProjectParticipantPage([$safe],1,1,25),
            'filters'=>['search'=>'','active'=>'all','project_role'=>'','internal'=>'all','person_active'=>'all'],
            'roleLabels'=>ProjectParticipant::ROLE_LABELS,'canManage'=>false,'csrfToken'=>'token',
        ]);

        self::assertStringNotContainsString($secret, $detail . $list);
        self::assertStringNotContainsString('Internal notes', $detail);
        self::assertStringContainsString('&lt;script&gt;Ada&lt;/script&gt;', $detail);
        self::assertStringNotContainsString('<script>Ada</script>', $detail . $list);
    }

    private function participant(string $notes): ProjectParticipant
    {
        $now = new DateTimeImmutable('2026-01-01');
        return new ProjectParticipant(1,1,2,'researcher',null,null,true,$notes,$now,$now,'<script>Ada</script>','Lovelace','ada@example.test','University','researcher',true,true,null,null,'ada.user',true,'TEST','Test project','active');
    }
    private function project(): Project
    {
        $now = new DateTimeImmutable('2026-01-01');
        return new Project(1,'TEST','Test project',null,null,null,null,null,null,5,null,null,'active',null,null,null,null,$now,$now);
    }
}
