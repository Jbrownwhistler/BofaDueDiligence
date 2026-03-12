<?php
/**
 * BofaDueDiligence - Front Controller
 */

// Load foundation
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/autoload.php';

// Start session
Session::start();
CSRF::generateToken();

// Create upload directory if needed
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// Parse route
$requestUri = $_SERVER['REQUEST_URI'];
$basePath = parse_url(BASE_URL, PHP_URL_PATH) ?: '/';
$path = substr($requestUri, strlen($basePath));
$path = strtok($path, '?'); // Remove query string
$path = trim($path, '/');

// Default route
if ($path === '' || $path === false) {
    if (Auth::check()) {
        Auth::redirectToDashboard();
    } else {
        $path = 'login';
    }
}

// Load routes
$routes = require __DIR__ . '/../src/routes.php';

// Match route
if (isset($routes[$path])) {
    $route = $routes[$path];

    // Check role
    if ($route['role'] !== null) {
        Auth::requireRole($route['role']);
    }

    // CSRF check on POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !str_starts_with($path, 'api/')) {
        CSRF::check();
    }

    // Instantiate controller and call method
    $controllerClass = $route['controller'];
    $method = $route['method'];

    $controller = new $controllerClass();
    $controller->$method();
} else {
    http_response_code(404);
    include __DIR__ . '/../templates/errors/404.php';
}
