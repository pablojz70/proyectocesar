<?php
require_once '../../config/database.php';
if (!isAdmin()) { $_SESSION['error'] = 'Acceso denegado'; redirect('/dashboard.php'); }
$db = getDB();
$id = intval($_GET['id'] ?? 0);
if ($id == $_SESSION['user_id']) { $_SESSION['error'] = 'No puedes eliminarte a ti mismo'; redirect('/modules/users/list.php'); }
$stmt = $db->prepare("DELETE FROM users WHERE id=?");
$stmt->bind_param("i", $id);
if ($stmt->execute() && $stmt->affected_rows > 0) {
    $_SESSION['success'] = 'Usuario eliminado';
} else {
    $_SESSION['error'] = 'No se pudo eliminar';
}
redirect('/modules/users/list.php');
