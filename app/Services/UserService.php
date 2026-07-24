<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DuplicateEmailException;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Repositories\PersonRepository;
use App\Validation\UserValidator;
use App\Validation\PersonValidator;

final class UserService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserValidator $validator,
        private readonly AuthenticationService $authentication,
        private readonly PersonRepository $people,
        private readonly PersonValidator $personValidator,
    ) {
    }

    /** @param array<string, mixed> $input @return array<string, string> */
    public function validate(array $input, bool $passwordRequired, ?int $exceptId = null): array
    {
        $errors = $this->validator->validateUser($input, $passwordRequired);
        if ((string) ($input['role'] ?? '') === User::ROLE_ADMIN) {
            $existing = $exceptId === null ? null : $this->users->findById($exceptId);
            if ($existing === null || !$existing->isAdmin()) {
                $errors['role'] = 'Administrator cannot be assigned through user management.';
            }
        }
        $email = UserValidator::normalizeEmail((string) ($input['email'] ?? ''));
        $username = UserValidator::normalizeUsername((string) ($input['username'] ?? ''));
        if (!isset($errors['email']) && $this->users->emailExists($email, $exceptId)) {
            $errors['email'] = 'That email address is already in use.';
        }
        if (!isset($errors['username']) && $this->users->usernameExists($username, $exceptId)) {
            $errors['username'] = 'That username is already in use.';
        }
        if($exceptId===null){
            $selected=$this->personId($input['linked_person_id']??null);
            if(trim((string)($input['linked_person_id']??''))!==''&&$selected===null)$errors['linked_person_id']='Select a valid Person.';
            if($selected!==null){
                $person=$this->people->findById($selected);
                if($person===null)$errors['linked_person_id']='The selected Person no longer exists.';
                elseif($person->userId!==null)$errors['linked_person_id']='The selected Person is already linked to a User.';
            }else{
                $personData=$this->newPersonData($input);
                foreach($this->personValidator->validate($personData)as$field=>$message)$errors['person_'.$field]=$message;
                $personEmail=(string)$personData['institutional_email'];
                if(!isset($errors['person_institutional_email'])&&$this->people->emailExists($personEmail))$errors['linked_person_id']='A Person already uses this email. Select that Person explicitly if it is the same identity.';
            }
        }
        return $errors;
    }

    /** @param array<string, mixed> $input */
    public function create(array $input): User
    {
        if($this->validate($input,true)!==[])throw new \InvalidArgumentException('User information is invalid.');
        $userData=[
            'username' => UserValidator::normalizeUsername((string) $input['username']),
            'email' => UserValidator::normalizeEmail((string) $input['email']),
            'password_hash' => $this->authentication->hash((string) $input['password']),
            'first_name' => trim((string) $input['first_name']),
            'last_name' => trim((string) $input['last_name']),
            'role' => (string) $input['role'],
            'is_active' => isset($input['is_active']) && (string) $input['is_active'] === '1',
        ];
        $selected=$this->personId($input['linked_person_id']??null);
        return$this->users->createWithPerson($userData,$selected===null?$this->newPersonData($input):null,$selected);
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input, int $actingUserId): User
    {
        $data = [
            'username' => UserValidator::normalizeUsername((string) $input['username']),
            'email' => UserValidator::normalizeEmail((string) $input['email']),
            'first_name' => trim((string) $input['first_name']),
            'last_name' => trim((string) $input['last_name']),
            'role' => (string) $input['role'],
            'is_active' => isset($input['is_active']) && (string) $input['is_active'] === '1',
            'acting_user_id' => $actingUserId,
        ];
        if ((string) ($input['password'] ?? '') !== '') {
            $data['password_hash'] = $this->authentication->hash((string) $input['password']);
        }
        return $this->users->update($id, $data);
    }

    public function setActive(int $id, bool $active, int $actingUserId): User
    {
        return $this->users->setActive($id, $active, $actingUserId);
    }
    /** @param array<string,mixed>$input @return array<string,mixed> */
    private function newPersonData(array$input):array{return[
        'user_id'=>'','first_name'=>trim((string)($input['first_name']??'')),'last_name'=>trim((string)($input['last_name']??'')),
        'institutional_email'=>UserValidator::normalizeEmail((string)($input['email']??'')),'affiliation'=>'','position_type'=>'other',
        'is_internal'=>'0','active_from'=>'','active_to'=>'','is_active'=>(isset($input['is_active'])&&(string)$input['is_active']==='1')?'1':'0',
        'default_monthly_capacity_hours'=>'125.00','notes'=>'',
        'annual_capacity_hours'=>'1500.00',
    ];}
    private function personId(mixed$value):?int{$id=filter_var(trim((string)$value),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);return$id===false?null:(int)$id;}
}
