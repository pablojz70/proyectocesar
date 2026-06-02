<?php
// Deploy: mover archivos a la raiz FTP para que el dominio los encuentre
$ftp = ftp_connect('ftpupload.net', 21, 30);
if (!$ftp) die("No conecta\n");
if (!ftp_login($ftp, 'if0_42011396', 'Pjzc13804')) die("No auth\n");
ftp_pasv($ftp, true);
echo "Conectado\n";

// Lista de archivos/carpetas viejos en la raiz que debemos reemplazar
$old_items = ['.git', 'assets', 'config', 'controllers', 'db', 'helpers', 
              'imagen', 'models', 'views', 'index.php', '.htaccess', 
              'mig.php', 'mig2.php', 'sw.js', 'manifest.php', 'manifest.json',
              '.override', 'DO NOT UPLOAD FILES HERE'];

// Renombrar cada uno a .old (por si acaso)
foreach ($old_items as $item) {
    if (ftp_size($ftp, "/$item") !== -1 || ftp_size($ftp, "/$item") !== false) {
        @ftp_rename($ftp, "/$item", "/{$item}.old");
        echo "Renombrado: $item -> {$item}.old\n";
    }
}

// Renombrar .htaccess
@ftp_rename($ftp, '/.htaccess', '/.htaccess.old');
echo "Renombrado .htaccess\n";

// Copiar todos los archivos de htdocs a la raiz
function ftp_copy_dir($ftp, $src, $dst) {
    @ftp_mkdir($ftp, $dst);
    $files = ftp_nlist($ftp, $src);
    if (!$files) return;
    foreach ($files as $f) {
        $name = basename($f);
        if ($name == '.' || $name == '..') continue;
        $src_path = "$src/$name";
        $dst_path = "$dst/$name";
        if (ftp_size($ftp, $src_path) == -1) {
            // Es directorio
            @ftp_mkdir($ftp, $dst_path);
            ftp_copy_dir($ftp, $src_path, $dst_path);
        } else {
            ftp_get($ftp, "/tmp/ftp_temp", $src_path, FTP_BINARY);
            ftp_put($ftp, $dst_path, "/tmp/ftp_temp", FTP_BINARY);
            echo "  $dst_path\n";
        }
    }
}

echo "Copiando archivos de /htdocs a /\n";
ftp_copy_dir($ftp, '/htdocs', '/');

// Actualizar database.php en la raiz con BASE_URL vacio
$config = file_get_contents('/opt/lampp/htdocs/proyectocesar/config/database.php');
$config = str_replace("define('BASE_URL', '/proyectocesar')", "define('BASE_URL', '')", $config);
$config = str_replace("define('BASE_URL', '/proyectocesar');", "define('BASE_URL', '');", $config);
// Cambiar credenciales para InfinityFree
$config = str_replace("define('DB_HOST', 'localhost')", "define('DB_HOST', 'sql105.infinityfree.com')", $config);
$config = str_replace("define('DB_USER', 'root')", "define('DB_USER', 'if0_42011396')", $config);
$config = str_replace("define('DB_PASS', '')", "define('DB_PASS', 'Pjzc13804')", $config);
$config = str_replace("define('DB_NAME', 'sistema_ventas_creditos')", "define('DB_NAME', 'if0_42011396_sistema_ventas_creditos')", $config);
file_put_contents('/tmp/remote_db_root.php', $config);
ftp_put($ftp, '/config/database.php', '/tmp/remote_db_root.php', FTP_BINARY);
echo "database.php actualizado\n";

ftp_close($ftp);
echo "\n=== COMPLETADO ===\n";
echo "Visita: http://proyectocesar.infinityfree.me/login.php\n";
