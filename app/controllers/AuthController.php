<?php
// app/controllers/AuthController.php
// Secure authentication controller

require_once '../core/BaseController.php';
require_once '../core/Auth.php';
require_once '../core/RateLimiter.php';
require_once '../core/LoginAudit.php';
require_once '../core/Database.php';

class AuthController extends BaseController {

    public function login() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            // === CSRF Protection (Critical) ===
            $this->validateCSRF();

            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            // Basic validation
            if (empty($username) || empty($password)) {
                $this->showLoginForm('Username and password are required.');
                return;
            }

            // === RATE LIMITING (Brute force protection) ===
            $rateLimiter = new RateLimiter(5, 900); // 5 attempts per 15 minutes
            $isRateLimited = $rateLimiter->isLimited($username);

            if ($isRateLimited) {
                // Still log the attempt even if blocked
                $audit = new LoginAudit();
                $audit->logFailure($username, true, 'rate_limited');
                $this->showLoginForm("Too many failed login attempts. Please try again in a few minutes.");
                return;
            }

            // Fetch user with necessary joins
            $db = new Database();
            $db->query("
                SELECT 
                    u.id, u.username, u.employee_id, u.password_hash,
                    e.role, e.branch_id, e.name as employee_name, 
                    b.branch_name,e.photo
                FROM users u
                JOIN employees e ON u.employee_id = e.id
                JOIN branches b ON e.branch_id = b.id
                WHERE u.username = :username 
                  AND u.is_active = 1
                LIMIT 1
            ");
            $db->bind(':username', $username);
            $user = $db->single();

            // Verify credentials
            $audit = new LoginAudit();

            if ($user && password_verify($password, $user['password_hash'])) {
                // === SUCCESS ===
                Auth::login($user);
                $rateLimiter->clear($username);
                $audit->logSuccess($username);

                $this->redirect('dashboard');

            } else {
                // === FAILURE ===
                $rateLimiter->recordFailure($username);
                $audit->logFailure($username, false, 'invalid_credentials');
                $this->showLoginForm('Invalid username or password.');
            }

        } else {
            $this->showLoginForm();
        }
    }

    private function showLoginForm($error = null) {
        $data = [
            'title' => 'Login - Remote Center ERP',
            'error' => $error
        ];
        $this->view('auth/login', $data);
    }

    /**
     * Secure logout using the centralized Auth class
     */
    public function logout() {
        Auth::logout();
        $this->redirect('auth/login');
    }
}
?>