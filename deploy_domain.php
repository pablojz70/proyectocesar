<?php
// Deploy a la carpeta correcta del dominio
$ftp = ftp_connect('ftpupload.net', 21, 30);
if (!$ftp) die("No conecta\n");
if (!ftp_login($ftp, 'if0_42011396', 'Pjzc13804')) die("No auth\n");
ftp_pasv($ftp, true);
echo "Conectado\n";

// Crear la estructura de directorios del dominio
$domain_dir = '/proyectocesar.infinityfree.me/htdocs';
$dirs = ['', '/config', '/assets', '/assets/css', '/assets/js', '/includes',
    '/modules', '/modules/clients', '/modules/products', '/modules/sales',
    '/modules/payments', '/modules/loans', '/modules/reports', '/modules/users',
    '/api', '/imagen', '/uploads', '/uploads/products'];

foreach ($dirs as $dir) {
    $path = $domain_dir . $dir;
    @ftp_mkdir($ftp, $path);
    echo "Directorio: $path\n";
}

// Copiar archivos desde htdocs (donde estan actualmente)
function copy_dir($ftp, $src_base, $dst_base) {
    $items = ftp_nlist($ftp, $src_base);
    if (!$items) return;
    foreach ($items as $item) {
        $name = basename($item);
        if ($name == '.' || $name == '..') continue;
        $src = "$src_base/$name";
        $dst = "$dst_base/$name";
        if (ftp_size($ftp, $src) == -1) {
            @ftp_mkdir($ftp, $dst);
            copy_dir($ftp, $src, $dst);
        } else {
            // Download and re-upload
            $temp = tempnam(sys_get_temp_dir(), 'ftp_');
            if (ftp_get($ftp, $temp, $src, FTP_BINARY)) {
                ftp_put($ftp, $dst, $temp, FTP_BINARY);
                echo "  $dst\n";
            }
            unlink($temp);
        }
    }
}

echo "Copiando archivos de /htdocs a $domain_dir\n";
copy_dir($ftp, '/htdocs', $domain_dir);

// Subir database.php con configuracion correcta
$config = file_get_contents('/opt/lampp/htdocs/proyectocesar/config/database.php');
$config = str_replace("define('BASE_URL', '/proyectocesar')", "define('BASE_URL', '')", $config);
$config = str_replace("define('BASE_URL', '/proyectocesar');", "define('BASE_URL', '');", $config);
$config = str_replace("define('DB_HOST', 'localhost')", "define('DB_HOST', 'sql105.infinityfree.com')", $config);
$config = str_replace("define('DB_USER', 'root')", "define('DB_USER', 'if0_42011396')", $config);
$config = str_replace("define('DB_PASS', '')", "define('DB_PASS', 'Pjzc13804')", $config);
$config = str_replace("define('DB_NAME', 'sistema_ventas_creditos')", "define('DB_NAME', 'if0_42011396_sistema_ventas_creditos')", $config);
file_put_contents('/tmp/remote_db_domain.php', $config);
ftp_put($ftp, "$domain_dir/config/database.php", '/tmp/remote_db_domain.php', FTP_BINARY);
echo "database.php configurado\n";

ftp_close($ftp);
echo "\n=== COMPLETADO ===\n";
echo "Visita: http://proyectocesar.infinityfree.me/login.php\n";
