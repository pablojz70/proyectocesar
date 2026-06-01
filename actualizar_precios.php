<?php
/**
 * Actualiza precios de productos desde hoja "IMPRIMIR PRECIOS" del Excel
 * Uso: /opt/lampp/bin/php actualizar_precios.php
 */

require_once __DIR__ . '/config/database.php';

$db = getDB();
$admin_id = 1;

echo "=== ACTUALIZAR PRECIOS DESDE EXCEL ===\n\n";

$zip = new ZipArchive();
if ($zip->open(__DIR__ . '/documento/Perfumes 62025_Marco.xlsx') !== TRUE) {
    die("No se pudo abrir el Excel\n");
}

$xml = $zip->getFromName('xl/sharedStrings.xml');
$sheet = $zip->getFromName('xl/worksheets/sheet3.xml');
$zip->close();

$strings = [];
$sxml = simplexml_load_string($xml);
$ns = $sxml->getNamespaces(true);
foreach ($sxml->children($ns[''])->si as $si) {
    $strings[] = (string)$si->t;
}

$actualizados = 0;
$no_encontrados = 0;

$sheet_xml = simplexml_load_string($sheet);
foreach ($sheet_xml->sheetData->row as $r) {
    $rowNum = (int)$r['r'];
    if ($rowNum < 2) continue;

    $row = [];
    foreach ($r->c as $c) {
        $val = (string)$c->v;
        if ($c['t'] == 's') $val = $strings[intval($val)] ?? $val;
        $col = preg_replace('/[0-9]/', '', (string)$c['r']);
        $row[$col] = $val;
    }

    $nombre = trim($row['A'] ?? '');
    $precio_divisa = floatval($row['B'] ?? 0);
    $precio_bcv = floatval($row['C'] ?? 0);
    $precio_euro = floatval($row['D'] ?? 0);

    if (empty($nombre) || $precio_divisa <= 0) continue;

    $safe = $db->real_escape_string($nombre);
    $result = $db->query("SELECT id, name, price_efectivo, price_euro, price_bcv FROM products WHERE name LIKE '$safe' AND user_id=$admin_id");

    if ($result && $prod = $result->fetch_assoc()) {
        $stmt = $db->prepare("UPDATE products SET price_efectivo=?, price_euro=?, price_bcv=? WHERE id=?");
        $stmt->bind_param("dddi", $precio_divisa, $precio_euro, $precio_bcv, $prod['id']);
        $stmt->execute();
        echo "  Actualizado: {$prod['name']} → $";
        printf("%.2f / €%.2f / Bs %.2f\n", $precio_divisa, $precio_euro, $precio_bcv);
        $actualizados++;
    } else {
        echo "  NO ENCONTRADO: $nombre\n";
        $no_encontrados++;
    }
}

echo "\n=== RESUMEN ===\n";
echo "Actualizados: $actualizados\n";
echo "No encontrados: $no_encontrados\n";
