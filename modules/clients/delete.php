<?php
require_once '../../config/database.php';
$db = getDB();
$id = intval($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];
$is_admin = isAdmin();
$where = "1=1";

$stmt = $db->prepare("DELETE FROM clients WHERE id = ? AND $where");
$stmt->bind_param("i", $id);
if ($stmt->execute() && $stmt->affected_rows > 0) {
    $_SESSION['success'] = 'Cliente eliminado';
} else {
    $_SESSION['error'] = 'No se pudo eliminar el cliente';
}
redirect('/modules/clients/list.php');
