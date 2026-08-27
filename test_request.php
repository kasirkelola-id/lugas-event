<?php
$ch = curl_init('http://localhost:8080/api/profile/password');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'old_password' => 'lugasjosjis',
    'new_password' => 'newpassword123',
    'confirm_password' => 'newpassword123'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer 5519ddbabeab8ec6623fe05419d60027c475bca0c9d3be6005b96fdb746f6857',
    'Content-Type: application/json'
]);
$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "HTTP: $httpcode\n";
echo "Response: $response\n";
