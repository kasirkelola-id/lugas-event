<?php
$pdo = new PDO("mysql:host=localhost;dbname=lugasku;charset=utf8mb4", "root", "", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

$pdo->exec("DELETE FROM chat_room_members; DELETE FROM chat_rooms; DELETE FROM chats; DELETE FROM organization_members; DELETE FROM users; DELETE FROM user_tokens; DELETE FROM user_devices; DELETE FROM karang_taruna;");

$pdo->exec("INSERT INTO karang_taruna (id, nama_organisasi, kode_pin) VALUES (1, 'Org 1', '123'), (2, 'Org 2', '456')");

$pdo->exec("INSERT INTO users (id, nama_lengkap, username, password) VALUES (1, 'User 1', 'u1', 'pass'), (2, 'User 2', 'u2', 'pass'), (3, 'User 3', 'u3', 'pass')");

$pdo->exec("INSERT INTO organization_members (user_id, karang_taruna_id, status_aktif, role_level) VALUES (1, 1, 1, 'anggota'), (2, 1, 1, 'anggota'), (3, 2, 1, 'anggota')");

$pdo->exec("INSERT INTO chat_rooms (id, karang_taruna_id, name, type) VALUES (1, 1, 'Default', 'default'), (2, 1, 'Custom', 'custom')");

$pdo->exec("INSERT INTO chat_room_members (chat_room_id, user_id) VALUES (2, 1), (2, 2)");

$pdo->exec("INSERT INTO chats (id, karang_taruna_id, type, sender_id, receiver_id, chat_room_id, message, created_at) VALUES 
(1, 1, 'private', 1, 2, NULL, 'Hello', '2026-09-10 00:00:00'),
(2, 1, 'private', 2, 1, NULL, 'Hi', '2026-09-10 00:01:00'),
(3, 1, 'group', 1, NULL, 2, 'Hello Group', '2026-09-10 00:02:00')");

$pdo->exec("INSERT INTO user_tokens (id, user_id, karang_taruna_id, token_hash, expires_at) VALUES (1, 1, 1, 'hash', '2027-01-01 00:00:00')");

$pdo->exec("INSERT INTO user_devices (id, user_id, fcm_token) VALUES (1, 1, 'fcm1'), (2, 2, 'fcm2')");

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

echo "Dummy data seeded.\n";
