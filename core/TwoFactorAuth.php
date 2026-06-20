<?php
// core/TwoFactorAuth.php — TOTP setup/verify for user accounts (Phase 6).

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Totp.php';

class TwoFactorAuth
{
    public static function issuer(): string
    {
        return defined('AUTH_2FA_ISSUER') ? (string)AUTH_2FA_ISSUER : APP_NAME;
    }

    public static function isEnabled(int $userId): bool
    {
        try {
            $db = new Database();
            $db->query('SELECT totp_enabled FROM users WHERE id = :id LIMIT 1');
            $db->bind(':id', $userId);
            $row = $db->single();

            return (bool)($row['totp_enabled'] ?? false);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * @return array{status: string, secret?: string, message?: string}
     */
    public static function beginSetup(int $userId): array
    {
        if (self::isEnabled($userId)) {
            return ['status' => 'error', 'message' => 'Two-factor authentication is already enabled.'];
        }

        $secret = Totp::generateSecret();

        try {
            $db = new Database();
            $db->query('
                UPDATE users
                SET totp_secret = :secret, totp_enabled = 0
                WHERE id = :id
            ');
            $db->bind(':secret', $secret);
            $db->bind(':id', $userId);

            if (!$db->execute()) {
                return ['status' => 'error', 'message' => 'Failed to start 2FA setup.'];
            }

            return ['status' => 'success', 'secret' => $secret];
        } catch (Throwable $e) {
            error_log('TwoFactorAuth::beginSetup failed: ' . $e->getMessage());
            return ['status' => 'error', 'message' => 'Failed to start 2FA setup.'];
        }
    }

    /**
     * Confirm setup with a valid TOTP code and enable 2FA.
     */
    public static function confirmSetup(int $userId, string $code): array
    {
        $secret = self::getPendingSecret($userId);
        if ($secret === null) {
            return ['status' => 'error', 'message' => 'No 2FA setup in progress. Start setup first.'];
        }

        if (!Totp::verify($secret, $code)) {
            return ['status' => 'error', 'message' => 'Invalid verification code. Try again.'];
        }

        try {
            $db = new Database();
            $db->query('UPDATE users SET totp_enabled = 1 WHERE id = :id');
            $db->bind(':id', $userId);

            if (!$db->execute()) {
                return ['status' => 'error', 'message' => 'Failed to enable 2FA.'];
            }

            require_once __DIR__ . '/CredentialVersion.php';
            CredentialVersion::bump($userId);

            return ['status' => 'success', 'message' => 'Two-factor authentication enabled.'];
        } catch (Throwable $e) {
            error_log('TwoFactorAuth::confirmSetup failed: ' . $e->getMessage());
            return ['status' => 'error', 'message' => 'Failed to enable 2FA.'];
        }
    }

    public static function verifyLogin(int $userId, string $code): bool
    {
        $row = self::getSecretRow($userId);
        if (!$row || !(bool)$row['totp_enabled'] || empty($row['totp_secret'])) {
            return false;
        }

        return Totp::verify((string)$row['totp_secret'], $code);
    }

    public static function disable(int $userId, string $currentPassword, string $code = ''): array
    {
        try {
            $db = new Database();
            $db->query('SELECT password_hash, totp_secret, totp_enabled FROM users WHERE id = :id LIMIT 1');
            $db->bind(':id', $userId);
            $user = $db->single();

            if (!$user || !(bool)$user['totp_enabled']) {
                return ['status' => 'error', 'message' => 'Two-factor authentication is not enabled.'];
            }

            if (!password_verify($currentPassword, (string)$user['password_hash'])) {
                return ['status' => 'error', 'message' => 'Current password is incorrect.'];
            }

            if ($code !== '' && !Totp::verify((string)$user['totp_secret'], $code)) {
                return ['status' => 'error', 'message' => 'Invalid authenticator code.'];
            } elseif ($code === '') {
                return ['status' => 'error', 'message' => 'Enter your current authenticator code to disable 2FA.'];
            }

            $db->query('UPDATE users SET totp_secret = NULL, totp_enabled = 0 WHERE id = :id');
            $db->bind(':id', $userId);

            if (!$db->execute()) {
                return ['status' => 'error', 'message' => 'Failed to disable 2FA.'];
            }

            require_once __DIR__ . '/CredentialVersion.php';
            CredentialVersion::bump($userId);

            return ['status' => 'success', 'message' => 'Two-factor authentication disabled.'];
        } catch (Throwable $e) {
            error_log('TwoFactorAuth::disable failed: ' . $e->getMessage());
            return ['status' => 'error', 'message' => 'Failed to disable 2FA.'];
        }
    }

    /**
     * Admin recovery: disable 2FA for another user (no TOTP/password from target).
     */
    public static function adminDisable(int $userId): array
    {
        try {
            $db = new Database();
            $db->query('SELECT totp_enabled FROM users WHERE id = :id AND deleted_at IS NULL LIMIT 1');
            $db->bind(':id', $userId);
            $row = $db->single();

            if (!$row) {
                return ['status' => 'error', 'message' => 'User not found.'];
            }

            if (!(bool)($row['totp_enabled'] ?? false)) {
                return ['status' => 'error', 'message' => 'Two-factor authentication is not enabled for this user.'];
            }

            $db->query('UPDATE users SET totp_secret = NULL, totp_enabled = 0 WHERE id = :id');
            $db->bind(':id', $userId);

            if (!$db->execute()) {
                return ['status' => 'error', 'message' => 'Failed to disable 2FA.'];
            }

            require_once __DIR__ . '/CredentialVersion.php';
            CredentialVersion::bump($userId);

            return ['status' => 'success', 'message' => 'Two-factor authentication has been disabled for this user.'];
        } catch (Throwable $e) {
            error_log('TwoFactorAuth::adminDisable failed: ' . $e->getMessage());
            return ['status' => 'error', 'message' => 'Failed to disable 2FA.'];
        }
    }

    public static function getStatus(int $userId): array
    {
        $row = self::getSecretRow($userId);

        return [
            'enabled'       => (bool)($row['totp_enabled'] ?? false),
            'pending_setup' => !empty($row['totp_secret']) && empty($row['totp_enabled']),
            'secret'        => (!empty($row['totp_secret']) && empty($row['totp_enabled']))
                ? (string)$row['totp_secret']
                : null,
            'username'      => (string)($row['username'] ?? ''),
        ];
    }

    private static function getPendingSecret(int $userId): ?string
    {
        $row = self::getSecretRow($userId);
        if (!$row || (bool)$row['totp_enabled'] || empty($row['totp_secret'])) {
            return null;
        }

        return (string)$row['totp_secret'];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function getSecretRow(int $userId): ?array
    {
        try {
            $db = new Database();
            $db->query('
                SELECT username, totp_secret, totp_enabled
                FROM users
                WHERE id = :id
                LIMIT 1
            ');
            $db->bind(':id', $userId);
            $row = $db->single();

            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }
}
