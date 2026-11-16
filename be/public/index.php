<?php

declare(strict_types=1);

use App\Domains\Authentication\Middleware\AuthenticationMiddleware;
use App\Domains\Authentication\Services\AuthenticationService;
use App\Domains\Vacations\Repositories\VacationRequestRepository;
use App\Domains\Vacations\Repositories\VacationsRepository;
use App\Shared\Clock;
use App\Shared\Configuration;
use App\Shared\ConfigurationError;
use App\Shared\ErrorHandlingMiddleware;
use App\Shared\PasswordHasherArgon2id;
use App\Shared\Ports\PasswordHasher;
use App\Shared\SecurityHeadersMiddleware;
use App\Shared\SystemClock;
use Laminas\Diactoros\ResponseFactory;
use League\Route\Strategy\JsonStrategy;

require_once __DIR__ . '/../vendor/autoload.php';

// All instants are handled as UTC (see App\Shared\DateTimes), independent of server settings.
date_default_timezone_set('UTC');

// Load environment variables. A .env file is optional: in a deployment the platform sets them directly.
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->safeLoad();

// Refuse to run with a configuration that cannot work, with a message that names the setting
// (never its value). Weak secrets are a warning here and an error when APP_ENV=production.
try {
    Configuration::assertValid($_ENV);
} catch (ConfigurationError $e) {
    error_log($e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo '{"status_code":500,"reason_phrase":"Internal Server Error"}';
    exit(1);
}
foreach (Configuration::warnings($_ENV) as $warning) {
    error_log('Configuration warning: ' . $warning);
}

// The PHP version is not the caller's business.
header_remove('X-Powered-By');

// Create a new container
$container = new League\Container\Container();

// Database configuration
$dbConfig = [
    'driver'   => 'mysql',
    'host'     => $_ENV['DB_HOST'],
    'port'     => $_ENV['DB_PORT'],
    'database' => $_ENV['DB_DATABASE'],
    'username' => $_ENV['DB_USERNAME'],
    'password' => $_ENV['DB_PASSWORD'],
    'charset'  => $_ENV['DB_CHARSET'],
];

// Add the database to the container. It is shared, so everything in a request uses the same
// connection: a transaction only covers the queries that run on the connection that opened it.
$container->addShared(App\Shared\Database::class, function () use ($dbConfig) {
    return new App\Shared\Database($dbConfig);
});

// Add the request and response to the container
$container->add(Psr\Http\Message\RequestInterface::class, function () {
    return Laminas\Diactoros\ServerRequestFactory::fromGlobals(
        $_SERVER,
        $_GET,
        $_POST,
        $_COOKIE,
        $_FILES
    );
});
$container->add(Psr\Http\Message\ResponseInterface::class, Laminas\Diactoros\Response::class);
$container->add(PasswordHasher::class, PasswordHasherArgon2id::class);
$container->add(Clock::class, SystemClock::class);
// Resolved through the container (not given as a class name), so the repository's own dependencies are autowired.
$container->add(VacationRequestRepository::class, fn () => $container->get(VacationsRepository::class));
$container->add(AuthenticationService::class, function () {
    return new AuthenticationService(
        $_ENV['JWT_SECRET'],
    );
});

// Add the router to the container
$container->add(League\Route\Router::class, function () use ($container) {
    $strategy = new JsonStrategy(new ResponseFactory());
    $strategy->setContainer($container);

    $router = new League\Route\Router();
    $router->setStrategy($strategy); // Set the strategy here

    // Identifies the caller (401 if it cannot) before an endpoint runs. Every route is behind it
    // except logging in, which is how a caller gets a token.
    $authentication = $container->get(AuthenticationMiddleware::class);

    new App\Domains\Authentication\Routes\AuthenticationRoutes($router, $authentication)->register();

    $router->group('', function (League\Route\RouteGroup $protected) {
        new App\Domains\Users\Routes\UsersRoutes($protected)->register();
        new App\Domains\Vacations\Routes\VacationsRoutes($protected)->register();
    })->middleware($authentication);

    return $router;
});
// Enable autowiring
$container->delegate(new League\Container\ReflectionContainer());

// Get the router from the container
$router = $container->get(League\Route\Router::class);
// The first one added is the outermost: headers go on every response, including the error responses
// that the error handler (inside it) produces.
$router->middleware(new SecurityHeadersMiddleware());
$router->middleware(new ErrorHandlingMiddleware());

// Dispatch the request
$request = $container->get(Psr\Http\Message\RequestInterface::class);
$response = $router->dispatch($request);

// Send the response
new Laminas\HttpHandlerRunner\Emitter\SapiEmitter()->emit($response);
