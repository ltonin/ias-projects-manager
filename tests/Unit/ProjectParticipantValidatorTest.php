<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Person;
use App\Models\Project;
use App\Models\ProjectParticipant;
use App\Validation\ProjectParticipantValidator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ProjectParticipantValidatorTest extends TestCase
{
    public function testEverySupportedRoleAndNullDatesAreValid(): void
    {
        $validator = new ProjectParticipantValidator();
        foreach (ProjectParticipant::ROLE_LABELS as $role => $label) {
            self::assertSame([], $validator->validate($this->input(['project_role'=>$role]), $this->project(), $this->person()), $role);
        }
        self::assertCount(14, ProjectParticipant::ROLE_LABELS);
    }

    public function testInvalidRoleDatesAndNoteLength(): void
    {
        $validator = new ProjectParticipantValidator();
        self::assertArrayHasKey('project_role', $validator->validate($this->input(['project_role'=>'project_manager']), $this->project(), $this->person()));
        self::assertArrayHasKey('participation_start', $validator->validate($this->input(['participation_start'=>'bad']), $this->project(), $this->person()));
        self::assertArrayHasKey('participation_end', $validator->validate($this->input(['participation_start'=>'2026-06-01','participation_end'=>'2026-05-31']), $this->project(), $this->person()));
        self::assertArrayHasKey('notes', $validator->validate($this->input(['notes'=>str_repeat('n',2001)]), $this->project(), $this->person()));
    }

    public function testProjectAndPersonDateBoundaries(): void
    {
        $validator = new ProjectParticipantValidator();
        self::assertArrayHasKey('participation_start', $validator->validate($this->input(['participation_start'=>'2025-12-31']), $this->project(), $this->person()));
        self::assertArrayHasKey('participation_end', $validator->validate($this->input(['participation_end'=>'2029-01-01']), $this->project(), $this->person()));
        self::assertArrayHasKey('participation_start', $validator->validate($this->input(['participation_start'=>'2026-01-15']), $this->project(), $this->person('2026-02-01',null)));
        self::assertArrayHasKey('participation_end', $validator->validate($this->input(['participation_end'=>'2028-11-01']), $this->project(), $this->person(null,'2028-10-31')));
        self::assertSame([], $validator->validate($this->input(['participation_start'=>'2026-03-01','participation_end'=>'2027-06-30']), $this->project(), $this->person('2025-10-01',null)));
    }

    public function testUnknownBoundariesOnlyValidateKnownDates(): void
    {
        $validator = new ProjectParticipantValidator();
        self::assertSame([], $validator->validate($this->input(['participation_start'=>'2020-01-01','participation_end'=>'2030-01-01']), $this->project(null,null), $this->person(null,null)));
        self::assertArrayHasKey('participation_start', $validator->validate($this->input(['participation_start'=>'2025-01-01']), $this->project('2026-01-01',null), $this->person(null,null)));
        self::assertArrayHasKey('participation_end', $validator->validate($this->input(['participation_end'=>'2029-01-01']), $this->project(null,'2028-01-01'), $this->person(null,null)));
    }

    /** @param array<string,string> $overrides @return array<string,string> */
    private function input(array $overrides=[]): array { return $overrides+['project_role'=>'researcher','participation_start'=>'','participation_end'=>'','is_active'=>'1','notes'=>'']; }
    private function project(?string $start='2026-01-01', ?string $end='2028-12-31'): Project
    {
        $now=new DateTimeImmutable('2026-01-01');
        return new Project(1,'TEST','Test',null,null,null,null,null,null,1,$start===null?null:new DateTimeImmutable($start),$end===null?null:new DateTimeImmutable($end),'active',null,null,null,null,$now,$now);
    }
    private function person(?string $from=null, ?string $to=null): Person
    {
        $now=new DateTimeImmutable('2026-01-01');
        return new Person(1,null,'Ada','Lovelace','ada@example.test','University','researcher',true,$from===null?null:new DateTimeImmutable($from),$to===null?null:new DateTimeImmutable($to),true,null,$now,$now);
    }
}
