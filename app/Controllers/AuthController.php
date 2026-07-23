<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Csrf;
use App\Auth\CurrentUser;
use App\Http\Request;
use App\Http\Response;
use App\Services\AuthenticationService;
use App\Support\Flash;
use App\Support\RedirectTarget;
use App\Support\UrlGenerator;
use App\Support\View;
use App\Validation\UserValidator;

final class AuthController
{
    private const LOGIN_ERROR = 'The email or username, password, or account status is invalid.';

    public function __construct(
        private readonly Request $request,
        private readonly View $view,
        private readonly CurrentUser $currentUser,
        private readonly AuthenticationService $authentication,
        private readonly UserValidator $validator,
        private readonly Csrf $csrf,
        private readonly Flash $flash,
        private readonly UrlGenerator $urls,
    ) {
    }

    public function loginForm(): Response
    {
        if ($this->currentUser->get() !== null) {
            return Response::redirect($this->urls->to('/'));
        }
        return new Response($this->view->render('auth/login', [
            'title' => 'Login',
            'errors' => [],
            'identifier' => '',
            'redirect' => RedirectTarget::sanitize($this->request->query('redirect')),
            'csrfToken' => $this->csrf->token(),
        ]));
    }

    public function login(): Response
    {
        if (!$this->csrf->validate(is_string($this->request->post('_csrf')) ? $this->request->post('_csrf') : null)) {
            return new Response('Invalid CSRF token.', 403, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }
        $input = $this->request->postData();
        $errors = $this->validator->validateLogin($input);
        if ($errors === [] && !$this->authentication->attempt((string) $input['identifier'], (string) $input['password'])) {
            $errors['credentials'] = self::LOGIN_ERROR;
        }
        if ($errors !== []) {
            return new Response($this->view->render('auth/login', [
                'title' => 'Login',
                'errors' => $errors,
                'identifier' => UserValidator::normalizeLoginIdentifier((string) ($input['identifier'] ?? '')),
                'redirect' => RedirectTarget::sanitize($input['redirect'] ?? null),
                'csrfToken' => $this->csrf->token(),
            ]), 422);
        }
        $this->csrf->regenerate();
        $this->flash->add('success', 'You are now signed in.');
        return Response::redirect($this->urls->to(RedirectTarget::sanitize($input['redirect'] ?? null)));
    }

    public function logout(): Response
    {
        if (!$this->csrf->validate(is_string($this->request->post('_csrf')) ? $this->request->post('_csrf') : null)) {
            return new Response('Invalid CSRF token.', 403, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }
        $this->authentication->logout();
        $this->csrf->regenerate();
        $this->currentUser->clear();
        $this->flash->add('success', 'You have been signed out.');
        return Response::redirect($this->urls->to('/login'));
    }
}
