<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Authorization;
use App\Auth\Csrf;
use App\Auth\CurrentPerson;
use App\Auth\ProjectPolicy;
use App\Exceptions\DuplicateProjectParticipantException;
use App\Exceptions\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Models\Project;
use App\Models\ProjectParticipant;
use App\Repositories\ProjectParticipantRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\PersonHourAllocationRepository;
use App\Support\PersonMonthConverter;
use App\Exceptions\ParticipantHasAllocationsException;
use App\Exceptions\ParticipantResponsibleForWorkPackageException;
use App\Repositories\WorkPackageRepository;
use App\Services\ProjectParticipantService;
use App\Support\Flash;
use App\Support\UrlGenerator;
use App\Support\View;
use App\Support\ParticipationDateDefaults;

final class ProjectParticipantController
{
    public function __construct(
        private readonly Request $request,
        private readonly View $view,
        private readonly Authorization $authorization,
        private readonly CurrentPerson $currentPerson,
        private readonly ProjectPolicy $policy,
        private readonly ProjectRepository $projects,
        private readonly ProjectParticipantRepository $participants,
        private readonly ProjectParticipantService $service,
        private readonly Csrf $csrf,
        private readonly Flash $flash,
        private readonly UrlGenerator $urls,
        private readonly PersonHourAllocationRepository $allocations,
        private readonly PersonMonthConverter $converter,
        private readonly WorkPackageRepository $workPackages,
    ) {
    }

    /** @param array<string,string> $parameters */
    public function index(array $parameters): Response
    {
        $user = $this->authorization->user();
        $person = $this->currentPerson->get();
        $project = $this->project($parameters);
        $this->policy->requireView($user, $person, $project);
        $listing = $this->service->listing($project, $this->request->queryData());
        return new Response($this->view->render('project_participants/index', [
            'title' => 'Participants — ' . $project->acronym,
            'project' => $project,
            'page' => $listing['page'],
            'filters' => $listing['filters'],
            'roleLabels' => ProjectParticipant::ROLE_LABELS,
            'canManage' => $this->policy->canManageParticipants($user, $person, $project),
            'csrfToken' => $this->csrf->token(),
            'configurationYear'=>$this->validYear($this->request->query('year')),
        ]));
    }

    /** @param array<string,string> $parameters */
    public function show(array $parameters): Response
    {
        $user = $this->authorization->user();
        $person = $this->currentPerson->get();
        $project = $this->project($parameters);
        $this->policy->requireView($user, $person, $project);
        $participant = $this->participant($parameters, $project);
        $canManage = $this->policy->canManageParticipants($user, $person, $project);
        $canNotes = $this->policy->canViewParticipantNotes($user, $person, $project);
        return new Response($this->view->render('project_participants/show', [
            'title' => $participant->personName() . ' — ' . $project->acronym,
            'project' => $project,
            'participant' => $canNotes ? $participant : $participant->withoutNotes(),
            'canManage' => $canManage,
            'canViewNotes' => $canNotes,
            'csrfToken' => $this->csrf->token(),
            'hasAllocations'=>$this->allocations->hasAllocationsForParticipant($participant->id),
            'allocationTotals'=>$this->allocations->totalsForParticipant($participant->id),
            'unifiedAllocationTotals'=>$this->allocations->unifiedTotalsForParticipant($participant->id),
            'divergentAllocationCount'=>$this->allocations->divergentCountForParticipant($participant->id),
            'recentAllocations'=>$this->allocations->recentForParticipant($participant->id),
            'allocationCount'=>$this->allocations->countForParticipant($participant->id),
            'converter'=>$this->converter,
            'responsibleWorkPackages'=>$this->workPackages->listByResponsibleParticipant($participant->id),
        ]));
    }

    /** @param array<string,string> $parameters */
    public function createForm(array $parameters): Response
    {
        [$user, $person, $project] = $this->managementContext($parameters);
        [$returnContext,$returnYear]=$this->returnContext($this->request->query('return'),$this->request->query('year'));
        return $this->form('Add participant', 'create', $project, null, $user, $person, [], $this->emptyValues($project),200,$returnContext,$returnYear);
    }

