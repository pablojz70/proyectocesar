<?php
/**
 * Actualiza stock desde hoja "Existencia" y precios desde "IMPRIMIR PRECIOS"
 * Uso: /opt/lampp/bin/php actualizar_stock.php
 */

require_once __DIR__ . '/config/database.php';

$db = getDB();
$admin_id = 1;

echo "=== ACTUALIZAR STOCK DESDE EXCEL ===\n\n";

$zip = new ZipArchive();
if ($zip->open(__DIR__ . '/documento/Perfumes 62025_Marco.xlsx') !== TRUE) {
    die("No se pudo abrir el Excel\n");
}

$xml = $zip->getFromName('xl/sharedStrings.xml');
$sxml = simplexml_load_string($xml);
$ns = $sxml->getNamespaces(true);
$strings = [];
foreach ($sxml->children($ns[''])->si as $si) {
    $strings[] = (string)$si->t;
}

// ── Sheet 4: Existencia (stock) ──
echo "--- Stock desde hoja Existencia ---\n";
$sheet = $zip->getFromName('xl/worksheets/sheet4.xml');
$sheet_xml = simplexml_load_string($sheet);
$actualizados = 0; $no_encontrados = 0; $creados = 0;

foreach ($sheet_xml->sheetData->row as $r) {
    $producto = ''; $stock = 0;
    foreach ($r->c as $c) {
        $ref = (string)$c['r'];
        $val = (string)$c->v;
        $col = preg_replace('/[0-9]/', '', $ref);
        if ($c['t'] == 's') $val = $strings[intval($val)] ?? $val;
        if ($col == 'A') $producto = trim($val);
        if ($col == 'B') $stock = intval($val);
    }
    if (empty($producto) || $producto == 'TOTALES' || $stock <= 0) continue;

    $safe = $db->real_escape_string(substr($producto, 0, 30));
    $result = $db->query("SELECT id, name FROM products WHERE name LIKE '%$safe%' AND user_id=$admin_id LIMIT 1");
    if ($result && $prod = $result->fetch_assoc()) {
        $db->query("UPDATE products SET stock=$stock WHERE id={$prod['id']}");
        echo "  OK: {$prod['name']} → stock $stock\n";
        $actualizados++;
    } else {
        $stmt = $db->prepare("INSERT INTO products (user_id, type, name, stock, price_efectivo, price_euro, price_bcv) VALUES (?, 'Perfume', ?, ?, 0, 0, 0)");
        $stmt->bind_param("isi", $admin_id, $producto, $stock);
        $stmt->execute();
        echo "  CREADO: $producto → stock $stock\n";
        $creados++;
    }
}
echo "Stock actualizados: $actualizados | Creados: $creados | No encontrados: $no_encontrados\n\n";

// ── Sheet 3: IMPRIMIR PRECIOS (prices) ──
echo "--- Precios desde hoja IMPRIMIR PRECIOS ---\n";
$sheet3 = $zip->getFromName('xl/worksheets/sheet3.xml');
$zip->close();

$sheet3_xml = simplexml_load_string($sheet3);
$precios_ok = 0; $precios_no = 0;

foreach ($sheet3_xml->sheetData->row as $r) {
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
    $p_divisa = floatval($row['B'] ?? 0);
    $p_bcv = floatval($row['C'] ?? 0);
    $p_euro = floatval($row['D'] ?? 0);
    if (empty($nombre) || $p_divisa <= 0) continue;

    $safe = $db->real_escape_string(substr($nombre, 0, 30));
    $result = $db->query("SELECT id, name FROM products WHERE name LIKE '%$safe%' AND user_id=$admin_id LIMIT 1");
    if ($result && $prod = $result->fetch_assoc()) {
        $st = $db->prepare("UPDATE products SET price_efectivo=?, price_euro=?, price_bcv=? WHERE id=?");
        $st->bind_param("dddi", $p_divisa, $p_euro, $p_bcv, $prod['id']);
        $st->execute();
        $precios_ok++;
    } else {
        $precios_no++;
    }
}
echo "Precios actualizados: $precios_ok | No encontrados: $precios_no\n\n";
echo "=== COMPLETADO ===\n";
