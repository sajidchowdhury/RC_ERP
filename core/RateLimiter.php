<?php
// core/RateLimiter.php — session-based rate limiting (login + JSON APIs)

class RateLimiter
{
    private int $maxAttempts;
    private int $windowSeconds;
    private string $prefix;

    public function __construct(int $maxAttempts = 5, int $windowSeconds = 900, string $prefix = 'login:')
    {
        $this->maxAttempts = max(1, $maxAttempts);
        $this->windowSeconds = max(1, $windowSeconds);
        $this->prefix = $prefix;
    }

    /**
     * Check whether the identifier is blocked (does not increment the counter).
     */
    public function isLimited(string $identifier): bool
    {
        $bucket = self::readBucket($this->bucketKey($identifier));
        return (int)($bucket['count'] ?? 0) >= $this->maxAttempts;
    }

    /**
     * Record a failed attempt (e.g. wrong password).
     */
    public function recordFailure(string $identifier): void
    {
        self::incrementBucket($this->bucketKey($identifier), $this->windowSeconds);
    }

    /**
     * Clear attempts after successful authentication.
     */
    public function clear(string $identifier): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $key = $this->bucketKey($identifier);
        if (isset($_SESSION['_rate_limits'][$key])) {
            unset($_SESSION['_rate_limits'][$key]);
        }
    }

    /**
     * JSON API guard: increment and return whether the request is allowed.
     *
     * @return array{allowed: bool, retry_after: int}
     */
    public static function attempt(string $key, int $maxAttempts, int $windowSeconds): array
    {
        if ($maxAttempts < 1 || $windowSeconds < 1) {
            return ['allowed' => true, 'retry_after' => 0];
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            return ['allowed' => true, 'retry_after' => 0];
        }

        $bucket = self::incrementBucket($key, $windowSeconds);
        if ((int)($bucket['count'] ?? 0) > $maxAttempts) {
            return [
                'allowed'     => false,
                'retry_after' => max(1, (int)($bucket['reset_at'] ?? time()) - time()),
            ];
        }

        return ['allowed' => true, 'retry_after' => 0];
    }

    private function bucketKey(string $identifier): string
    {
        return $this->prefix . strtolower(trim($identifier));
    }

    /**
     * @return array{count: int, reset_at: int}
     */
    private static function readBucket(string $key): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return ['count' => 0, 'reset_at' => time()];
        }

        $store = &$_SESSION['_rate_limits'];
        if (!is_array($store)) {
            $store = [];
        }

        $now = time();
        $bucket = $store[$key] ?? ['count' => 0, 'reset_at' => $now];
        if ($now >= (int)($bucket['reset_at'] ?? 0)) {
            return ['count' => 0, 'reset_at' => $now];
        }

        return [
            'count'    => (int)($bucket['count'] ?? 0),
            'reset_at' => (int)($bucket['reset_at'] ?? $now),
        ];
    }

    /**
     * @return array{count: int, reset_at: int}
     */
    private static function incrementBucket(string $key, int $windowSeconds): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return ['count' => 0, 'reset_at' => time()];
        }

        $store = &$_SESSION['_rate_limits'];
        if (!is_array($store)) {
            $store = [];
        }

        $now = time();
        $bucket = $store[$key] ?? ['count' => 0, 'reset_at' => $now + $windowSeconds];
        if ($now >= (int)($bucket['reset_at'] ?? 0)) {
            $bucket = ['count' => 0, 'reset_at' => $now + $windowSeconds];
        }

        $bucket['count'] = (int)($bucket['count'] ?? 0) + 1;
        $store[$key] = $bucket;

        return $bucket;
    }
}