<?php
// core/PendingLogin.php — intermediate state between password/remember and full session (2FA gate).

class PendingLogin
{
    public const TTL_SECONDS = 300;

    /**
     * @param array<string, mixed> $user
     */
    public static function start(array $user, bool $rememberMe = false): void
    {
        $_SESSION['pending_2fa_user_id'] = (int)$user['id'];
        $_SESSION['pending_2fa_username'] = (string)($user['username'] ?? '');
        $_SESSION['pending_2fa_expires'] = time() + self::TTL_SECONDS;
        $_SESSION['pending_2fa_remember'] = $rememberMe ? 1 : 0;
    }

    public static function isActive(): bool
    {
        if (empty($_SESSION['pending_2fa_user_id'])) {
            return false;
        }

        $expires = (int)($_SESSION['pending_2fa_expires'] ?? 0);
        if ($expires > 0 && time() > $expires) {
            self::clear();
            return false;
        }

        return true;
    }

    public static function userId(): ?int
    {
        return self::isActive() ? (int)$_SESSION['pending_2fa_user_id'] : null;
    }

    public static function username(): string
    {
        return self::isActive() ? (string)($_SESSION['pending_2fa_username'] ?? '') : '';
    }

    public static function shouldRemember(): bool
    {
        return self::isActive() && !empty($_SESSION['pending_2fa_remember']);
    }

    public static function clear(): void
    {
        unset(
            $_SESSION['pending_2fa_user_id'],
            $_SESSION['pending_2fa_username'],
            $_SESSION['pending_2fa_expires'],
            $_SESSION['pending_2fa_remember']
        );
    }
}
