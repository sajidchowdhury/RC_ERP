<?php
// app/services/Security/RouteAccess.php — Phase 7 route/role enforcement

require_once __DIR__ . '/../../../core/Auth.php';

class RouteAccess
{
    /** @var array<string, array<string, string[]>>|null */
    private static ?array $matrix = null;

    /**
     * @return array<string, array<string, string[]>>
     */
    private static function matrix(): array
    {
        if (self::$matrix === null) {
            $path = dirname(__DIR__, 2) . '/config/route_roles.php';
            $loaded = is_readable($path) ? require $path : [];
            self::$matrix = is_array($loaded) ? $loaded : [];
        }

        return self::$matrix;
    }

    public static function allows(string $controller, string $action): bool
    {
        if (Auth::isAdmin()) {
            return true;
        }

        $roles = self::matrix()[$controller][$action] ?? null;
        if ($roles === null) {
            return true;
        }

        return Auth::hasRole(...$roles);
    }

    /**
     * JSON 403 when role is not permitted (call at start of sensitive actions).
     */
    public static function require(string $controller, string $action): void
    {
        if (self::allows($controller, $action)) {
            return;
        }

        require_once __DIR__ . '/../../../core/ApiResponse.php';
        ApiResponse::emit(
            ApiResponse::error(
                'You do not have permission to perform this action.',
                ApiResponse::CODE_FORBIDDEN
            ),
            403
        );
    }
}