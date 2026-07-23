<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\UserLinkOption;
use App\Services\PersonService;
use App\Validation\PersonValidator;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\InMemoryPersonRepository;

final class PersonServiceTest extends TestCase
{
    public function testCreatesUnlinkedAndLinkedPeopleIncludingInactiveUser(): void
    {
        $repository = new InMemoryPersonRepository([$this->user(1, false)]);
        $service = new PersonService($repository, new PersonValidator());
        $unlinked = $service->create($this->input());
        self::assertNull($unlinked->userId);
        $linkedInput = $this->input(['user_id'=>'1','institutional_email'=>'linked@example.test']);
        self::assertSame([], $service->validate($linkedInput));
        self::assertSame(1, $service->create($linkedInput)->userId);
    }

    public function testRejectsMissingOrAlreadyLinkedUserAndDuplicateNormalizedEmail(): void
    {
        $repository = new InMemoryPersonRepository([$this->user(1)]);
        $service = new PersonService($repository, new PersonValidator());
        $service->create($this->input(['user_id'=>'1','institutional_email'=>'Person@Example.test']));
        self::assertArrayHasKey('user_id', $service->validate($this->input(['user_id'=>'999'])));
        self::assertArrayHasKey('user_id', $service->validate($this->input(['user_id'=>'1'])));
        self::assertArrayHasKey('institutional_email', $service->validate($this->input(['institutional_email'=>'person@example.test'])));
    }

    public function testCanPreserveChangeAndRemoveLink(): void
    {
        $repository = new InMemoryPersonRepository([$this->user(1), $this->user(2)]);
        $service = new PersonService($repository, new PersonValidator());
        $person = $service->create($this->input(['user_id'=>'1']));
        self::assertSame([], $service->validate($this->input(['user_id'=>'1']), $person->id));
        self::assertSame(2, $service->update($person->id, $this->input(['user_id'=>'2']))->userId);
        self::assertNull($service->update($person->id, $this->input())->userId);
    }

    public function testPersonActivationIsIndependentOfUser(): void
    {
        $repository = new InMemoryPersonRepository([$this->user(1, false)]);
        $service = new PersonService($repository, new PersonValidator());
        $person = $service->create($this->input(['user_id'=>'1']));
        self::assertFalse($service->setActive($person->id, false)->isActive);
        self::assertFalse($repository->users[1]->isActive);
        self::assertTrue($service->setActive($person->id, true)->isActive);
        self::assertFalse($repository->users[1]->isActive);
    }

    public function testUnexpectedFieldsCannotMassAssignProtectedData(): void
    {
        $service = new PersonService($repository = new InMemoryPersonRepository(), new PersonValidator());
        $person = $service->create($this->input() + ['id'=>'999','created_at'=>'1900-01-01','password_hash'=>'forbidden']);
        self::assertSame(1, $person->id);
        self::assertSame('Test Person', $person->fullName());
    }

    public function testFiltersAndDeterministicPagination(): void
    {
        $repository = new InMemoryPersonRepository([$this->user(1)]);
        $service = new PersonService($repository, new PersonValidator());
        $service->create($this->input(['first_name'=>'Zed','last_name'=>'Alpha','affiliation'=>'Padua','position_type'=>'full_professor','user_id'=>'1']));
        $service->create($this->input(['first_name'=>'Amy','last_name'=>'Beta','institutional_email'=>'amy@example.test','is_internal'=>'0','is_active'=>'0']));
        foreach ([
            ['search'=>'Zed'],['search'=>'Alpha'],['search'=>'Padua'],['search'=>'test.user'],
            ['active'=>'inactive'],['internal'=>'external','active'=>'all'],['search'=>'amy@example.test','active'=>'all'],['position_type'=>'full_professor'],['linked'=>'linked'],
        ] as $query) self::assertSame(1, $service->listing($query)['page']->total);
        self::assertSame(2, $service->listing(['active'=>'all','per_page'=>'1','page'=>'2'])['page']->page);
        self::assertSame(1, $service->listing(['active'=>'all','page'=>'invalid'])['page']->page);
        self::assertSame(2, $service->listing(['active'=>'all','per_page'=>'1','page'=>'999'])['page']->page);
    }

    public function testEveryPositionCanBeFiltered(): void
    {
        $service = new PersonService($repository = new InMemoryPersonRepository(), new PersonValidator());
        foreach (\App\Models\Person::POSITION_LABELS as $position => $label) {
            $service->create($this->input(['first_name'=>$position,'institutional_email'=>$position.'@example.test','position_type'=>$position]));
            self::assertSame(1, $service->listing(['position_type'=>$position])['page']->total, $position);
        }
    }

    private function user(int $id, bool $active=true): UserLinkOption { return new UserLinkOption($id,'test.user'.$id,'Test','User','user'.$id.'@example.test','participant',$active); }
    /** @param array<string,string> $overrides @return array<string,string> */
    private function input(array $overrides=[]): array
    {
        return $overrides + ['user_id'=>'','first_name'=>'Test','last_name'=>'Person','institutional_email'=>'','affiliation'=>'','position_type'=>'researcher','is_internal'=>'1','active_from'=>'','active_to'=>'','is_active'=>'1','notes'=>''];
    }
}
