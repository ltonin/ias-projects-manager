<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Auth\AuthSession;
use App\Auth\SessionManager;
use App\Models\User;
use App\Services\AuthenticationService;
use App\Services\UserService;
use App\Validation\UserValidator;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\InMemoryUserRepository;
use Tests\Fakes\InMemoryPersonRepository;
use App\Validation\PersonValidator;
use Tests\Support\UserFactory;
use Tests\Support\PersonFactory;

final class UserServiceTest extends TestCase
{
    protected function setUp(): void { $_SESSION = []; }

    public function testDuplicateEmailProducesValidationError(): void
    {
        $service = $this->service(new InMemoryUserRepository([UserFactory::make()]));
        $errors = $service->validate($this->input('USER@example.test'), true);
        self::assertSame('That email address is already in use.', $errors['email']);
    }

    public function testExistingUserMayKeepOwnEmail(): void
    {
        $service = $this->service(new InMemoryUserRepository([UserFactory::make()]));
        self::assertSame([], $service->validate($this->input('USER@example.test'), true, 1));
    }

    public function testDuplicateUsernameIsCaseInsensitiveAndOwnUsernameMayBeKept(): void
    {
        $repository = new InMemoryUserRepository([UserFactory::make(username: 'existing.user')]);
        $service = $this->service($repository);
        $input = $this->input('new@example.test');
        $input['username'] = 'EXISTING.USER';
        self::assertSame('That username is already in use.', $service->validate($input, true)['username']);
        self::assertArrayNotHasKey('username', $service->validate($input, true, 1));
    }

    public function testNonexistentUserUpdateFailsSafely(): void
    {
        $this->expectException(\OutOfBoundsException::class);
        $this->service(new InMemoryUserRepository())->update(999, $this->input('new@example.test'), 1);
    }

    public function testCreateWithoutSelectionBuildsLinkedPersonDefaults():void
    {
        $users=new InMemoryUserRepository();$service=$this->service($users);$created=$service->create($this->input('new@example.test'));
        self::assertSame(1,$created->id);self::assertSame(1,$users->linkedCreateCount);
        self::assertSame('Test',$users->lastNewPersonData['first_name']);self::assertSame('User',$users->lastNewPersonData['last_name']);
        self::assertSame('new@example.test',$users->lastNewPersonData['institutional_email']);
        self::assertSame('other',$users->lastNewPersonData['position_type']);self::assertSame('0',$users->lastNewPersonData['is_internal']);
        self::assertSame('',$users->lastNewPersonData['active_from']);self::assertSame('',$users->lastNewPersonData['active_to']);
        self::assertSame('125.00',$users->lastNewPersonData['default_monthly_capacity_hours']);
    }

    public function testExplicitExistingPersonDoesNotRequestDuplicatePerson():void
    {
        $people=new InMemoryPersonRepository();$people->people[7]=PersonFactory::make(id:7,userId:null);
        $users=new InMemoryUserRepository();$input=$this->input('new@example.test');$input['linked_person_id']='7';
        $this->service($users,$people)->create($input);
        self::assertNull($users->lastNewPersonData);self::assertSame(7,$users->lastExistingPersonId);
    }

    public function testDuplicatePersonEmailRequiresExplicitSelectionAndCreatesNothing():void
    {
        $people=new InMemoryPersonRepository();$people->people[3]=PersonFactory::make(id:3,institutionalEmail:'new@example.test');
        $users=new InMemoryUserRepository();$service=$this->service($users,$people);$input=$this->input('new@example.test');
        self::assertArrayHasKey('linked_person_id',$service->validate($input,true));
        try{$service->create($input);self::fail('Expected validation failure.');}catch(\InvalidArgumentException){}
        self::assertCount(0,$users->users);self::assertSame(0,$users->linkedCreateCount);
    }

    public function testLinkFailureRollsBackUserCreation():void
    {
        $users=new InMemoryUserRepository();$users->beforeLinkedCreate=static function():void{throw new \RuntimeException('link failed');};
        try{$this->service($users)->create($this->input('new@example.test'));self::fail('Expected link failure.');}catch(\RuntimeException){}
        self::assertCount(0,$users->users);self::assertSame(0,$users->linkedCreateCount);self::assertNull($users->lastNewPersonData);
    }

    public function testUserUpdateDoesNotSynchronizePerson():void
    {
        $existing=UserFactory::make(role:User::ROLE_VIEWER);$people=new InMemoryPersonRepository();$people->people[4]=PersonFactory::make(id:4,userId:$existing->id,firstName:'Curated');
        $users=new InMemoryUserRepository([$existing]);$input=$this->input('changed@example.test');$input['first_name']='Changed';
        $this->service($users,$people)->update($existing->id,$input,99);
        self::assertSame('Curated',$people->people[4]->firstName);
    }

    private function service(InMemoryUserRepository $repository,?InMemoryPersonRepository$people=null): UserService
    {
        return new UserService($repository,new UserValidator(12),new AuthenticationService($repository,new AuthSession(new SessionManager(),1800,28800)),$people??new InMemoryPersonRepository(),new PersonValidator());
    }

    /** @return array<string, string> */
    private function input(string $email): array
    {
        return [
            'username' => 'new.user', 'email' => $email, 'first_name' => 'Test', 'last_name' => 'User',
            'role' => User::ROLE_VIEWER, 'is_active' => '1',
            'password' => 'a sufficiently long passphrase', 'password_confirmation' => 'a sufficiently long passphrase',
        ];
    }
}
