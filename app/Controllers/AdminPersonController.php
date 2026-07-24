<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Authorization;
use App\Auth\Csrf;
use App\Exceptions\DuplicatePersonEmailException;
use App\Exceptions\HttpException;
use App\Exceptions\UserAlreadyLinkedException;
use App\Http\Request;
use App\Http\Response;
use App\Models\Person;
use App\Repositories\PersonRepository;
use App\Repositories\PersonCapacityRepository;
use App\Services\PersonService;
use App\Support\Flash;
use App\Support\UrlGenerator;
use App\Support\View;

final class AdminPersonController
{
    public function __construct(
        private readonly Request $request,
        private readonly View $view,
        private readonly Authorization $authorization,
        private readonly PersonRepository $people,
        private readonly PersonService $service,
        private readonly Csrf $csrf,
        private readonly Flash $flash,
        private readonly UrlGenerator $urls,
        private readonly PersonCapacityRepository $capacity,
    ) {
    }

    public function index(): Response
    {
        $user = $this->authorization->peopleViewer();
        $listing = $this->service->listing($this->request->queryData());
        return new Response($this->view->render('admin/people/index', [
            'title' => 'People',
            'page' => $listing['page'],
            'filters' => $listing['filters'],
            'positionLabels' => Person::POSITION_LABELS,
            'csrfToken' => $this->csrf->token(),
            'canManage' => $user->isAdmin(),
        ]));
    }

    public function createForm(): Response
    {
        $this->authorization->admin();
        return $this->form('Create person', 'create', null, [], $this->emptyValues());
    }

    public function create(): Response
    {
        $this->authorization->admin();
        $this->requireCsrf();
        $input = $this->request->postData();
        $errors = $this->service->validate($input);
        if ($errors !== []) {
            return $this->form('Create person', 'create', null, $errors, $input, 422);
        }
        try {
            $person = $this->service->create($input);
        } catch (DuplicatePersonEmailException) {
            return $this->form('Create person', 'create', null, ['institutional_email' => 'That institutional email is already in use.'], $input, 422);
        } catch (UserAlreadyLinkedException) {
            return $this->form('Create person', 'create', null, ['user_id' => 'That user is already linked to another person.'], $input, 422);
        }
        $this->flash->add('success', $person->fullName() . ' was added to the people registry.');
        return Response::redirect($this->urls->to('/admin/people'));
    }

    /** @param array<string, string> $parameters */
    public function editForm(array $parameters): Response
    {
        $this->authorization->admin();
        $person = $this->find($parameters);
        return $this->form('Edit person', 'edit', $person, [], $this->values($person));
    }

    /** @param array<string, string> $parameters */
    public function update(array $parameters): Response
    {
        $this->authorization->admin();
        $this->requireCsrf();
        $person = $this->find($parameters);
        $input = $this->request->postData();
        $errors = $this->service->validate($input, $person->id);
        if ($errors !== []) {
            return $this->form('Edit person', 'edit', $person, $errors, $input, 422);
        }
        try {
            $updated = $this->service->update($person->id, $input);
        } catch (DuplicatePersonEmailException) {
            return $this->form('Edit person', 'edit', $person, ['institutional_email' => 'That institutional email is already in use.'], $input, 422);
        } catch (UserAlreadyLinkedException) {
            return $this->form('Edit person', 'edit', $person, ['user_id' => 'That user is already linked to another person.'], $input, 422);
        }
        $this->flash->add('success', $updated->fullName() . ' was updated.');
        return Response::redirect($this->urls->to('/admin/people'));
    }

    /** @param array<string, string> $parameters */
    public function activate(array $parameters): Response { return $this->changeActive($parameters, true); }
    /** @param array<string, string> $parameters */
    public function deactivate(array $parameters): Response { return $this->changeActive($parameters, false); }

    /** @param array<string, string> $parameters */
    private function changeActive(array $parameters, bool $active): Response
    {
        $this->authorization->admin();
        $this->requireCsrf();
        $person = $this->find($parameters);
        $updated = $this->service->setActive($person->id, $active);
        $this->flash->add('success', sprintf('%s was %s.', $updated->fullName(), $active ? 'activated' : 'deactivated'));
        return Response::redirect($this->urls->to('/admin/people'));
    }

    /** @param array<string, string> $parameters */
    private function find(array $parameters): Person
    {
        $id = filter_var($parameters['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new HttpException(404, 'Person not found.');
        }
        return $this->people->findById((int) $id) ?? throw new HttpException(404, 'Person not found.');
    }

    private function requireCsrf(): void
    {
        $token = $this->request->post('_csrf');
        if (!is_string($token) || !$this->csrf->validate($token)) {
            throw new HttpException(403, 'Invalid CSRF token.');
        }
    }

    /** @param array<string, string> $errors @param array<string, mixed> $values */
    private function form(string $title, string $mode, ?Person $person, array $errors, array $values, int $status = 200): Response
    {
        return new Response($this->view->render('admin/people/form', [
            'title' => $title,
            'mode' => $mode,
            'person' => $person,
            'errors' => $errors,
            'values' => $values,
            'positionLabels' => Person::POSITION_LABELS,
            'userOptions' => $this->people->availableUsers($person?->id),
            'csrfToken' => $this->csrf->token(),
            'hasCapacityOverrides' => $person !== null && $this->capacity->hasOverridesForPerson($person->id),
        ]), $status);
    }

    /** @return array<string, string> */
    private function emptyValues(): array
    {
        return [
            'user_id' => '', 'first_name' => '', 'last_name' => '', 'institutional_email' => '',
            'affiliation' => '', 'position_type' => 'researcher', 'is_internal' => '1',
            'active_from' => '', 'active_to' => '', 'is_active' => '1', 'notes' => '',
            'default_monthly_capacity_hours'=>'125.00',
            'annual_capacity_hours'=>Person::defaultAnnualCapacity('researcher'),
        ];
    }

    /** @return array<string, string> */
    private function values(Person $person): array
    {
        return [
            'user_id' => $person->userId === null ? '' : (string) $person->userId,
            'first_name' => $person->firstName,
            'last_name' => $person->lastName,
            'institutional_email' => $person->institutionalEmail ?? '',
            'affiliation' => $person->affiliation ?? '',
            'position_type' => $person->positionType,
            'is_internal' => $person->isInternal ? '1' : '0',
            'active_from' => $person->activeFrom?->format('Y-m-d') ?? '',
            'active_to' => $person->activeTo?->format('Y-m-d') ?? '',
            'is_active' => $person->isActive ? '1' : '0',
            'default_monthly_capacity_hours'=>$person->defaultMonthlyCapacityHours,
            'annual_capacity_hours'=>$person->annualCapacityHours,
            'notes' => $person->notes ?? '',
        ];
    }
}
