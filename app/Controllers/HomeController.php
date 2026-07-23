<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Authorization;
use App\Auth\CurrentPerson;
use App\Exceptions\HttpException;
use App\Models\PersonHourAllocation;
use App\Services\GlobalAnnualOverviewService;
use App\Http\Response;
use App\Support\Config;
use App\Support\UrlGenerator;
use App\Support\View;

final class HomeController
{
    public function __construct(
        private readonly View $view,
        private readonly Authorization $authorization,
        private readonly CurrentPerson $currentPerson,
        private readonly GlobalAnnualOverviewService $overview,
        private readonly \App\Http\Request $request,
    ) {
    }

    public function index(): Response
    {
        $user=$this->authorization->user();$raw=$this->request->query('year');
        $year=filter_var($raw,FILTER_VALIDATE_INT,['options'=>['min_range'=>PersonHourAllocation::MIN_YEAR,'max_range'=>PersonHourAllocation::MAX_YEAR]]);
        if($raw!==null&&$year===false)throw new HttpException(422,'Invalid overview year.');
        $page=$this->overview->page($user,$this->currentPerson->get()?->id,$year===false?(int)date('Y'):(int)$year);
        return new Response($this->view->render('home/index',['title'=>'Overview','page'=>$page]));
    }
}
