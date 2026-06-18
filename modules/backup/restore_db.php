<?php
require_once __DIR__ . '/../../config/database.php';
if (!isAdmin()) { $_SESSION['error'] = 'Acceso denegado'; redirect('/dashboard.php'); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['sql_file'])) {
    $_SESSION['error'] = 'No se seleccion&oacute; ning&uacute;n archivo';
    redirect('/modules/backup/index.php');
}

$file = $_FILES['sql_file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['error'] = 'Error al subir el archivo';
    redirect('/modules/backup/index.php');
}

if (pathinfo($file['name'], PATHINFO_EXTENSION) !== 'sql') {
    $_SESSION['error'] = 'Solo se permiten archivos .sql';
    redirect('/modules/backup/index.php');
}

$sql = file_get_contents($file['tmp_name']);
if (empty($sql)) {
    $_SESSION['error'] = 'Archivo vac&iacute;o';
    redirect('/modules/backup/index.php');
}

$db = getDB();

// Disable foreign key checks
$db->query("SET FOREIGN_KEY_CHECKS = 0");

// Execute multi-query
if ($db->multi_query($sql)) {
    do {
        if ($result = $db->store_result()) $result->free();
    } while ($db->next_result());
}

$db->query("SET FOREIGN_KEY_CHECKS = 1");

$_SESSION['success'] = 'Base de datos restaurada exitosamente';
redirect('/modules/backup/index.php');
