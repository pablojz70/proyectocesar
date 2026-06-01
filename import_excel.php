<?php
/**
 * Importador de datos desde Excel a la base de datos
 * Uso: /opt/lampp/bin/php import_excel.php
 */

require_once __DIR__ . '/config/database.php';

$db = getDB();
$admin_id = 1; // admin user

function excelDate($serial) {
    if (!is_numeric($serial)) return date('Y-m-d');
    $unix = ($serial - 25569) * 86400;
    return date('Y-m-d', $unix);
}

function findOrCreateClient($name) {
    global $db, $admin_id;
    $name = trim($name);
    if (empty($name)) return 0;
    $safe = $db->real_escape_string($name);
    $r = $db->query("SELECT id FROM clients WHERE name LIKE '$safe' AND user_id=$admin_id LIMIT 1");
    if ($r && $row = $r->fetch_assoc()) return $row['id'];
    $cedula = 'IMP-' . rand(10000, 99999);
    $stmt = $db->prepare("INSERT INTO clients (user_id, name, cedula_rif) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $admin_id, $name, $cedula);
    $stmt->execute();
    echo "  Cliente creado: $name (Cedula: $cedula)\n";
    return $db->insert_id;
}

function findOrCreateProduct($name) {
    global $db, $admin_id;
    $name = trim($name);
    if (empty($name)) return 0;
    $safe = $db->real_escape_string($name);
    $r = $db->query("SELECT id, price_efectivo, price_euro, price_bcv FROM products WHERE name LIKE '$safe' AND user_id=$admin_id LIMIT 1");
    if ($r && $row = $r->fetch_assoc()) return $row;
    $stmt = $db->prepare("INSERT INTO products (user_id, type, name, description, stock, price_efectivo, price_euro, price_bcv) VALUES (?, 'Perfume', ?, '', 1, 0, 0, 0)");
    $stmt->bind_param("is", $admin_id, $name);
    $stmt->execute();
    $id = $db->insert_id;
    echo "  Producto creado: $name (ID: $id)\n";
    return ['id' => $id, 'price_efectivo' => 0, 'price_euro' => 0, 'price_bcv' => 0];
}

function readExcel($path) {
    $zip = new ZipArchive();
    if ($zip->open($path) !== TRUE) return [];
    $xml = $zip->getFromName('xl/sharedStrings.xml');
    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if (!$xml || !$sheet) return [];

    $strings = [];
    $sxml = simplexml_load_string($xml);
    $ns = $sxml->getNamespaces(true);
    foreach ($sxml->children($ns[''])->si as $si) {
        $strings[] = (string)$si->t;
    }

    $rows = [];
    $sheet_xml = simplexml_load_string($sheet);
    foreach ($sheet_xml->sheetData->row as $r) {
        $row = [];
        foreach ($r->c as $c) {
            $val = (string)$c->v;
            if ($c['t'] == 's') $val = $strings[intval($val)] ?? $val;
            $col = preg_replace('/[0-9]/', '', (string)$c['r']);
            $row[$col] = $val;
        }
        $rows[(int)$r['r']] = $row;
    }
    return $rows;
}

echo "=== IMPORTADOR DE EXCEL ===\n\n";

// ────────────────────────────────────────────
// 1. IMPORTAR PERFUMES (Clientes, Productos, Ventas)
// ────────────────────────────────────────────
echo "--- Importando Perfumes (Ventas) ---\n";
$perfumes = readExcel(__DIR__ . '/documento/Perfumes 62025_Marco.xlsx');
$count_sales = 0;

foreach ($perfumes as $rowNum => $row) {
    if ($rowNum < 7) continue;
    $cliente = trim($row['C'] ?? '');
    $producto = trim($row['D'] ?? '');
    $precio_str = trim($row['E'] ?? '');
    if (empty($cliente) || empty($producto)) continue;

    $client_id = findOrCreateClient($cliente);
    $prod_data = findOrCreateProduct($producto);

    // Revisar si ya existe esta venta (para evitar duplicados)
    $safe_p = $db->real_escape_string($producto);
    $safe_c = $client_id;
    $exists = $db->query("SELECT s.id FROM sales s JOIN sale_items si ON si.sale_id=s.id JOIN products p ON p.id=si.product_id WHERE s.client_id=$safe_c AND p.name LIKE '$safe_p' AND s.user_id=$admin_id LIMIT 1");
    if ($exists && $exists->fetch_assoc()) continue;

    $precio = floatval(str_replace(',', '', $precio_str));
    $fecha_excel = $row['F'] ?? '';
    $fecha = is_numeric($fecha_excel) ? excelDate($fecha_excel) : date('Y-m-d');

    $total_pagado = 0;
    $pagos = [];
    for ($i = 1; $i <= 7; $i++) {
        $col = chr(70 + $i);
        $pago_val = floatval($row[$col] ?? 0);
        if ($pago_val > 0) {
            $total_pagado += $pago_val;
            $pagos[] = $pago_val;
        }
    }

    $por_pagar_str = $row['N'] ?? '0';
    $por_pagar = floatval(str_replace(',', '', $por_pagar_str));
    $total_venta = $precio > 0 ? $precio : $total_pagado + $por_pagar;
    if ($total_venta <= 0) $total_venta = $total_pagado;

    $sale_type = 'credito';
    $status = 'pendiente';
    if ($por_pagar <= 0 && $total_pagado > 0) {
        $sale_type = 'contado';
        $status = 'pagada';
    }

    $db->begin_transaction();
    try {
        $stmt = $db->prepare("INSERT INTO sales (user_id, client_id, sale_type, payment_currency, exchange_rate, total_efectivo, total_euro, total_bs, status, installments, created_at) VALUES (?, ?, ?, 'EFECTIVO', 0, ?, 0, 0, ?, 1, ?)");
        $stmt->bind_param("iisdss", $admin_id, $client_id, $sale_type, $total_venta, $status, $fecha);
        $stmt->execute();
        $sale_id = $db->insert_id;

        $stmt2 = $db->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price_efectivo, unit_price_euro, unit_price_bs) VALUES (?, ?, 1, ?, 0, 0)");
        $stmt2->bind_param("iid", $sale_id, $prod_data['id'], $total_venta);
        $stmt2->execute();

        foreach ($pagos as $p) {
            $stmt3 = $db->prepare("INSERT INTO payments (sale_id, amount_efectivo, amount_euro, amount_bs, exchange_rate, payment_date) VALUES (?, ?, 0, 0, 0, ?)");
            $stmt3->bind_param("ids", $sale_id, $p, $fecha);
            $stmt3->execute();
        }

        $db->commit();
        $count_sales++;
        echo "  Venta #$sale_id: $cliente - $producto ($total_venta$)\n";
    } catch (Exception $e) {
        $db->rollback();
        echo "  Error fila $rowNum: " . $e->getMessage() . "\n";
    }
}
echo "Ventas importadas: $count_sales\n\n";

