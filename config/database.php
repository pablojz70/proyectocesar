<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sistema_ventas_creditos');
define('DB_PORT', 3306);
define('BASE_URL', '/proyectocesar');

function getDB() {
    static $db = null;
    if ($db === null) {
        try {
            $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
            if ($db->connect_error) {
                die("Error de conexión: " . $db->connect_error);
            }
            $db->set_charset("utf8mb4");
        } catch (Exception $e) {
            die("Error de conexión: " . $e->getMessage());
        }
    }
    return $db;
}

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function isSeller() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'seller';
}

function redirect($url) {
    header("Location: " . BASE_URL . $url);
    exit;
}

function h($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function getExchangeRate() {
    $rate = 0;
    $api_url = "https://ve.dolarapi.com/v1/dolares/oficial";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['promedio'])) {
            $rate = floatval($data['promedio']);
        } elseif (isset($data['promedioReal'])) {
            $rate = floatval($data['promedioReal']);
        } elseif (isset($data['precio'])) {
            $rate = floatval($data['precio']);
        }
    }
    return $rate > 0 ? $rate : 0;
}

session_start();
