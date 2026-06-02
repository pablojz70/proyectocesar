<?php
/**
 * Script de despliegue vía FTP a InfinityFree
 * Uso: /opt/lampp/bin/php deploy.php
 */

$ftp_server = 'ftpupload.net';
$ftp_user = 'if0_42011396';
$ftp_pass = 'Pjzc13804';
$remote_root = '/htdocs/proyectocesar';
$local_root = __DIR__;

$conn = ftp_connect($ftp_server, 21, 30);
if (!$conn) die("No se pudo conectar\n");
if (!ftp_login($conn, $ftp_user, $ftp_pass)) die("No se pudo autenticar\n");
ftp_pasv($conn, true);

echo "Conectado a $ftp_server\n";

// Crear directorio remoto
$dirs = ['', '/config', '/assets', '/assets/css', '/assets/js', '/includes',
    '/modules', '/modules/clients', '/modules/products', '/modules/sales',
    '/modules/payments', '/modules/loans', '/modules/reports', '/modules/users',
    '/api', '/imagen', '/uploads', '/uploads/products', '/documento'];

foreach ($dirs as $dir) {
    $remote = $remote_root . $dir;
    @ftp_mkdir($conn, $remote);
    echo "Directorio: $remote\n";
}

// Subir archivos
$files = [];
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($local_root));
foreach ($rii as $file) {
    if ($file->isDir()) continue;
    if (strpos($file->getPathname(), '/.git') !== false) continue;
    if (strpos($file->getPathname(), '/node_modules') !== false) continue;
    if ($file->getExtension() === 'sql') continue;
    $local_path = $file->getPathname();
    $relative = substr($local_path, strlen($local_root));
    $remote_path = $remote_root . str_replace('\\', '/', $relative);
    $files[] = [$local_path, $remote_path];
}

$count = 0;
foreach ($files as $f) {
    if (ftp_put($conn, $f[1], $f[0], FTP_BINARY)) {
        echo "  OK: {$f[1]}\n";
        $count++;
    } else {
        echo "  FAIL: {$f[1]}\n";
    }
}

ftp_close($conn);
echo "\nSubidos $count archivos\n";
echo "=== COMPLETADO ===\n";