// ────────────────────────────────────────────
// 2. IMPORTAR PRESTAMOS
// ────────────────────────────────────────────
echo "--- Importando Prestamos ---\n";
$prestamos = readExcel(__DIR__ . '/documento/planilla de prestamos 2025.xlsx');
$count_loans = 0;

foreach ($prestamos as $rowNum => $row) {
    if ($rowNum < 5) continue;
    $cliente = trim($row['C'] ?? '');
    $capital = floatval($row['D'] ?? 0);
    if (empty($cliente) || $capital <= 0) continue;

    $client_id = findOrCreateClient($cliente);
    if (!$client_id) continue;

    $interes_pct = floatval($row['F'] ?? 0);
    $fecha_excel = $row['B'] ?? '';
    $fecha = is_numeric($fecha_excel) ? excelDate($fecha_excel) : (strpos($fecha_excel, '/') ? date('Y-m-d', strtotime(str_replace('/', '-', $fecha_excel))) : date('Y-m-d'));
    $capital_por_pagar = floatval($row['E'] ?? $capital);
    $abonos = floatval($row['J'] ?? 0);

    $total_interes = $capital * $interes_pct;
    $total_amount = $capital + $total_interes;
    $term_months = 6;

    $exists = $db->query("SELECT id FROM loans WHERE client_id=$client_id AND amount=$capital AND user_id=$admin_id LIMIT 1");
    if ($exists && $exists->fetch_assoc()) continue;

    $db->begin_transaction();
    try {
        $stmt = $db->prepare("INSERT INTO loans (user_id, client_id, currency, amount, interest_rate, term_months, total_interest, total_amount, monthly_payment, start_date, status, created_at) VALUES (?, ?, 'EUR', ?, ?, ?, ?, ?, ?, ?, 'activo', ?)");
        $monthly = $term_months > 0 ? $total_amount / $term_months : $total_amount;
        $stmt->bind_param("iiddidddss", $admin_id, $client_id, $capital, $interes_pct, $term_months, $total_interes, $total_amount, $monthly, $fecha, $fecha);
        $stmt->execute();
        $loan_id = $db->insert_id;

        for ($i = 1; $i <= $term_months; $i++) {
            $due = date('Y-m-d', strtotime($fecha . " +$i months"));
            $ins_amt = $i == $term_months ? $total_amount - ($monthly * ($term_months - 1)) : $monthly;
            $stmt2 = $db->prepare("INSERT INTO loan_installments (loan_id, installment_number, due_date, amount, status) VALUES (?, ?, ?, ?, 'pendiente')");
            $stmt2->bind_param("iisd", $loan_id, $i, $due, $ins_amt);
            $stmt2->execute();
        }

        $db->commit();
        $count_loans++;
        echo "  Prestamo #$loan_id: $cliente - $capital EUR\n";
    } catch (Exception $e) {
        $db->rollback();
        echo "  Error fila $rowNum: " . $e->getMessage() . "\n";
    }
}
echo "Prestamos importados: $count_loans\n\n";
echo "=== IMPORTACION COMPLETADA ===\n";
