<?php
$pdo = new PDO("mysql:host=localhost;dbname=kartar_staging;charset=utf8mb4", "root", "", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

function dump_index($pdo, $table) {
    echo "\n=== SHOW INDEX FROM $table ===\n";
    $stmt = $pdo->query("SHOW INDEX FROM $table");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Key_name: {$row['Key_name']} | Column_name: {$row['Column_name']} | Non_unique: {$row['Non_unique']} | Seq_in_index: {$row['Seq_in_index']}\n";
    }
}

function explain_query($pdo, $label, $query) {
    echo "\n--- $label ---\n";
    $stmt = $pdo->query("EXPLAIN $query");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $filtered = isset($row['filtered']) ? $row['filtered'] : 'N/A';
        echo "table: {$row['table']}\n";
        echo "type: {$row['type']}\n";
        echo "possible_keys: {$row['possible_keys']}\n";
        echo "key: {$row['key']}\n";
        echo "key_len: {$row['key_len']}\n";
        echo "rows: {$row['rows']}\n";
        echo "filtered: {$filtered}\n";
        echo "Extra: {$row['Extra']}\n";
        echo "----------\n";
    }
}

$tables = ['users', 'organization_members', 'chats', 'chat_rooms', 'chat_room_members', 'user_tokens', 'user_devices'];
foreach ($tables as $t) {
    dump_index($pdo, $t);
}

echo "\n=== EXPLAIN MATRIX ===\n";

explain_query($pdo, "A/E. Active membership / Private receiver validation", 
    "SELECT * FROM organization_members WHERE user_id = 1 AND karang_taruna_id = 1 AND status_aktif = 1");

explain_query($pdo, "B. Tenant active member list", 
    "SELECT * FROM organization_members WHERE karang_taruna_id = 1 AND status_aktif = 1");

explain_query($pdo, "C. Custom room membership", 
    "SELECT * FROM chat_room_members WHERE chat_room_id = 1 AND user_id = 1");

explain_query($pdo, "D. Default room lookup", 
    "SELECT * FROM chat_rooms WHERE karang_taruna_id = 1 AND type = 'default'");

$private_query = "
SELECT chats.*, users.nama_lengkap, users.role_level, users.profile_photo 
FROM (
    SELECT * FROM (
        SELECT * FROM chats 
        WHERE karang_taruna_id = 1 AND type = 'private' AND sender_id = 1 AND receiver_id = 2 AND created_at >= '2026-08-14 00:00:00' 
        ORDER BY id DESC LIMIT 50
    ) as branch1
    UNION ALL
    SELECT * FROM (
        SELECT * FROM chats 
        WHERE karang_taruna_id = 1 AND type = 'private' AND sender_id = 2 AND receiver_id = 1 AND created_at >= '2026-08-14 00:00:00' 
        ORDER BY id DESC LIMIT 50
    ) as branch2
) AS chats
JOIN users ON users.id = chats.sender_id
ORDER BY chats.id DESC LIMIT 50
";
explain_query($pdo, "F/G/H. Private history (UNION ALL)", $private_query);

explain_query($pdo, "I. Group history", 
    "SELECT * FROM chats WHERE chat_room_id = 1 AND created_at >= '2026-08-14' ORDER BY id DESC LIMIT 50");

explain_query($pdo, "J. Private contacts", 
    "SELECT 
        u.id as contact_id, 
        MAX(m.id) as max_id
    FROM users u
    JOIN organization_members om ON u.id = om.user_id AND om.karang_taruna_id = 1 AND om.status_aktif = 1
    JOIN (
        SELECT CASE WHEN sender_id = 1 THEN receiver_id ELSE sender_id END as contact_id, MAX(id) as max_id
        FROM chats WHERE type = 'private' AND karang_taruna_id = 1 AND (sender_id = 1 OR receiver_id = 1) AND created_at >= '2026-08-14'
        GROUP BY CASE WHEN sender_id = 1 THEN receiver_id ELSE sender_id END
    ) last_chat ON u.id = last_chat.contact_id
    JOIN chats m ON m.id = last_chat.max_id
    ORDER BY m.id DESC LIMIT 50");

explain_query($pdo, "K. Cleanup selector", 
    "SELECT id FROM chats WHERE created_at < '2026-08-14'");

$token = $pdo->query("SELECT token_hash FROM user_tokens LIMIT 1")->fetchColumn();
explain_query($pdo, "L. Auth token lookup", 
    "SELECT * FROM user_tokens WHERE token_hash = '$token'");

explain_query($pdo, "M. FCM recipient lookup", 
    "SELECT * FROM user_devices WHERE user_id IN (1, 2, 3)");
