<?php
$ch = curl_init('http://localhost:8080/api/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'username' => 'admin',
    'password' => 'newpassword123'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
$response = curl_exec($ch);
curl_close($ch);
$data = json_decode($response, true);
if (!isset($data['data']['token'])) {
    die("Login failed: " . $response);
}
$token = $data['data']['token'];

// Now simulate POST /api/profile/password
$ch2 = curl_init('http://localhost:8080/api/profile/password');
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_POST, true);
curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode([
    'old_password' => 'newpassword123',
    'new_password' => 'newpassword1234',
    'confirm_password' => 'newpassword1234'
]));
curl_setopt($ch2, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $token",
    'Content-Type: application/json'
]);
$res2 = curl_exec($ch2);
$code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
echo "HTTP: $code2\n";
echo "Response: $res2\n";
