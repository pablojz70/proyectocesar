<?php
// Deploy directo: sube archivos desde LOCAL a la carpeta del dominio
$ftp = ftp_connect('ftpupload.net', 21, 30);
if (!$ftp) die("No conecta\n");
if (!ftp_login($ftp, 'if0_42011396', 'Pjzc13804')) die("No auth\n");
ftp_pasv($ftp, true);
echo "Conectado\n";

$remote_base = '/proyectocesar.infinityfree.me/htdocs';
$local_base = __DIR__;

// Crear directorios
$dirs = ['', '/config', '/assets', '/assets/css', '/assets/js', '/includes',
    '/modules', '/modules/clients', '/modules/products', '/modules/sales',
    '/modules/payments', '/modules/loans', '/modules/reports', '/modules/users',
    '/api', '/imagen', '/uploads', '/uploads/products'];

foreach ($dirs as $dir) {
    @ftp_mkdir($ftp, $remote_base . $dir);
}

// Subir archivos
$files = [
    'login.php', 'logout.php', 'index.php', 'dashboard.php',
    'config/database.php',
    'assets/css/style.css', 'assets/js/main.js',
    'includes/header.php', 'includes/footer.php',
    'modules/clients/list.php', 'modules/clients/create.php', 'modules/clients/edit.php', 'modules/clients/delete.php',
    'modules/products/list.php', 'modules/products/create.php', 'modules/products/edit.php', 'modules/products/delete.php',
    'modules/sales/register.php', 'modules/sales/history.php', 'modules/sales/detail.php', 'modules/sales/edit.php', 'modules/sales/delete.php',
    'modules/payments/consult_debt.php', 'modules/payments/register_payment.php',
    'modules/loans/register.php', 'modules/loans/history.php', 'modules/loans/collect.php', 'modules/loans/report.php',
    'modules/reports/sales.php', 'modules/reports/profitability.php', 'modules/reports/inventory.php', 'modules/reports/collections.php',
    'modules/users/list.php', 'modules/users/create.php', 'modules/users/edit.php', 'modules/users/delete.php',
    'api/create_client.php', 'api/get_exchange_rate.php',
    'imagen/1inicio.png', 'imagen/2clientes.png', 'imagen/3productos.png', 'imagen/4vender.png',
    'imagen/5ventas.png', 'imagen/6cobranza.png', 'imagen/7prestamos.png', 'imagen/8historial.png',
    'imagen/9reportes.png', 'imagen/10usuarios.png'
];

$count = 0;
foreach ($files as $f) {
    $local = "$local_base/$f";
    $remote = "$remote_base/$f";
    if (file_exists($local)) {
        if (ftp_put($ftp, $remote, $local, FTP_BINARY)) {
            echo "OK: $f\n";
            $count++;
        } else {
            echo "FAIL: $f\n";
        }
    } else {
        echo "SKIP (not found): $f\n";
    }
}

// Subir database.php configurado para InfinityFree
$config = file_get_contents("$local_base/config/database.php");
$config = preg_replace("/define\('BASE_URL',\s*'[^']*'\)/", "define('BASE_URL', '')", $config);
$config = preg_replace("/define\('DB_HOST',\s*'[^']*'\)/", "define('DB_HOST', 'sql105.infinityfree.com')", $config);
$config = preg_replace("/define\('DB_USER',\s*'[^']*'\)/", "define('DB_USER', 'if0_42011396')", $config);
$config = preg_replace("/define\('DB_PASS',\s*'[^']*'\)/", "define('DB_PASS', 'Pjzc13804')", $config);
$config = preg_replace("/define\('DB_NAME',\s*'[^']*'\)/", "define('DB_NAME', 'if0_42011396_sistema_ventas_creditos')", $config);
file_put_contents('/tmp/remote_db.php', $config);
ftp_put($ftp, "$remote_base/config/database.php", '/tmp/remote_db.php', FTP_BINARY);
echo "OK: config/database.php (InfinityFree)\n";

ftp_close($ftp);
echo "\nSubidos $count archivos\n";
echo "Visita: http://proyectocesar.infinityfree.me/login.php\n";
