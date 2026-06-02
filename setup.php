<?php
/**
 * Script de instalacion remota - ejecutar en InfinityFree
 * Visita: http://proyectocesar.infinityfree.me/proyectocesar/setup.php
 */
echo "<h2>Instalacion del Sistema de Ventas y Creditos</h2>";

// Tus datos MySQL de InfinityFree (CAMBIAR SEGUN TU PANEL)
$host = 'sql105.infinityfree.com';
$user = 'if0_42011396';
$pass = 'Pjzc13804';
$dbname = 'if0_42011396_sistema_ventas_creditos';

echo "<p>Conectando a MySQL...</p>";
$conn = new mysqli($host, $user, $pass, '', 3306);
if ($conn->connect_error) {
    die("<p style='color:red'>Error: " . $conn->connect_error . "</p>");
}

// Crear BD si no existe
$conn->query("CREATE DATABASE IF NOT EXISTS `$dbname` DEFAULT CHARACTER SET utf8mb4 DEFAULT COLLATE utf8mb4_unicode_ci");
$conn->select_db($dbname);

// Leer SQL
$sql = file_get_contents(__DIR__ . '/db_import.sql');
if (!$sql) {
    // Si no hay archivo, crear tablas desde cero
    $sql = "
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin','seller') DEFAULT 'seller',
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    cedula_rif VARCHAR(20) NOT NULL,
    phone VARCHAR(20),
    observations TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_cedula_per_user (user_id, cedula_rif)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    stock INT NOT NULL DEFAULT 0,
    price_efectivo DECIMAL(12,2) NOT NULL,
    price_euro DECIMAL(12,2) NOT NULL,
    price_bcv DECIMAL(12,2) NOT NULL,
    foto VARCHAR(255),
    archivo VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    client_id INT NOT NULL,
    sale_type ENUM('contado','credito') NOT NULL,
    payment_currency ENUM('EFECTIVO','EURO','BCV') NOT NULL,
    exchange_rate DECIMAL(12,2),
    total_efectivo DECIMAL(12,2) NOT NULL,
    total_euro DECIMAL(12,2) NOT NULL,
    total_bs DECIMAL(12,2) NOT NULL,
    status ENUM('pagada','pendiente','parcial') DEFAULT 'pendiente',
    installments INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (client_id) REFERENCES clients(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price_efectivo DECIMAL(12,2) NOT NULL,
    unit_price_euro DECIMAL(12,2) NOT NULL,
    unit_price_bs DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    amount_efectivo DECIMAL(12,2) NOT NULL,
    amount_euro DECIMAL(12,2) NOT NULL,
    amount_bs DECIMAL(12,2) NOT NULL,
    exchange_rate DECIMAL(12,2),
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES sales(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS loans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    client_id INT NOT NULL,
    currency ENUM('EUR','BS') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    interest_rate DECIMAL(5,2) NOT NULL,
    term_months INT NOT NULL,
    total_interest DECIMAL(12,2) NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL,
    monthly_payment DECIMAL(12,2) NOT NULL,
    start_date DATE NOT NULL,
    status ENUM('activo','pagado','vencido') DEFAULT 'activo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (client_id) REFERENCES clients(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS loan_installments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL,
    installment_number INT NOT NULL,
    due_date DATE NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    status ENUM('pendiente','pagada','vencida') DEFAULT 'pendiente',
    paid_date DATE,
    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT IGNORE INTO users (username, password, full_name, role) VALUES
('admin', '\$2y\$10\$xbL9ZqDYxmVjyMwxwmF/8ed2r7DKFuFmpJqxIoAIA77N/SCRCWIxm', 'Administrador', 'admin'),
('vendedor1', '\$2y\$10\$xbL9ZqDYxmVjyMwxwmF/8ed2r7DKFuFmpJqxIoAIA77N/SCRCWIxm', 'Vendedor Demo', 'seller');
";
}

// Ejecutar SQL
if ($conn->multi_query($sql)) {
    do {
        if ($result = $conn->store_result()) $result->free();
    } while ($conn->next_result());
}

// Verificar tablas
$tables = $conn->query("SHOW TABLES");
echo "<p>Tablas creadas:</p><ul>";
while ($t = $tables->fetch_row()) echo "<li>$t[0]</li>";
echo "</ul>";

echo "<p style='color:green'>Base de datos instalada correctamente</p>";

// Configurar database.php
$config = "<?php
define('DB_HOST', '$host');
define('DB_USER', '$user');
define('DB_PASS', '$pass');
define('DB_NAME', '$dbname');
define('DB_PORT', 3306);
define('BASE_URL', '/proyectocesar');

function getDB() {
    static \$db = null;
    if (\$db === null) {
        try {
            \$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
            if (\$db->connect_error) die('Error de conexion: ' . \$db->connect_error);
            \$db->set_charset('utf8mb4');
        } catch (Exception \$e) {
            die('Error de conexion: ' . \$e->getMessage());
        }
    }
    return \$db;
}

function isAdmin() {
    return isset(\$_SESSION['user_role']) && \$_SESSION['user_role'] === 'admin';
}
function isSeller() {
    return isset(\$_SESSION['user_role']) && \$_SESSION['user_role'] === 'seller';
}
function redirect(\$url) {
    if (!headers_sent()) {
        header('Location: ' . BASE_URL . \$url);
    } else {
        echo '<script>window.location.href=\"' . BASE_URL . \$url . '\";</script>';
    }
    exit;
}
function h(\$text) {
    return htmlspecialchars(\$text, ENT_QUOTES, 'UTF-8');
}
function getExchangeRate() {
    \$rate = 0;
    \$api_url = 'https://ve.dolarapi.com/v1/dolares/oficial';
    \$ch = curl_init();
    curl_setopt(\$ch, CURLOPT_URL, \$api_url);
    curl_setopt(\$ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt(\$ch, CURLOPT_TIMEOUT, 5);
    curl_setopt(\$ch, CURLOPT_SSL_VERIFYPEER, false);
    \$response = curl_exec(\$ch);
    \$http_code = curl_getinfo(\$ch, CURLINFO_HTTP_CODE);
    curl_close(\$ch);
    if (\$http_code === 200 && \$response) {
        \$data = json_decode(\$response, true);
        if (isset(\$data['promedio'])) \$rate = floatval(\$data['promedio']);
        elseif (isset(\$data['promedioReal'])) \$rate = floatval(\$data['promedioReal']);
        elseif (isset(\$data['precio'])) \$rate = floatval(\$data['precio']);
    }
    return \$rate > 0 ? \$rate : 0;
}
session_start();
";

file_put_contents(__DIR__ . '/config/database.php', $config);
echo "<p style='color:green'>config/database.php actualizado</p>";

echo "<hr><p><a href='login.php'>Ir al login</a></p>";
echo "<p><strong>Usuarios:</strong> admin / password - vendedor1 / password</p>";
