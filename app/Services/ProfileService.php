<?php
declare(strict_types=1);
namespace App\Services;

use App\Exceptions\DuplicateEmailException;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Validation\UserValidator;

final class ProfileService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly AuthenticationService $authentication,
        private readonly int $passwordMinLength,
    ) {}

    public function validateEmail(User$user,array$input):array
    {
        $email=UserValidator::normalizeEmail((string)($input['email']??''));
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254)return['email'=>'Enter a valid email address.'];
        if($this->users->emailExists($email,$user->id))return['email'=>'That email address is already in use.'];
        return[];
    }
    public function updateEmail(User$user,array$input):User
    {
        $errors=$this->validateEmail($user,$input);if($errors!==[])throw new \InvalidArgumentException($errors['email']);
        return$this->users->updateEmail($user->id,UserValidator::normalizeEmail((string)$input['email']));
    }
    public function validatePassword(User$user,array$input):array
    {
        $errors=[];$current=(string)($input['current_password']??'');$new=(string)($input['new_password']??'');
        if(!password_verify($current,$user->passwordHash))$errors['current_password']='Current password is incorrect.';
        if(strlen($new)<$this->passwordMinLength)$errors['new_password']='New password must be at least '.$this->passwordMinLength.' characters.';
        elseif(strlen($new)>4096)$errors['new_password']='New password must not exceed 4096 characters.';
        if(!hash_equals($new,(string)($input['new_password_confirmation']??'')))$errors['new_password_confirmation']='Password confirmation does not match.';
        return$errors;
    }
    public function changePassword(User$user,array$input):void
    {
        $errors=$this->validatePassword($user,$input);if($errors!==[])throw new \InvalidArgumentException((string)reset($errors));
        $this->users->updatePasswordHash($user->id,$this->authentication->hash((string)$input['new_password']));
    }
}
