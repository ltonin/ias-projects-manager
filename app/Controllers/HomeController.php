<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Csrf;
use App\Http\Response;
use App\Support\Config;
use App\Support\UrlGenerator;
use App\Support\View;

final class HomeController
{
    public function __construct(
        private readonly View $view,
        private readonly Config $config,
        private readonly Csrf $csrf,
        private readonly UrlGenerator $urls,
    ) {
    }

    public function index(): Response
    {
        return new Response($this->view->render('home/index', [
            'title' => 'Installation',
            'appName' => $this->config->requireString('app.name'),
            'environment' => $this->config->requireString('app.environment'),
            'csrfToken' => $this->csrf->token(),
            'csrfTestUrl' => $this->urls->to('/csrf-test'),
        ]));
    }
}
