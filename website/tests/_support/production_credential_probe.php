<?php

// Standalone policy probe: no CI app bootstrap, database, .env or transport.
define('ENVIRONMENT', 'production');
require dirname(__DIR__, 2) . '/vendor/autoload.php';

$defaultDenied = \App\Services\CredentialPolicy::verify('superadmin123', [
    'username' => 'superadmin', 'password' => password_hash('superadmin123', PASSWORD_BCRYPT),
]) === false;
$privateAccepted = \App\Services\CredentialPolicy::verify('private bootstrap passphrase', [
    'username' => 'superadmin', 'password' => password_hash('private bootstrap passphrase', PASSWORD_BCRYPT),
]);
$seedBlocked = false;
try {
    // If the production guard ever disappears this uninitialized seeder fails;
    // it cannot acquire a database or silently seed application data.
    (new ReflectionClass(\App\Database\Seeds\UserSeeder::class))->newInstanceWithoutConstructor()->run();
} catch (RuntimeException $e) {
    $seedBlocked = $e->getMessage() === 'Demo credentials may only be seeded in testing.';
}
echo json_encode(['default_denied' => $defaultDenied, 'private_accepted' => $privateAccepted, 'seed_blocked' => $seedBlocked]);
