<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Person;
use App\Validation\PersonValidator;
use PHPUnit\Framework\TestCase;

final class PersonValidatorTest extends TestCase
{
    public function testEverySupportedPositionIsValidAndFacultyIsRejected(): void
    {
        $validator = new PersonValidator();
        foreach (Person::POSITION_LABELS as $position => $label) {
            self::assertSame([], $validator->validate($this->valid(['position_type' => $position])), $position);
        }
        self::assertArrayHasKey('position_type', $validator->validate($this->valid(['position_type' => 'faculty'])));
        self::assertSame('Full Professor', Person::POSITION_LABELS['full_professor']);
        self::assertSame('Associate Professor', Person::POSITION_LABELS['associate_professor']);
        self::assertSame('Assistant Professor', Person::POSITION_LABELS['assistant_professor']);
    }

    public function testRequiredLengthsEmailDatesBooleansAndNotes(): void
    {
        $validator = new PersonValidator();
        $errors = $validator->validate($this->valid([
            'first_name'=>'','last_name'=>'','institutional_email'=>'bad',
            'affiliation'=>str_repeat('a',256),'notes'=>str_repeat('n',2001),
            'is_internal'=>'yes','is_active'=>'yes','active_from'=>'2026-02-30','active_to'=>'bad',
        ]));
        foreach (['first_name','last_name','institutional_email','affiliation','notes','is_internal','is_active','active_from','active_to'] as $field) self::assertArrayHasKey($field,$errors);
        self::assertArrayHasKey('active_to', $validator->validate($this->valid(['active_from'=>'2026-02-02','active_to'=>'2026-02-01'])));
    }

    public function testOptionalFieldsMayBeEmpty(): void
    {
        self::assertSame([], (new PersonValidator())->validate($this->valid()));
    }
    public function testStandardMonthlyCapacity():void
    {
        $validator=new PersonValidator();foreach(['0','0.00','125','125.00','999999.99']as$value)self::assertArrayNotHasKey('default_monthly_capacity_hours',$validator->validate($this->valid(['default_monthly_capacity_hours'=>$value])));
        foreach(['-1','1.234','1e2','1000000','bad']as$value)self::assertArrayHasKey('default_monthly_capacity_hours',$validator->validate($this->valid(['default_monthly_capacity_hours'=>$value])));
    }

    /** @param array<string,string> $overrides @return array<string,string> */
    private function valid(array $overrides=[]): array
    {
        return $overrides + ['user_id'=>'','first_name'=>'Ada','last_name'=>'Lovelace','institutional_email'=>'','affiliation'=>'','position_type'=>'researcher','is_internal'=>'1','active_from'=>'','active_to'=>'','is_active'=>'1','default_monthly_capacity_hours'=>'125.00','annual_capacity_hours'=>'1150.00','notes'=>''];
    }
}
