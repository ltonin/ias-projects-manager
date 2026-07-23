<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Csrf;
use App\Http\Request;
use App\Http\Response;
use App\Support\Flash;
use App\Support\UrlGenerator;

final class CsrfTestController
{
    public function __construct(
        private readonly Request $request,
        private readonly Csrf $csrf,
        private readonly Flash $flash,
        private readonly UrlGenerator $urls,
    ) {
    }

    public function verify(): Response
    {
        $token = $this->request->post('_csrf');
        if (!is_string($token) || !$this->csrf->validate($token)) {
            return new Response('Invalid CSRF token.', 403, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }
        $this->flash->add('success', 'CSRF validation succeeded.');
        return Response::redirect($this->urls->to('/'));
    }
}
