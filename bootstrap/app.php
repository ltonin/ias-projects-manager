<?php

declare(strict_types=1);

use App\Auth\Csrf;
use App\Auth\AuthSession;
use App\Auth\Authorization;
use App\Auth\CurrentUser;
use App\Auth\CurrentPerson;
use App\Auth\ProjectPolicy;
use App\Auth\CapacityPolicy;
use App\Auth\SessionManager;
use App\Controllers\AdminUserController;
use App\Controllers\AdminPersonController;
use App\Controllers\AuthController;
use App\Controllers\ProjectController;
use App\Controllers\ProjectCreationController;
use App\Controllers\ProjectParticipantController;
use App\Controllers\PersonHourAllocationController;
use App\Controllers\PersonCapacityController;
use App\Controllers\WorkPackageController;
use App\Controllers\AnnualEffortController;
use App\Controllers\CsrfTestController;
use App\Controllers\HealthController;
use App\Controllers\HomeController;
use App\Database\ConnectionFactory;
use App\Exceptions\AuthorizationException;
use App\Exceptions\AuthenticationRequiredException;
use App\Exceptions\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Routing\Router;
use App\Repositories\PdoUserRepository;
use App\Repositories\PdoPersonRepository;
use App\Repositories\PdoProjectRepository;
use App\Repositories\PdoProjectParticipantRepository;
use App\Repositories\PdoPersonHourAllocationRepository;
use App\Repositories\PdoPersonCapacityRepository;
use App\Repositories\PdoWorkPackageRepository;
use App\Repositories\PdoAnnualEffortRepository;
use App\Repositories\PdoGlobalAnnualOverviewRepository;
use App\Services\AuthenticationService;
use App\Services\HealthService;
use App\Services\UserService;
use App\Services\PersonService;
use App\Services\ProjectService;
use App\Services\ProjectParticipantService;
use App\Services\PersonHourAllocationService;
use App\Services\PersonCapacityService;
use App\Services\WorkPackageService;
use App\Services\AnnualEffortService;
use App\Services\NavigationService;
use App\Services\GlobalAnnualOverviewService;
use App\Services\ProjectCreationWorkflowService;
use App\Support\ConfigLoader;
use App\Support\Flash;
use App\Support\UrlGenerator;
use App\Support\View;
use App\Validation\UserValidator;
use App\Validation\PersonValidator;
use App\Validation\ProjectValidator;
use App\Validation\ProjectParticipantValidator;
use App\Validation\PersonHourAllocationValidator;
use App\Support\PersonMonthConverter;
use App\Support\DecimalHours;
use App\Validation\PersonCapacityValidator;
use App\Validation\WorkPackageValidator;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/bootstrap/autoload.php';

$config = (new ConfigLoader(PROJECT_ROOT))->load();
$environment = $config->requireString('app.environment');
$debug = (bool) $config->get('app.debug', false) && $environment !== 'production';
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set($config->requireString('app.timezone'));

$request = Request::fromGlobals($_SERVER, $_GET, $_POST, (string) $config->get('app.base_path', ''));
$secure = str_starts_with(strtolower($config->requireString('app.base_url')), 'https://') || $request->isSecure();
$sessionManager = new SessionManager();
$sessionManager->start($config->requireString('app.session_name'), $secure);

foreach ([
    'X-Content-Type-Options' => 'nosniff',
    'X-Frame-Options' => 'SAMEORIGIN',
    'Referrer-Policy' => 'strict-origin-when-cross-origin',
    'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
] as $header => $value) {
    header($header . ': ' . $value);
}

