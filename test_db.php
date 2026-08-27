<?php
$db = new mysqli('localhost', 'root', '', 'lugasku');
$db->query("UPDATE users SET password_must_change = 1 WHERE username = 'admin'");
$db->close();
echo "Updated\n";
