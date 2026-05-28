<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$rate = getExchangeRate();
$manual = isset($_GET['rate']) ? floatval($_GET['rate']) : 0;

echo json_encode([
    'rate' => $manual > 0 ? $manual : $rate,
    'source' => $manual > 0 ? 'manual' : ($rate > 0 ? 'api' : 'none')
]);