$urls = new UrlGenerator(
    $config->requireString('app.base_url'),
    (string) $config->get('app.base_path', ''),
    (bool) $config->get('app.clean_urls', true),
);
$flash = new Flash();
$csrf = new Csrf();
$idleTimeout = (int) $config->get('app.session_idle_timeout', 1800);
$absoluteTimeout = (int) $config->get('app.session_absolute_timeout', 28800);
$passwordMinLength = (int) $config->get('app.password_min_length', 12);
$authSession = new AuthSession($sessionManager, $idleTimeout, $absoluteTimeout);
$sessionExpired = $authSession->hasExpired();
$users = new PdoUserRepository(new ConnectionFactory($config));
$currentUser = new CurrentUser($authSession, $users);
$authorization = new Authorization($currentUser);
$validator = new UserValidator($passwordMinLength);
$authentication = new AuthenticationService($users, $authSession);
$router = new Router();
$health = new HealthController(new HealthService(new ConnectionFactory($config)));
$people = new PdoPersonRepository(new ConnectionFactory($config));
$userService = new UserService($users,$validator,$authentication,$people,new PersonValidator());
$personCapacity = new PdoPersonCapacityRepository(new ConnectionFactory($config));
$decimalHours = new DecimalHours();
$personCapacityService = new PersonCapacityService($personCapacity,$people,new PersonCapacityValidator(),$decimalHours);
$currentPerson = new CurrentPerson($currentUser, $people);
$projectPolicy = new ProjectPolicy();
$capacityPolicy = new CapacityPolicy();
$projects = new PdoProjectRepository(new ConnectionFactory($config));
$view = new View(PROJECT_ROOT . '/views', $urls, $flash, $currentUser, $csrf, new NavigationService($currentUser,$currentPerson,$projects,$projectPolicy,$capacityPolicy),$request->path());
$globalOverview = new GlobalAnnualOverviewService($projects,new PdoGlobalAnnualOverviewRepository(new ConnectionFactory($config)),$decimalHours);
$home = new HomeController($view,$authorization,$currentPerson,$globalOverview,$request);
$auth = new AuthController($request, $view, $currentUser, $authentication, $validator, $csrf, $flash, $urls);
$adminUsers = new AdminUserController($request,$view,$authorization,$users,$userService,$people,$csrf,$flash,$urls);
$adminPeople = new AdminPersonController($request, $view, $authorization, $people, new PersonService($people, new PersonValidator()), $csrf, $flash, $urls,$personCapacity);
$personCapacityController = new PersonCapacityController($request,$view,$authorization,$currentPerson,$capacityPolicy,$people,$personCapacity,$personCapacityService,$decimalHours,$csrf,$flash,$urls);
$projectParticipants = new PdoProjectParticipantRepository(new ConnectionFactory($config));
$personHourAllocations = new PdoPersonHourAllocationRepository(new ConnectionFactory($config));
$personMonthConverter = new PersonMonthConverter();
$workPackages = new PdoWorkPackageRepository(new ConnectionFactory($config));
$annualEffortRepository = new PdoAnnualEffortRepository(new ConnectionFactory($config));
$annualEffortService = new AnnualEffortService($projects,$projectParticipants,$workPackages,$annualEffortRepository,new PersonHourAllocationValidator(),$projectPolicy,$decimalHours);
$workPackageService = new WorkPackageService($workPackages,$projects,$projectParticipants,new WorkPackageValidator(),$projectPolicy,$personHourAllocations);
$projectService=new ProjectService($projects,new ProjectValidator(),$projectPolicy,$workPackages);
$projectController = new ProjectController($request, $view, $authorization, $currentPerson, $projectPolicy, $projects, $projectParticipants, $personHourAllocations, $personMonthConverter, $projectService, $csrf, $flash, $urls,$workPackages);
$projectCreationController=new ProjectCreationController($request,$view,$authorization,$currentPerson,$projectPolicy,$projectService,$projects,$people,new ProjectCreationWorkflowService(new ConnectionFactory($config)),$sessionManager,$csrf,$flash,$urls);
$projectParticipantController = new ProjectParticipantController(
    $request, $view, $authorization, $currentPerson, $projectPolicy, $projects, $projectParticipants,
    new ProjectParticipantService($projectParticipants, $projects, $people, new ProjectParticipantValidator(), $projectPolicy, $personHourAllocations,$workPackages),
    $csrf, $flash, $urls, $personHourAllocations, $personMonthConverter,$workPackages,
);
$personHourAllocationController = new PersonHourAllocationController(
    $request,$view,$authorization,$currentPerson,$projectPolicy,$projects,$projectParticipants,$personHourAllocations,
    new PersonHourAllocationService($personHourAllocations,$projects,$projectParticipants,new PersonHourAllocationValidator(),$projectPolicy,$workPackages),
    $personMonthConverter,$csrf,$flash,$urls,$people,$personCapacityService,$decimalHours,$workPackages,
);
$workPackageController = new WorkPackageController($request,$view,$authorization,$currentPerson,$projectPolicy,$projects,$workPackages,$workPackageService,$csrf,$flash,$urls,$personHourAllocations);
$annualEffortController = new AnnualEffortController($request,$view,$authorization,$currentPerson,$projects,$annualEffortService,$csrf,$flash,$urls,$personMonthConverter);

