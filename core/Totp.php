<?php
// core/Totp.php — RFC 6238 TOTP (Google Authenticator compatible).

class Totp
{
    private const DIGITS = 6;
    private const PERIOD = 30;

    public static function generateSecret(int $length = 16): string
    {
        $bytes = random_bytes($length);
        return self::base32Encode($bytes);
    }

    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\s+/', '', trim($code));
        if ($code === '' || !ctype_digit($code) || strlen($code) !== self::DIGITS) {
            return false;
        }

        $timeSlice = (int)floor(time() / self::PERIOD);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::getCode($secret, $timeSlice + $i), $code)) {
                return true;
            }
        }

        return false;
    }

    public static function getCode(string $secret, ?int $timeSlice = null): string
    {
        $timeSlice = $timeSlice ?? (int)floor(time() / self::PERIOD);
        $secretKey = self::base32Decode($secret);
        $time = pack('N*', 0, $timeSlice);
        $hash = hash_hmac('sha1', $time, $secretKey, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $truncated = substr($hash, $offset, 4);
        $value = unpack('N', $truncated)[1] & 0x7FFFFFFF;

        return str_pad((string)($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    public static function provisioningUri(string $accountName, string $secret, string $issuer): string
    {
        $label = rawurlencode($issuer . ':' . $accountName);
        $issuerEnc = rawurlencode($issuer);
        $secretEnc = rawurlencode($secret);

        return "otpauth://totp/{$label}?secret={$secretEnc}&issuer={$issuerEnc}&digits=" . self::DIGITS . '&period=' . self::PERIOD;
    }

    private static function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';
        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($binary, 5) as $chunk) {
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }
            $encoded .= $alphabet[bindec($chunk)];
        }

        return $encoded;
    }

    private static function base32Decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret));
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';

        foreach (str_split($secret) as $char) {
            $pos = strpos($alphabet, $char);
            if ($pos === false) {
                continue;
            }
            $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';
        foreach (str_split($binary, 8) as $chunk) {
            if (strlen($chunk) !== 8) {
                continue;
            }
            $decoded .= chr(bindec($chunk));
        }

        return $decoded;
    }
}
