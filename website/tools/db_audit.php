<?php

$host = 'localhost';
$db   = 'lugasku';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

$tables = ['users', 'organization_members', 'chats', 'chat_rooms', 'chat_room_members', 'user_tokens', 'user_devices'];

echo "=== M. ACTUAL ROW COUNTS ===\n";
foreach ($tables as $t) {
    $stmt = $pdo->query("SELECT COUNT(*) as c FROM $t");
    $row = $stmt->fetch();
    echo "$t: " . $row['c'] . "\n";
}

echo "\n=== N. SHOW INDEX — RAW EVIDENCE ===\n";
foreach ($tables as $t) {
    echo "Table: $t\n";
    $stmt = $pdo->query("SHOW INDEX FROM $t");
    while ($row = $stmt->fetch()) {
        echo "  - Index: {$row['Key_name']} | Unique: " . ($row['Non_unique'] == 0 ? 'Yes' : 'No') . " | Column: {$row['Column_name']} | Seq: {$row['Seq_in_index']} | Cardinality: {$row['Cardinality']}\n";
    }
}

echo "\n=== D. MEMBERSHIP UNIQUENESS ===\n";
$stmt = $pdo->query("SELECT karang_taruna_id, user_id, COUNT(*) as c FROM organization_members GROUP BY karang_taruna_id, user_id HAVING c > 1");
$dups = $stmt->fetchAll();
if (count($dups) > 0) {
    echo "Found " . count($dups) . " duplicate memberships.\n";
} else {
    echo "No duplicate memberships found.\n";
}

echo "\n=== H. CHAT_ROOM_MEMBERS UNIQUENESS ===\n";
$stmt = $pdo->query("SELECT chat_room_id, user_id, COUNT(*) as c FROM chat_room_members GROUP BY chat_room_id, user_id HAVING c > 1");
$dups = $stmt->fetchAll();
if (count($dups) > 0) {
    echo "Found " . count($dups) . " duplicate chat room memberships.\n";
} else {
    echo "No duplicate chat room memberships found.\n";
}

echo "\n=== O. REAL EXPLAIN MATRIX ===\n";

$queries = [
    "1. Auth token lookup" => "EXPLAIN SELECT * FROM user_tokens WHERE token_hash = 'hash' AND expires_at > NOW()",
    "2. Active membership validation" => "EXPLAIN SELECT * FROM organization_members WHERE user_id = 1 AND karang_taruna_id = 1 AND status_aktif = 1",
    "3. Active member list for tenant" => "EXPLAIN SELECT * FROM organization_members WHERE karang_taruna_id = 1 AND status_aktif = 1",
    "4. Private receiver validation" => "EXPLAIN SELECT * FROM organization_members WHERE user_id = 2 AND karang_taruna_id = 1 AND status_aktif = 1",
    "5. Custom-room membership validation" => "EXPLAIN SELECT * FROM chat_room_members WHERE chat_room_id = 1 AND user_id = 1",
    "6. Default-room lookup" => "EXPLAIN SELECT * FROM chat_rooms WHERE karang_taruna_id = 1 AND type = 'default'",
    "7. User room list" => "EXPLAIN SELECT * FROM chat_room_members WHERE user_id = 1",
    "8. Private history" => "EXPLAIN SELECT chats.*, users.nama_lengkap, users.role_level, users.profile_photo FROM chats JOIN users ON users.id = chats.sender_id WHERE chats.karang_taruna_id = 1 AND chats.type = 'private' AND ((chats.sender_id = 1 AND chats.receiver_id = 2) OR (chats.sender_id = 2 AND chats.receiver_id = 1)) AND chats.created_at >= '2026-08-12 00:00:00' ORDER BY chats.id DESC LIMIT 50",
    "9. Group history" => "EXPLAIN SELECT chats.*, users.nama_lengkap, users.role_level, users.profile_photo FROM chats JOIN users ON users.id = chats.sender_id WHERE chats.chat_room_id = 1 AND chats.created_at >= '2026-08-12 00:00:00' ORDER BY chats.id DESC LIMIT 50",
    "10. Private contacts" => "EXPLAIN SELECT u.id as contact_id, u.nama_lengkap, m.message, m.created_at FROM users u JOIN organization_members om ON u.id = om.user_id AND om.karang_taruna_id = 1 AND om.status_aktif = 1 JOIN (SELECT CASE WHEN sender_id = 1 THEN receiver_id ELSE sender_id END as contact_id, MAX(id) as max_id FROM chats WHERE type = 'private' AND karang_taruna_id = 1 AND (sender_id = 1 OR receiver_id = 1) GROUP BY CASE WHEN sender_id = 1 THEN receiver_id ELSE sender_id END) last_chat ON u.id = last_chat.contact_id JOIN chats m ON m.id = last_chat.max_id WHERE m.created_at >= '2026-08-12 00:00:00' ORDER BY m.id DESC LIMIT 500",
    "11. Cleanup selector" => "EXPLAIN SELECT id FROM chats WHERE created_at < '2026-08-12 00:00:00' ORDER BY created_at ASC LIMIT 1000",
    "12. FCM recipient lookup" => "EXPLAIN SELECT * FROM user_devices WHERE user_id IN (1, 2, 3)",
];

foreach ($queries as $name => $query) {
    echo "\n$name\n";
    try {
        $stmt = $pdo->query($query);
        $result = $stmt->fetchAll();
        foreach ($result as $row) {
            echo "  table: {$row['table']} | type: {$row['type']} | possible_keys: {$row['possible_keys']} | key: {$row['key']} | key_len: {$row['key_len']} | ref: {$row['ref']} | rows: {$row['rows']} | filtered: {$row['filtered']} | Extra: {$row['Extra']}\n";
        }
    } catch (\PDOException $e) {
        echo "  Error: " . $e->getMessage() . "\n";
    }
}