$router->get('/', fn (array $parameters): Response => $home->index(), 'home');
$router->get('/health', fn (array $parameters): Response => $health->show(), 'health');
$router->get('/login', fn (array $parameters): Response => $auth->loginForm(), 'login');
$router->post('/login', fn (array $parameters): Response => $auth->login(), 'login.submit');
$router->post('/logout', function (array $parameters) use ($authorization, $auth): Response {
    $authorization->user();
    return $auth->logout();
}, 'logout');
$router->get('/admin/users', fn (array $parameters): Response => $adminUsers->index(), 'admin.users');
$router->get('/admin/users/create', fn (array $parameters): Response => $adminUsers->createForm(), 'admin.users.create');
$router->post('/admin/users', fn (array $parameters): Response => $adminUsers->create(), 'admin.users.store');
$router->get('/admin/users/{id}/edit', fn (array $parameters): Response => $adminUsers->editForm($parameters), 'admin.users.edit');
$router->post('/admin/users/{id}', fn (array $parameters): Response => $adminUsers->update($parameters), 'admin.users.update');
$router->post('/admin/users/{id}/activate', fn (array $parameters): Response => $adminUsers->activate($parameters), 'admin.users.activate');
$router->post('/admin/users/{id}/deactivate', fn (array $parameters): Response => $adminUsers->deactivate($parameters), 'admin.users.deactivate');
$router->get('/admin/people', fn (array $parameters): Response => $adminPeople->index(), 'admin.people');
$router->get('/admin/people/create', fn (array $parameters): Response => $adminPeople->createForm(), 'admin.people.create');
$router->post('/admin/people', fn (array $parameters): Response => $adminPeople->create(), 'admin.people.store');
$router->get('/admin/people/{id}/edit', fn (array $parameters): Response => $adminPeople->editForm($parameters), 'admin.people.edit');
$router->post('/admin/people/{id}', fn (array $parameters): Response => $adminPeople->update($parameters), 'admin.people.update');
$router->post('/admin/people/{id}/activate', fn (array $parameters): Response => $adminPeople->activate($parameters), 'admin.people.activate');
$router->post('/admin/people/{id}/deactivate', fn (array $parameters): Response => $adminPeople->deactivate($parameters), 'admin.people.deactivate');
$router->get('/projects', fn (array $parameters): Response => $projectController->index(), 'projects');
$router->get('/projects/create', fn (array $parameters): Response => $projectCreationController->show(), 'projects.create');
$router->post('/projects/create', fn (array $parameters): Response => $projectCreationController->submit(), 'projects.create.submit');
$router->post('/projects', fn (array $parameters): Response => $projectController->create(), 'projects.store');
$router->get('/projects/{id}', fn (array $parameters): Response => $annualEffortController->readOnly($parameters), 'projects.show');
$router->get('/projects/{id}/edit', fn (array $parameters): Response => $projectController->editForm($parameters), 'projects.edit');
$router->get('/projects/{id}/configure', fn (array $parameters): Response => $projectController->configureForm($parameters), 'projects.configure');
$router->post('/projects/{id}', fn (array $parameters): Response => $projectController->update($parameters), 'projects.update');
$router->post('/projects/{id}/status', fn (array $parameters): Response => $projectController->status($parameters), 'projects.status');
$router->get('/projects/{projectId}/effort/edit',fn(array$p):Response=>$annualEffortController->show($p),'annual-effort');
$router->get('/projects/{projectId}/effort',fn(array$p):Response=>Response::redirect($urls->to('/projects/'.$p['projectId'],['year'=>$request->query('year',(int)date('Y'))])),'annual-effort.legacy');
$router->post('/projects/{projectId}/effort/edit',fn(array$p):Response=>$annualEffortController->save($p),'annual-effort.save');
$router->get('/projects/{projectId}/allocations/unassigned',fn(array$p):Response=>$personHourAllocationController->unassignedIndex($p),'allocations.unassigned');
$router->get('/projects/{projectId}/participants', fn (array $parameters): Response => $projectParticipantController->index($parameters), 'project-participants');
$router->get('/projects/{projectId}/participants/create', fn (array $parameters): Response => $projectParticipantController->createForm($parameters), 'project-participants.create');
$router->post('/projects/{projectId}/participants', fn (array $parameters): Response => $projectParticipantController->create($parameters), 'project-participants.store');
$router->get('/projects/{projectId}/participants/{participantId}', fn (array $parameters): Response => $projectParticipantController->show($parameters), 'project-participants.show');
$router->get('/projects/{projectId}/participants/{participantId}/edit', fn (array $parameters): Response => $projectParticipantController->editForm($parameters), 'project-participants.edit');
$router->post('/projects/{projectId}/participants/{participantId}', fn (array $parameters): Response => $projectParticipantController->update($parameters), 'project-participants.update');
$router->post('/projects/{projectId}/participants/{participantId}/activate', fn (array $parameters): Response => $projectParticipantController->activate($parameters), 'project-participants.activate');
$router->post('/projects/{projectId}/participants/{participantId}/deactivate', fn (array $parameters): Response => $projectParticipantController->deactivate($parameters), 'project-participants.deactivate');
$router->get('/projects/{projectId}/participants/{participantId}/remove', fn (array $parameters): Response => $projectParticipantController->removeForm($parameters), 'project-participants.remove-confirm');
$router->post('/projects/{projectId}/participants/{participantId}/remove', fn (array $parameters): Response => $projectParticipantController->remove($parameters), 'project-participants.remove');
$router->get('/projects/{projectId}/work-packages',fn(array$p):Response=>$workPackageController->index($p),'work-packages');
$router->get('/projects/{projectId}/work-packages/create',fn(array$p):Response=>$workPackageController->createForm($p),'work-packages.create');
$router->post('/projects/{projectId}/work-packages',fn(array$p):Response=>$workPackageController->create($p),'work-packages.store');
$router->get('/projects/{projectId}/work-packages/{workPackageId}',fn(array$p):Response=>$workPackageController->show($p),'work-packages.show');
$router->get('/projects/{projectId}/work-packages/{workPackageId}/edit',fn(array$p):Response=>$workPackageController->editForm($p),'work-packages.edit');
$router->post('/projects/{projectId}/work-packages/{workPackageId}',fn(array$p):Response=>$workPackageController->update($p),'work-packages.update');
$router->post('/projects/{projectId}/work-packages/{workPackageId}/activate',fn(array$p):Response=>$workPackageController->activate($p),'work-packages.activate');
$router->post('/projects/{projectId}/work-packages/{workPackageId}/deactivate',fn(array$p):Response=>$workPackageController->deactivate($p),'work-packages.deactivate');
$router->get('/projects/{projectId}/work-packages/{workPackageId}/remove',fn(array$p):Response=>$workPackageController->removeForm($p),'work-packages.remove-confirm');
$router->post('/projects/{projectId}/work-packages/{workPackageId}/remove',fn(array$p):Response=>$workPackageController->remove($p),'work-packages.remove');
$router->get('/projects/{projectId}/participants/{participantId}/allocations', fn(array$parameters):Response=>$personHourAllocationController->index($parameters), 'allocations');
$router->get('/projects/{projectId}/participants/{participantId}/allocations/create', fn(array$parameters):Response=>$personHourAllocationController->createForm($parameters), 'allocations.create');
$router->post('/projects/{projectId}/participants/{participantId}/allocations', fn(array$parameters):Response=>$personHourAllocationController->create($parameters), 'allocations.store');
$router->get('/projects/{projectId}/participants/{participantId}/allocations/{allocationId}', fn(array$parameters):Response=>$personHourAllocationController->show($parameters), 'allocations.show');
$router->get('/projects/{projectId}/participants/{participantId}/allocations/{allocationId}/edit', fn(array$parameters):Response=>$personHourAllocationController->editForm($parameters), 'allocations.edit');
$router->post('/projects/{projectId}/participants/{participantId}/allocations/{allocationId}', fn(array$parameters):Response=>$personHourAllocationController->update($parameters), 'allocations.update');
$router->get('/projects/{projectId}/participants/{participantId}/allocations/{allocationId}/remove', fn(array$parameters):Response=>$personHourAllocationController->removeForm($parameters), 'allocations.remove-confirm');
$router->post('/projects/{projectId}/participants/{participantId}/allocations/{allocationId}/remove', fn(array$parameters):Response=>$personHourAllocationController->remove($parameters), 'allocations.remove');
$router->get('/people/{personId}/capacity',fn(array$p):Response=>$personCapacityController->show($p),'capacity.show');
$router->get('/capacity',fn(array$p):Response=>$personCapacityController->overview(),'capacity.overview');
$router->get('/admin/people/{personId}/capacity/overrides/create',fn(array$p):Response=>$personCapacityController->createForm($p),'capacity.create');
$router->post('/admin/people/{personId}/capacity/overrides',fn(array$p):Response=>$personCapacityController->create($p),'capacity.store');
$router->get('/admin/people/{personId}/capacity/overrides/{overrideId}/edit',fn(array$p):Response=>$personCapacityController->editForm($p),'capacity.edit');
$router->post('/admin/people/{personId}/capacity/overrides/{overrideId}',fn(array$p):Response=>$personCapacityController->update($p),'capacity.update');
$router->get('/admin/people/{personId}/capacity/overrides/{overrideId}/remove',fn(array$p):Response=>$personCapacityController->removeForm($p),'capacity.remove-confirm');
$router->post('/admin/people/{personId}/capacity/overrides/{overrideId}/remove',fn(array$p):Response=>$personCapacityController->remove($p),'capacity.remove');
if ($environment !== 'production') {
    $csrfTest = new CsrfTestController($request, $csrf, $flash, $urls);
    $router->post('/csrf-test', fn (array $parameters): Response => $csrfTest->verify(), 'csrf-test');
}

try {
    return $router->dispatch($request);
} catch (AuthorizationException $exception) {
    return new Response($view->render('errors/error', ['title' => 'Forbidden', 'status' => 403, 'message' => $exception->getMessage()]), 403);
} catch (AuthenticationRequiredException) {
    if ($sessionExpired) {
        $flash->add('warning', 'Your session expired. Please sign in again.');
    }
    $redirect = App\Support\RedirectTarget::sanitize($request->path());
    return Response::redirect($urls->to('/login', ['redirect' => $redirect]));
} catch (HttpException $exception) {
    return new Response($view->render('errors/error', [
        'title' => $exception->statusCode === 404 ? 'Not found' : 'Request error',
        'status' => $exception->statusCode,
        'message' => $exception->getMessage(),
    ]), $exception->statusCode);
} catch (Throwable $exception) {
    error_log($exception->__toString());
    $message = $debug ? $exception->getMessage() : 'An unexpected error occurred.';
    return new Response($view->render('errors/error', ['title' => 'Server error', 'status' => 500, 'message' => $message]), 500);
}
