<?php
require_once __DIR__ . '/config/database.php';
$db = getDB();
$r = $db->query("SELECT id, username, full_name, role FROM users WHERE username='admin'");
if ($r && $u = $r->fetch_assoc()) {
    echo "Usuario encontrado: " . $u['username'] . " - " . $u['full_name'] . " - " . $u['role'] . "\n";
} else {
    echo "Usuario NO encontrado\n";
    echo "Error: " . $db->error . "\n";
}
// Check password verify
$r2 = $db->query("SELECT password FROM users WHERE username='admin'");
if ($r2 && $u2 = $r2->fetch_assoc()) {
    echo "Hash: " . $u2['password'] . "\n";
    echo "Verify password: " . (password_verify('password', $u2['password']) ? 'OK' : 'FAIL') . "\n";
}
