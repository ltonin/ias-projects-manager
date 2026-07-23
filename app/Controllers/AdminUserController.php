<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Authorization;
use App\Auth\Csrf;
use App\Exceptions\AdminSafetyException;
use App\Exceptions\DuplicateEmailException;
use App\Exceptions\DuplicateUsernameException;
use App\Exceptions\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\UserService;
use App\Support\Flash;
use App\Support\UrlGenerator;
use App\Support\View;

final class AdminUserController
{
    public function __construct(
        private readonly Request $request,
        private readonly View $view,
        private readonly Authorization $authorization,
        private readonly UserRepository $users,
        private readonly UserService $service,
        private readonly Csrf $csrf,
        private readonly Flash $flash,
        private readonly UrlGenerator $urls,
    ) {
    }

    public function index(): Response
    {
        $this->authorization->admin();
        return new Response($this->view->render('admin/users/index', [
            'title' => 'Users',
            'users' => $this->users->all(),
            'csrfToken' => $this->csrf->token(),
        ]));
    }

    public function createForm(): Response
    {
        $this->authorization->admin();
        return $this->form('Create user', 'create', null, [], $this->emptyValues());
    }

    public function create(): Response
    {
        $this->authorization->admin();
        $this->requireCsrf();
        $input = $this->request->postData();
        $errors = $this->service->validate($input, true);
        if ($errors !== []) {
            return $this->form('Create user', 'create', null, $errors, $input, 422);
        }
        try {
            $this->service->create($input);
        } catch (DuplicateEmailException) {
            return $this->form('Create user', 'create', null, ['email' => 'That email address is already in use.'], $input, 422);
        } catch (DuplicateUsernameException) {
            return $this->form('Create user', 'create', null, ['username' => 'That username is already in use.'], $input, 422);
        }
        $this->flash->add('success', 'User created.');
        return Response::redirect($this->urls->to('/admin/users'));
    }

    /** @param array<string, string> $parameters */
    public function editForm(array $parameters): Response
    {
        $this->authorization->admin();
        $user = $this->findUser($parameters);
        return $this->form('Edit user', 'edit', $user, [], [
            'username' => $user->username,
            'first_name' => $user->firstName,
            'last_name' => $user->lastName,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => $user->isActive ? '1' : '0',
        ]);
    }

    /** @param array<string, string> $parameters */
    public function update(array $parameters): Response
    {
        $actor = $this->authorization->admin();
        $this->requireCsrf();
        $user = $this->findUser($parameters);
        $input = $this->request->postData();
        $errors = $this->service->validate($input, false, $user->id);
        if ($errors !== []) {
            return $this->form('Edit user', 'edit', $user, $errors, $input, 422);
        }
        try {
            $this->service->update($user->id, $input, $actor->id);
        } catch (DuplicateEmailException) {
            return $this->form('Edit user', 'edit', $user, ['email' => 'That email address is already in use.'], $input, 422);
        } catch (DuplicateUsernameException) {
            return $this->form('Edit user', 'edit', $user, ['username' => 'That username is already in use.'], $input, 422);
        } catch (AdminSafetyException $exception) {
            return $this->form('Edit user', 'edit', $user, ['safety' => $exception->getMessage()], $input, 422);
        }
        $this->flash->add('success', 'User updated.');
        return Response::redirect($this->urls->to('/admin/users'));
    }

    /** @param array<string, string> $parameters */
    public function activate(array $parameters): Response
    {
        return $this->changeActive($parameters, true);
    }

    /** @param array<string, string> $parameters */
    public function deactivate(array $parameters): Response
    {
        return $this->changeActive($parameters, false);
    }

    /** @param array<string, string> $parameters */
    private function changeActive(array $parameters, bool $active): Response
    {
        $actor = $this->authorization->admin();
        $this->requireCsrf();
        $user = $this->findUser($parameters);
        try {
            $this->service->setActive($user->id, $active, $actor->id);
            $this->flash->add('success', $active ? 'User activated.' : 'User deactivated.');
        } catch (AdminSafetyException $exception) {
            $this->flash->add('danger', $exception->getMessage());
        }
        return Response::redirect($this->urls->to('/admin/users'));
    }

    /** @param array<string, string> $parameters */
    private function findUser(array $parameters): User
    {
        $id = filter_var($parameters['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new HttpException(404, 'User not found.');
        }
        return $this->users->findById((int) $id) ?? throw new HttpException(404, 'User not found.');
    }

    private function requireCsrf(): void
    {
        $token = $this->request->post('_csrf');
        if (!is_string($token) || !$this->csrf->validate($token)) {
            throw new HttpException(403, 'Invalid CSRF token.');
        }
    }

    /** @param array<string, string> $errors @param array<string, mixed> $values */
    private function form(string $title, string $mode, ?User $user, array $errors, array $values, int $status = 200): Response
    {
        return new Response($this->view->render('admin/users/form', [
            'title' => $title,
            'mode' => $mode,
            'user' => $user,
            'errors' => $errors,
            'values' => $values,
            'roles' => $user?->isAdmin() ? [User::ROLE_ADMIN] : User::MANAGEABLE_ROLES,
            'csrfToken' => $this->csrf->token(),
        ]), $status);
    }

    /** @return array<string, string> */
    private function emptyValues(): array
    {
        return ['username' => '', 'first_name' => '', 'last_name' => '', 'email' => '', 'role' => User::ROLE_PARTICIPANT, 'is_active' => '1'];
    }
}
