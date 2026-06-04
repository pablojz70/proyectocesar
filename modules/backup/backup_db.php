<?php
require_once __DIR__ . '/../../config/database.php';
if (!isAdmin()) { die('Acceso denegado'); }

// Get all tables
$db = getDB();
$tables = [];
$result = $db->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

// Start SQL output
header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="respaldo_' . date('Y-m-d_H-i') . '.sql"');

echo "-- Respaldo del Sistema de Ventas y Creditos\n";
echo "-- Fecha: " . date('Y-m-d H:i') . "\n";
echo "-- Base de datos: " . DB_NAME . "\n\n";
echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

foreach ($tables as $table) {
    // Table structure
    $create = $db->query("SHOW CREATE TABLE `$table`")->fetch_row();
    echo "-- Estructura de tabla `$table`\n";
    echo "DROP TABLE IF EXISTS `$table`;\n";
    echo $create[1] . ";\n\n";

    // Table data
    $data = $db->query("SELECT * FROM `$table`");
    $cols = $data->fetch_fields();
    $rows = [];
    while ($row = $data->fetch_row()) {
        $values = [];
        foreach ($row as $val) {
            if ($val === null) {
                $values[] = 'NULL';
            } else {
                $values[] = "'" . $db->real_escape_string($val) . "'";
            }
        }
        $rows[] = "(" . implode(',', $values) . ")";
    }

    if (!empty($rows)) {
        $col_names = [];
        foreach ($cols as $c) $col_names[] = "`$c->name`";
        echo "-- Datos de tabla `$table`\n";
        echo "INSERT INTO `$table` (" . implode(',', $col_names) . ") VALUES\n";
        echo implode(",\n", $rows) . ";\n\n";
    }
}

echo "SET FOREIGN_KEY_CHECKS=1;\n";
echo "-- Respaldo completado\n";
