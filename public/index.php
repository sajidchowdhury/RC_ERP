<?php
// public/index.php
// Secure entry point for Remote Center ERP

// =============================================
// 1. SECURE SESSION + CSRF (using centralized class)
// =============================================
require_once '../core/Session.php';
Session::start();

// =============================================
// 2. CORE INCLUDES
// =============================================
require_once '../config/config.php';
require_once '../core/Database.php';
require_once '../core/BaseController.php';
require_once '../core/Auth.php';
require_once '../core/Flash.php';

// ==================== ROUTING ====================
$request_uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
$request_path = parse_url($request_uri, PHP_URL_PATH) ?: '/';
$request_path = '/' . trim(str_replace('\\', '/', $request_path), '/');

$script_dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');

// Strip /public/index.php directory from URL (direct access: .../public/auth/login)
if ($script_dir !== '' && $script_dir !== '/' && strpos($request_path, $script_dir) === 0) {
    $path = substr($request_path, strlen($script_dir));
} else {
    // Root .htaccess rewrites to public/ but URI may omit /public (e.g. .../remote-center-erp/auth/login)
    $app_folder = basename(dirname(__DIR__));
    $stripped = false;
    foreach (['/' . $app_folder . '/public/', '/' . $app_folder . '/'] as $prefix) {
        if (strpos($request_path, $prefix) === 0) {
            $path = substr($request_path, strlen($prefix));
            $stripped = true;
            break;
        }
    }
    if (!$stripped) {
        $path = ltrim($request_path, '/');
    }
}

$path = trim($path, '/');

// Friendly shortcuts
// ==================== ROUTING ALIASES ====================
$routeAliases = [
    'login'              => 'auth/login',
    'logout'             => 'auth/logout',
    'save-fcm-token'     => 'sales/save_fcm_token',
    'save_fcm_token'     => 'sales/save_fcm_token',
    'sales-guide'        => 'sales/guide',
    'guideline'          => 'sales/guide',
    'reports'            => 'Report/index',
    'report'             => 'Report/index',
    'notifications/unread' => 'notification/unread',     // Fixed
    'notification/unread'=> 'notification/unread',
];


if (isset($routeAliases[$path])) {
    $path = $routeAliases[$path];
}

// Home: dashboard when logged in, otherwise login
if ($path === '' || $path === 'index.php' || $path === 'public') {
    $path = Auth::isLoggedIn() ? 'dashboard' : 'auth/login';
}

$segments = explode('/', $path);

$controllerName = ucfirst($segments[0] ?? 'Dashboard') . 'Controller';
$method         = $segments[1] ?? 'index';
$params         = array_slice($segments, 2);

// ==================== AUTH PROTECTION ====================
$publicControllers = ['AuthController'];   // Add more public controllers here if needed

if (!in_array($controllerName, $publicControllers)) {
    Auth::requireLogin();
}

// ==================== ROUTE TO CONTROLLER ====================
$controllerFile = '../app/controllers/' . $controllerName . '.php';

if (file_exists($controllerFile)) {
    require_once $controllerFile;
    
    $controller = new $controllerName();

    if (method_exists($controller, $method)) {
        $GLOBALS['__erp_route_controller'] = $controllerName;
        $GLOBALS['__erp_route_action'] = $method;
        call_user_func_array([$controller, $method], $params);
    } else {
        // Show nice custom error page
        http_response_code(404);
        $code = 404;
        $message = "Method not found: " . htmlspecialchars($method);
        include __DIR__ . '/error.php';
        exit;
    }
} else {
    // Show nice custom error page
    http_response_code(404);
    $code = 404;
    $message = "Page not found: " . htmlspecialchars($controllerName);
    include __DIR__ . '/error.php';
    exit;
}
?>