<?php
// CLI only. Supply KARTAR_DIAGNOSTIC_BEARER, KARTAR_DIAGNOSTIC_BASE_URL,
// KARTAR_DIAGNOSTIC_OLD_PASSWORD and KARTAR_DIAGNOSTIC_NEW_PASSWORD privately
// in the calling process environment. No application .env is loaded.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
$fail = static function (): never {
    fwrite(STDERR, "Diagnostic refused: explicit valid runtime inputs are required.\n");
    exit(1);
};
$bearer = trim((string) getenv('KARTAR_DIAGNOSTIC_BEARER'));
$base = trim((string) getenv('KARTAR_DIAGNOSTIC_BASE_URL'));
$oldPassword = getenv('KARTAR_DIAGNOSTIC_OLD_PASSWORD');
$newPassword = getenv('KARTAR_DIAGNOSTIC_NEW_PASSWORD');
if (!preg_match('/\A[A-Za-z0-9._~+\/-]{16,2048}={0,2}\z/D', $bearer)
    || !is_string($oldPassword) || $oldPassword === ''
    || !is_string($newPassword) || $newPassword === '') { $fail(); }
$url = parse_url($base);
if (!is_array($url) || !isset($url['scheme'], $url['host'])
    || !in_array($url['scheme'], ['http', 'https'], true)
    || isset($url['user'], $url['pass']) || isset($url['user']) || isset($url['pass'])
    || isset($url['query']) || isset($url['fragment'])
    || !in_array($url['path'] ?? '', ['', '/'], true)) { $fail(); }
$loopback = in_array(strtolower($url['host']), ['localhost', '127.0.0.1', '[::1]', '::1'], true);
// Remote targets require HTTPS and a separate explicit operator opt-in.
if (!$loopback && ($url['scheme'] !== 'https' || getenv('KARTAR_DIAGNOSTIC_ALLOW_REMOTE') !== '1')) { $fail(); }
if (!function_exists('curl_init')) { $fail(); }
$payload = ['old_password' => $oldPassword, 'new_password' => $newPassword, 'confirm_password' => $newPassword];
$ch = curl_init(rtrim($base, '/') . '/api/profile/password');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_CONNECTTIMEOUT => 3,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_PROXY => '',
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $bearer, 'Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
]);
$response = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$success = $response !== false && $status >= 200 && $status < 300;
// Response bodies and transport errors may contain credentials: never print them.
unset($bearer, $oldPassword, $newPassword, $payload, $response);
fwrite(STDOUT, 'HTTP status: ' . $status . "\n");
exit($success ? 0 : 1);
