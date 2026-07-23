<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\Person;
use App\Repositories\PersonRepository;

final class CurrentPerson
{
    private bool $loaded=false;
    private ?Person $person=null;
    public function __construct(private readonly CurrentUser $currentUser,private readonly PersonRepository $people){}
    public function get():?Person
    {
        if($this->loaded)return $this->person;
        $this->loaded=true;$user=$this->currentUser->get();
        return $this->person=$user===null?null:$this->people->findByUserId($user->id);
    }
}
