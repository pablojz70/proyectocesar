<?php
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$db = getDB();
$name = $db->real_escape_string($_POST['name'] ?? '');
$cedula = $db->real_escape_string($_POST['cedula_rif'] ?? '');
$phone = $db->real_escape_string($_POST['phone'] ?? '');
$user_id = $_SESSION['user_id'];

if (empty($name) || empty($cedula)) {
    echo json_encode(['ok' => false, 'error' => 'Nombre y cédula requeridos']);
    exit;
}

$check = $db->query("SELECT id FROM clients WHERE user_id = $user_id AND cedula_rif = '$cedula'");
if ($check->num_rows > 0) {
    echo json_encode(['ok' => false, 'error' => 'Ya existe un cliente con esa cédula']);
    exit;
}

$stmt = $db->prepare("INSERT INTO clients (user_id, name, cedula_rif, phone) VALUES (?, ?, ?, ?)");
$stmt->bind_param("isss", $user_id, $name, $cedula, $phone);
if ($stmt->execute()) {
    echo json_encode(['ok' => true, 'id' => $db->insert_id, 'name' => $name, 'cedula' => $cedula]);
} else {
    echo json_encode(['ok' => false, 'error' => $db->error]);
}