    /** @param array<string,string> $parameters */
    public function create(array $parameters): Response
    {
        [$user, $person, $project] = $this->managementContext($parameters);
        $this->requireCsrf();
        $input = $this->request->postData();
        [$returnContext,$returnYear]=$this->returnContext($input['return_context']??null,$input['return_year']??null);
        $errors = $this->service->validateCreate($project, $input);
        if ($errors !== []) return $this->form('Add participant', 'create', $project, null, $user, $person, $errors, $input, 422,$returnContext,$returnYear);
        try {
            $created = $this->service->create($project, $input, $user, $person);
        } catch (DuplicateProjectParticipantException $exception) {
            return $this->form('Add participant', 'create', $project, null, $user, $person, ['person_id' => $exception->getMessage()], $input, 422,$returnContext,$returnYear);
        }
        $this->flash->add('success', $created->personName() . ' was added to ' . $project->acronym . '.');
        return Response::redirect($this->urls->to($returnContext==='effort'?'/projects/'.$project->id:$this->basePath($project),$returnContext!==null&&$returnYear!==null?['year'=>$returnYear]:[]));
    }

    /** @param array<string,string> $parameters */
    public function editForm(array $parameters): Response
    {
        [$user, $person, $project] = $this->managementContext($parameters);
        $participant = $this->participant($parameters, $project);
        [$returnContext,$returnYear]=$this->returnContext($this->request->query('return'),$this->request->query('year'));
        return $this->form('Edit participant', 'edit', $project, $participant, $user, $person, [], $this->values($participant),200,$returnContext,$returnYear);
    }

    /** @param array<string,string> $parameters */
    public function update(array $parameters): Response
    {
        [$user, $person, $project] = $this->managementContext($parameters);
        $participant = $this->participant($parameters, $project);
        $this->requireCsrf();
        $input = $this->request->postData();
        [$returnContext,$returnYear]=$this->returnContext($input['return_context']??null,$input['return_year']??null);
        $errors = $this->service->validateUpdate($project, $participant, $input);
        if ($errors !== []) return $this->form('Edit participant', 'edit', $project, $participant, $user, $person, $errors, $input, 422,$returnContext,$returnYear);
        $updated = $this->service->update($project, $participant, $input, $user, $person);
        $this->flash->add('success', $updated->personName() . ' participation was updated.');
        return Response::redirect($this->urls->to($returnContext==='configure'?$this->basePath($project):$this->basePath($project).'/'.$updated->id,$returnContext==='configure'&&$returnYear!==null?['year'=>$returnYear]:[]));
    }

    /** @param array<string,string> $parameters */
    public function activate(array $parameters): Response { return $this->changeActive($parameters, true); }
    /** @param array<string,string> $parameters */
    public function deactivate(array $parameters): Response { return $this->changeActive($parameters, false); }

    /** @param array<string,string> $parameters */
    public function removeForm(array $parameters): Response
    {
        [, , $project] = $this->managementContext($parameters);
        $participant = $this->participant($parameters, $project);
        return new Response($this->view->render('project_participants/remove', [
            'title' => 'Remove participant',
            'project' => $project,
            'participant' => $participant->withoutNotes(),
            'csrfToken' => $this->csrf->token(),
            'hasAllocations'=>$this->allocations->hasAllocationsForParticipant($participant->id),
            'responsibleWorkPackageCount'=>$this->workPackages->countByResponsibleParticipant($participant->id),
        ]));
    }

    /** @param array<string,string> $parameters */
    public function remove(array $parameters): Response
    {
        [$user, $person, $project] = $this->managementContext($parameters);
        $participant = $this->participant($parameters, $project);
        $this->requireCsrf();
        $name = $participant->personName();
        try{$this->service->remove($project, $participant, $user, $person);}
        catch(ParticipantHasAllocationsException$exception){throw new HttpException(409,$exception->getMessage());}
        catch(ParticipantResponsibleForWorkPackageException$exception){throw new HttpException(409,$exception->getMessage());}
        $this->flash->add('success', $name . ' was removed from ' . $project->acronym . '.');
        return Response::redirect($this->urls->to($this->basePath($project)));
    }

