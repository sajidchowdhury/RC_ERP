<?php
// core/Auth.php
// Centralized, secure authentication helper

require_once __DIR__ . '/Database.php';

class Auth {

    /**
     * Perform secure login
     * - Regenerates session ID (prevents fixation)
     * - Sets consistent session variables
     * - Updates last_login timestamp
     */
    public static function login(array $user): void
    {
        // Prevent session fixation
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        // Standard session variables used across the app
        $_SESSION['user_id']        = (int)$user['id'];
        $_SESSION['username']       = $user['username'];
        $_SESSION['employee_id']    = $user['employee_id'] ?? null;
        $_SESSION['employee_name']  = $user['employee_name'] ?? $user['name'] ?? null;
        $_SESSION['role']           = $user['role'] ?? 'user';
        $_SESSION['branch_id']      = $user['branch_id'] ?? null;
        $_SESSION['branch_name']    = $user['branch_name'] ?? null;
        $_SESSION['logged_in_at']   = time();
        $_SESSION['photo']    = $user['photo'] ?? '';

        

        // Update last login in database (best effort)
        self::updateLastLogin((int)$user['id']);

        // Optional: store minimal user object
        $_SESSION['user'] = [
            'id'            => (int)$user['id'],
            'username'      => $user['username'],
            'role'          => $_SESSION['role'],
            'branch_id'     => $_SESSION['branch_id'],
        ];
    }

    /**
     * Update the last_login timestamp for the user
     */
    private static function updateLastLogin(int $userId): void
    {
        try {
            $db = new Database();
            $db->query("UPDATE users SET last_login = NOW() WHERE id = :id");
            $db->bind(':id', $userId);
            $db->execute();
        } catch (Throwable $e) {
            // Fail silently - last_login is nice-to-have, not critical
            error_log("Auth::updateLastLogin failed for user {$userId}: " . $e->getMessage());
        }
    }

    /**
     * Secure logout - clears everything properly
     */
    public static function logout(): void
    {
        // Use centralized session destroyer
        require_once __DIR__ . '/Session.php';
        Session::destroy();

        // Start a fresh session just for the flash message after logout
        session_start();
        $_SESSION['flash'] = [
            'message' => 'You have been logged out successfully.',
            'type'    => 'success'
        ];
    }

    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            Flash::set("Please login first to access this page.", "error");
            header("Location: " . BASE_URL . "auth/login");
            exit;
        }
    }

    public static function getCurrentUser(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function getUserId(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    public static function getRole(): ?string
    {
        return $_SESSION['role'] ?? null;
    }

    public static function getBranchId(): ?int
    {
        return $_SESSION['branch_id'] ?? null;
    }

    /**
     * Check if current user is admin (simple role check)
     */
    public static function isAdmin(): bool
    {
        return self::getRole() === 'admin';
    }

    public static function hasRole(string ...$roles): bool
    {
        $current = self::getRole();
        if ($current === null || $current === '') {
            return false;
        }
        return in_array($current, $roles, true);
    }

    /**
     * Abort with JSON 403 if the user does not have one of the allowed roles.
     */
    public static function requireRoleJson(array $allowedRoles): void
    {
        if ($allowedRoles === [] || self::hasRole(...$allowedRoles)) {
            return;
        }
        require_once __DIR__ . '/ApiResponse.php';
        ApiResponse::emit(
            ApiResponse::error(
                'You do not have permission to perform this action.',
                ApiResponse::CODE_FORBIDDEN
            ),
            403
        );
    }
}
?>