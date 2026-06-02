<?php
require_once __DIR__ . '/config/database.php';

$ftp_server = 'ftpupload.net';
$ftp_user = 'if0_42011396';
$ftp_pass = 'Pjzc13804';

$conn = ftp_connect($ftp_server, 21, 30);
if (!$conn) die("No se pudo conectar\n");
if (!ftp_login($conn, $ftp_user, $ftp_pass)) die("No se pudo autenticar\n");
ftp_pasv($conn, true);

echo "Conectado\n";

// Mover archivos de proyectoceesar a htdocs temporalmente
// Primero renombramos htdocs a htdocs_old
if (ftp_rename($conn, '/htdocs', '/htdocs_old')) {
    echo "htdocs -> htdocs_old\n";
} else {
    echo "No se pudo renombrar htdocs\n";
    // Intentamos crear htdocs vacio
    @ftp_mkdir($conn, '/htdocs');
}

// Renombramos proyectoceesar a htdocs
if (ftp_rename($conn, '/htdocs_old/proyectocesar', '/htdocs')) {
    echo "proyectocesar -> htdocs\n";
} else {
    echo "ERROR: No se pudo mover proyectoceesar\n";
    // Revertir
    ftp_rename($conn, '/htdocs_old', '/htdocs');
    exit;
}

// Actualizar database.php con BASE_URL vacio para raiz
$config = file_get_contents(__DIR__ . '/config/database.php');
$config = str_replace("define('BASE_URL', '/proyectocesar')", "define('BASE_URL', '')", $config);
$config = str_replace("define('BASE_URL', '/proyectocesar');", "define('BASE_URL', '');", $config);
file_put_contents('/tmp/remote_db_root.php', $config);

if (ftp_put($conn, '/htdocs/config/database.php', '/tmp/remote_db_root.php', FTP_BINARY)) {
    echo "database.php actualizado (BASE_URL vacio)\n";
}

ftp_close($conn);
echo "=== COMPLETADO ===\n";
echo "Visita: http://proyectocesar.infinityfree.me/login.php\n";