    /** @param array<string,string> $parameters */
    private function changeActive(array $parameters, bool $active): Response
    {
        [$user, $person, $project] = $this->managementContext($parameters);
        $participant = $this->participant($parameters, $project);
        $this->requireCsrf();
        $updated = $this->service->setActive($project, $participant, $active, $user, $person);
        $this->flash->add('success', $updated->personName() . ' participation is now ' . strtolower($updated->activeLabel()) . '.');
        return Response::redirect($this->urls->to($this->basePath($project) . '/' . $updated->id));
    }

    /** @param array<string,string> $parameters @return array{\App\Models\User,?\App\Models\Person,Project} */
    private function managementContext(array $parameters): array
    {
        $user = $this->authorization->user();
        $person = $this->currentPerson->get();
        $project = $this->project($parameters);
        $this->policy->requireManageParticipants($user, $person, $project);
        return [$user, $person, $project];
    }

    /** @param array<string,string> $parameters */
    private function project(array $parameters): Project
    {
        $id = $this->id($parameters['projectId'] ?? null);
        return $this->projects->findById($id) ?? throw new HttpException(404, 'Project not found.');
    }

    /** @param array<string,string> $parameters */
    private function participant(array $parameters, Project $project): ProjectParticipant
    {
        $id = $this->id($parameters['participantId'] ?? null);
        $participant = $this->participants->findById($id);
        if ($participant === null || $participant->projectId !== $project->id) throw new HttpException(404, 'Participant not found.');
        return $participant;
    }

    private function id(mixed $value): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) throw new HttpException(404, 'Record not found.');
        return (int) $id;
    }
    private function requireCsrf(): void
    {
        $token = $this->request->post('_csrf');
        if (!is_string($token) || !$this->csrf->validate($token)) throw new HttpException(403, 'Invalid CSRF token.');
    }
    private function basePath(Project $project): string { return '/projects/' . $project->id . '/participants'; }

    /** @param array<string,string> $errors @param array<string,mixed> $values */
    private function form(string $title, string $mode, Project $project, ?ProjectParticipant $participant, \App\Models\User $user, ?\App\Models\Person $person, array $errors, array $values, int $status = 200,?string$returnContext=null,?int$returnYear=null): Response
    {
        return new Response($this->view->render('project_participants/form', [
            'title' => $title,
            'mode' => $mode,
            'project' => $project,
            'participant' => $participant,
            'user'=>$user,
            'errors' => $errors,
            'values' => $values,
            'peopleOptions' => $mode === 'create' ? $this->participants->availablePeople($project->id) : [],
            'roleLabels' => ProjectParticipant::ROLE_LABELS,
            'csrfToken' => $this->csrf->token(),
            'returnContext'=>$returnContext,'returnYear'=>$returnYear,
        ]), $status);
    }
    /** @return array{?string,?int} */
    private function returnContext(mixed$context,mixed$year):array
    {
        $context=(string)$context;if(!in_array($context,['effort','configure'],true))return[null,null];
        return[$context,$this->validYear($year)??(int)date('Y')];
    }
    private function validYear(mixed$year):?int{$valid=filter_var($year,FILTER_VALIDATE_INT,['options'=>['min_range'=>\App\Models\PersonHourAllocation::MIN_YEAR,'max_range'=>\App\Models\PersonHourAllocation::MAX_YEAR]]);return$valid===false?null:(int)$valid;}
    /** @return array<string,string> */
    private function emptyValues(Project$project): array
    {
        return ['person_id' => '', 'project_role' => 'researcher']+(new ParticipationDateDefaults())->forProject($project)+['is_active' => '1', 'notes' => ''];
    }
    /** @return array<string,string> */
    private function values(ProjectParticipant $participant): array
    {
        return [
            'person_id' => (string) $participant->personId,
            'project_role' => $participant->projectRole,
            'participation_start' => $participant->participationStart?->format('Y-m-d') ?? '',
            'participation_end' => $participant->participationEnd?->format('Y-m-d') ?? '',
            'is_active' => $participant->isActive ? '1' : '0',
            'notes' => $participant->notes ?? '',
        ];
    }
}
