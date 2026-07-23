<?php

declare(strict_types=1);

use App\Auth\Csrf;
use App\Auth\SessionManager;
use App\Controllers\CsrfTestController;
use App\Controllers\HealthController;
use App\Controllers\HomeController;
use App\Database\ConnectionFactory;
use App\Exceptions\AuthorizationException;
use App\Exceptions\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Routing\Router;
use App\Services\HealthService;
use App\Support\ConfigLoader;
use App\Support\Flash;
use App\Support\UrlGenerator;
use App\Support\View;

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
(new SessionManager())->start($config->requireString('app.session_name'), $secure);

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
$view = new View(PROJECT_ROOT . '/views', $urls, $flash);
$router = new Router();
$home = new HomeController($view, $config, $csrf, $urls);
$health = new HealthController(new HealthService(new ConnectionFactory($config)));

$router->get('/', fn (array $parameters): Response => $home->index(), 'home');
$router->get('/health', fn (array $parameters): Response => $health->show(), 'health');
if ($environment !== 'production') {
    $csrfTest = new CsrfTestController($request, $csrf, $flash, $urls);
    $router->post('/csrf-test', fn (array $parameters): Response => $csrfTest->verify(), 'csrf-test');
}

try {
    return $router->dispatch($request);
} catch (AuthorizationException) {
    return new Response($view->render('errors/error', ['title' => 'Forbidden', 'status' => 403, 'message' => 'Access denied.']), 403);
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
