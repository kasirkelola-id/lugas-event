<?php
// Standalone policy probe: no application bootstrap, dotenv, database or network.
define('ENVIRONMENT', 'production');
require __DIR__ . '/../../app/Services/InternalSecret.php';
$results = [];
foreach ([null, '', 'default_internal_secret_for_dev', 'synthetic-private-value'] as $value) {
    putenv($value === null ? 'INTERNAL_API_SECRET' : 'INTERNAL_API_SECRET=' . $value);
    $results[] = \App\Services\InternalSecret::configured() !== null;
}
$results[] = \App\Services\InternalSecret::accepts('synthetic-wrong-value');
$results[] = \App\Services\InternalSecret::accepts('synthetic-private-value');
echo json_encode($results);
