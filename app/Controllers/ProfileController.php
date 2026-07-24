<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Auth\Authorization;
use App\Auth\Csrf;
use App\Exceptions\DuplicateEmailException;
use App\Http\Request;
use App\Http\Response;
use App\Repositories\PersonRepository;
use App\Services\ProfileService;
use App\Support\Flash;
use App\Support\UrlGenerator;
use App\Support\View;

final class ProfileController
{
    public function __construct(private readonly Request$request,private readonly View$view,private readonly Authorization$authorization,private readonly PersonRepository$people,private readonly ProfileService$service,private readonly Csrf$csrf,private readonly Flash$flash,private readonly UrlGenerator$urls){}
    public function show():Response
    {
        $user=$this->authorization->user();
        return$this->render($user,[],['email'=>$user->email],[]);
    }
    public function updateEmail():Response
    {
        $user=$this->authorization->user();$this->csrf();$input=$this->request->postData();$errors=$this->service->validateEmail($user,$input);
        if($errors!==[])return$this->render($user,$errors,$input,[],422);
        try{$this->service->updateEmail($user,$input);}catch(DuplicateEmailException){return$this->render($user,['email'=>'That email address is already in use.'],$input,[],422);}
        $this->flash->add('success','Email updated.');return Response::redirect($this->urls->to('/profile'));
    }
    public function changePassword():Response
    {
        $user=$this->authorization->user();$this->csrf();$input=$this->request->postData();$errors=$this->service->validatePassword($user,$input);
        if($errors!==[])return$this->render($user,[],['email'=>$user->email],$errors,422);
        $this->service->changePassword($user,$input);$this->flash->add('success','Password changed.');return Response::redirect($this->urls->to('/profile'));
    }
    private function render($user,array$emailErrors,array$emailValues,array$passwordErrors,int$status=200):Response{return new Response($this->view->render('profile/show',['title'=>'My Profile','user'=>$user,'person'=>$this->people->findByUserId($user->id),'emailErrors'=>$emailErrors,'emailValues'=>$emailValues,'passwordErrors'=>$passwordErrors,'csrfToken'=>$this->csrf->token()]),$status);}
    private function csrf():void{$token=$this->request->post('_csrf');if(!is_string($token)||!$this->csrf->validate($token))throw new \App\Exceptions\HttpException(403,'Invalid CSRF token.');}
}
