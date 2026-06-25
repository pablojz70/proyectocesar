<?php
require_once '../../config/database.php';

$db = getDB();
$user_id = $_SESSION['user_id'];
$is_admin = isAdmin();
$where = "1=1";

$loan_id = intval($_GET['loan_id'] ?? 0);
$client_id = intval($_GET['client_id'] ?? 0);

// ── POST: Registrar pago ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loan_id_pay = intval($_POST['loan_id']);
    $monto = floatval($_POST['monto'] ?? 0);
    $fecha = $_POST['fecha_pago'] ?? date('Y-m-d');

    if ($monto <= 0) {
        $_SESSION['error'] = 'Debe ingresar un monto válido';
        redirect("/modules/loans/collect.php?loan_id=$loan_id_pay");
    }

    $loan = $db->query("SELECT * FROM loans WHERE id=$loan_id_pay AND $where")->fetch_assoc();
    if (!$loan) { $_SESSION['error'] = 'Préstamo no encontrado'; redirect('/modules/loans/history.php'); }

    $db->begin_transaction();
    try {
        if ($loan['loan_type'] === 'plazo') {
            // PLAZO: sumar al abono, si cubre cuota(s), descontar
            $r = $db->query("INSERT INTO loan_payments (loan_id, amount, payment_date) VALUES ($loan_id_pay, $monto, '$fecha')");
            if (!$r) throw new Exception("Error al registrar pago: " . $db->error);
            $total_pagado = $db->query("SELECT COALESCE(SUM(amount),0) as t FROM loan_payments WHERE loan_id=$loan_id_pay")->fetch_assoc()['t'];

            // Marcar cuotas pagadas segun el total abonado
            $cuotas = $db->query("SELECT * FROM loan_installments WHERE loan_id=$loan_id_pay AND status='pendiente' ORDER BY installment_number");
            $acumulado = 0;
            while ($c = $cuotas->fetch_assoc()) {
                $acumulado += $c['amount'];
                if ($total_pagado >= $acumulado) {
                    $db->query("UPDATE loan_installments SET status='pagada', paid_date='$fecha', paid_amount={$c['amount']} WHERE id={$c['id']}");
                } else break;
            }

            $pendientes = $db->query("SELECT COUNT(*) as c FROM loan_installments WHERE loan_id=$loan_id_pay AND status='pendiente'")->fetch_assoc()['c'];
            if ($pendientes == 0) $db->query("UPDATE loans SET status='pagado' WHERE id=$loan_id_pay");

        } else {
            // MENSUAL: acumular abonos
            $r = $db->query("INSERT INTO loan_payments (loan_id, amount, payment_date) VALUES ($loan_id_pay, $monto, '$fecha')");
            if (!$r) throw new Exception("Error al registrar pago: " . $db->error);
            $total_pagado = $db->query("SELECT COALESCE(SUM(amount),0) as t FROM loan_payments WHERE loan_id=$loan_id_pay")->fetch_assoc()['t'];

    // Calcular mora (meses desde inicio a hoy)
    $interes_mensual = $loan['monthly_payment'];
    $inicio = new DateTime($loan['start_date']);
    $hoy = new DateTime();
    $diff = $inicio->diff($hoy);
    $mora = ($diff->y * 12) + $diff->m;
    $mora += $diff->d > 15 ? 1 : 0;
            $capital_restante = $loan['total_amount'];
            $abonado_capital = 0;

            // Calcular mora actual
            $start = new DateTime($loan['start_date']);
            $now = new DateTime($fecha);
            $diff = $start->diff($now);
            $mora_actual = ($diff->y * 12) + $diff->m;
            $mora_actual += $diff->d > 15 ? 1 : 0;

            // Cuanto interes se debe por mora
            $interes_devengado = $interes_mensual * $mora_actual;

            // Primero pagar intereses pendientes
            if ($total_pagado >= $interes_devengado) {
                $excedente = $total_pagado - $interes_devengado;
                // Pagar todo el interes, el resto al capital
                $nuevo_capital = max(0, $capital_restante - $excedente);
                $abonado_capital = $excedente;
                $db->query("UPDATE loans SET total_amount = $nuevo_capital WHERE id=$loan_id_pay");
                if ($nuevo_capital <= 0) {
                    $db->query("UPDATE loans SET status='pagado' WHERE id=$loan_id_pay");
                }
            }
        }

        $db->commit();
        $_SESSION['success'] = 'Pago de ' . number_format($monto, 2) . ' registrado';
        redirect("/modules/loans/collect.php?loan_id=$loan_id_pay");
    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
        redirect("/modules/loans/collect.php?loan_id=$loan_id_pay");
    }
}

// ── GET: Mostrar pagina ──
if ($loan_id > 0) {
    $loan = $db->query("SELECT l.*, c.name as client_name, c.cedula_rif FROM loans l JOIN clients c ON c.id=l.client_id WHERE l.id=$loan_id AND $where")->fetch_assoc();
    if (!$loan) { $_SESSION['error'] = 'Préstamo no encontrado'; redirect('/modules/loans/history.php'); }
    $pagos = $db->query("SELECT * FROM loan_payments WHERE loan_id=$loan_id ORDER BY payment_date, id");
    $total_pagado = $db->query("SELECT COALESCE(SUM(amount),0) as t FROM loan_payments WHERE loan_id=$loan_id")->fetch_assoc()['t'];
    $cuotas = $db->query("SELECT * FROM loan_installments WHERE loan_id=$loan_id ORDER BY installment_number");

    // Calcular mora (meses desde inicio a hoy)
    $inicio = new DateTime($loan['start_date']);
    $hoy = new DateTime();
    $diff = $inicio->diff($hoy);
    $mora = ($diff->y * 12) + $diff->m;
    $mora += $diff->d > 15 ? 1 : 0;

    $interes_mensual = $loan['monthly_payment'];
    if ($loan['loan_type'] === 'plazo') {
        $cuota_plazo = $loan['term_months'] > 0 ? $loan['total_amount'] / $loan['term_months'] : 0;
        $cuotas_pagadas = $cuota_plazo > 0 ? floor($total_pagado / $cuota_plazo) : 0;
        $cuota_actual = $cuotas_pagadas + 1;
        $fecha_venc = date('Y-m-d', strtotime($loan['start_date'] . " +$cuota_actual months"));
        $hoy = new DateTime();
        $venc = new DateTime($fecha_venc);
        if ($hoy > $venc) {
            $diff = $venc->diff($hoy);
            $mora_plazo = ($diff->y * 12) + $diff->m;
            $mora_plazo += $diff->d > 15 ? 1 : 0;
        } else {
            $mora_plazo = 0;
        }
        $capital_restante = 0;
        $cuotas_restantes = max(0, $loan['term_months'] - $cuotas_pagadas);
        $total_cuota = $cuota_plazo;
        $deuda_restante = max(0, $loan['total_amount'] - $total_pagado);
    } else {
        // Calcular meses cubiertos por pagos
        $ult_pago = $db->query("SELECT MAX(payment_date) as f FROM loan_payments WHERE loan_id=$loan_id")->fetch_assoc()['f'];
        $fecha_ref = $ult_pago ? new DateTime($ult_pago) : new DateTime($loan['start_date']);
        $inicio = new DateTime($loan['start_date']);
        $diff = $inicio->diff($fecha_ref);
        $meses_pagados = ($diff->y * 12) + $diff->m;
        $meses_pagados += $diff->d > 15 ? 1 : 0;

        $deuda_im = $interes_mensual * $meses_pagados;

        if ($total_pagado == 0) {
            $capital_restante = $loan['amount'];
            $deuda_restante = $loan['amount'] + ($interes_mensual * $mora);
            $interes_pagado = false;
        } elseif ($total_pagado <= $deuda_im) {
            $capital_restante = $loan['amount'];
            $deuda_restante = max(0, $loan['amount'] + ($interes_mensual * $mora) - $total_pagado);
            $interes_pagado = false;
        } else {
            $exc = $total_pagado - $deuda_im;
            $capital_restante = max(0, $loan['amount'] - $exc);
            $deuda_restante = $capital_restante;
            $interes_pagado = true;
        }
        $total_cuota = $loan['amount'] + ($interes_mensual * $mora);
    }
}

$page_title = 'Cobro de Préstamo';
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <?php if ($loan_id > 0 && $loan): ?>
    <!-- ── RESUMEN DEL PRESTAMO ── -->
    <div class="card mb-3">
        <div class="card-header"><strong><?= h($loan['client_name']) ?></strong> - Préstamo #<?= $loan['id'] ?></div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Cliente</th>
                        <th>Moneda</th>
                        <th>Capital</th>
                        <th>%</th>
                        <th>Interés</th>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Mora</th>
                        <th>Cuota</th>
                        <th>Deuda</th>
                        <th>Cap. Restante</th>
                        <th>Abono</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?= $loan['id'] ?></td>
                        <td><?= h($loan['client_name']) ?></td>
                        <td><?= $loan['currency'] ?></td>
                        <td><?= number_format($loan['amount'],2) ?></td>
                        <td><?= intval($loan['interest_rate']) ?>%</td>
                        <td><?= number_format($loan['total_interest'],2) ?></td>
                        <td><?= date('d/m/Y', strtotime($loan['start_date'])) ?></td>
                        <td><?= $loan['loan_type'] === 'mensual' ? 'Mensual' : 'Plazo' ?></td>
                        <td><?= $loan['status'] === 'pagado' ? '-' : $mora ?></td>
                        <td><?= number_format($total_cuota,2) ?></td>
                        <td><strong><?= number_format($deuda_restante,2) ?></strong></td>
                        <td><strong><?= $loan['loan_type'] === 'plazo' ? $cuotas_restantes . ' cuotas' : number_format($capital_restante,2) ?></strong></td>
                        <td><?= $total_pagado > 0 ? number_format($total_pagado,2) : '-' ?></td>
                    </tr>
                    <?php if ($loan['loan_type'] === 'mensual' && $interes_pagado && $capital_restante > 0 && $capital_restante < $loan['amount']): ?>
                    <tr class="table-info">
                        <td><?= $loan['id'] ?>*</td>
                        <td><?= h($loan['client_name']) ?></td>
                        <td><?= $loan['currency'] ?></td>
                        <td><?= number_format($capital_restante,2) ?></td>
                        <td><?= intval($loan['interest_rate']) ?>%</td>
                        <td><?= number_format($capital_restante * $loan['interest_rate'] / 100,2) ?></td>
                        <td><?= date('d/m/Y', strtotime('+1 month', strtotime($loan['start_date']))) ?></td>
                        <td><?= $loan['loan_type'] === 'mensual' ? 'Mensual' : 'Plazo' ?></td>
                        <td><?= $loan['status'] === 'pagado' ? '-' : $mora ?></td>
                        <td><?= number_format($capital_restante + ($capital_restante * $loan['interest_rate'] / 100 * $mora),2) ?></td>
                        <td><?= number_format($capital_restante + ($capital_restante * $loan['interest_rate'] / 100 * $mora),2) ?></td>
                        <td><strong><?= number_format($capital_restante,2) ?></strong></td>
                        <td><?= $total_pagado > 0 ? number_format($total_pagado,2) : '-' ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ── HISTORIAL DE PAGOS ── -->
    <div class="card mb-3">
        <div class="card-header">Historial de Pagos</div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead><tr><th>#</th><th>Fecha Pago</th><th>Monto</th><th>Moneda</th><th>MoraP</th><th>Total Abonado</th><th class="no-print">Acci&oacute;n</th></tr></thead>
                <tbody>
                    <?php if ($pagos->num_rows === 0): ?>
                    <tr><td colspan="7" class="text-center text-muted">Sin pagos registrados</td></tr>
                    <?php endif; ?>
                    <?php $acum = 0; $inicio_ts = strtotime($loan['start_date']); while($p = $pagos->fetch_assoc()): $acum += $p['amount']; 
                        $diff = (strtotime($p['payment_date']) - $inicio_ts) / 86400;
                        $mora_p = floor($diff / 30);
                        if (($diff - $mora_p * 30) > 15) $mora_p++;
                    ?>
                    <tr>
                        <td><?= $p['id'] ?></td>
                        <td><?= date('d/m/Y', strtotime($p['payment_date'])) ?></td>
                        <td><?= number_format($p['amount'],2) ?></td>
                        <td><?= $loan['currency'] ?></td>
                        <td><?= $mora_p ?></td>
                        <td><?= number_format($acum,2) ?></td>
                        <td class="no-print"><a href="delete_pago.php?id=<?= $p['id'] ?>&loan_id=<?= $loan['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Eliminar este pago?')" title="Eliminar"><i class="bi bi-trash"></i></a></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ── FORMULARIO DE PAGO ── -->
    <div class="card">
        <div class="card-header">Registrar Pago</div>
        <div class="card-body">
            <form method="POST" class="row g-2" onsubmit="this.querySelector('button[type=submit]').disabled=true">
                <input type="hidden" name="loan_id" value="<?= $loan['id'] ?>">
                <div class="col-md-4">
                    <label class="form-label">Monto a Pagar</label>
                    <input type="number" name="monto" class="form-control" step="0.01" min="0" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha de Pago</label>
                    <input type="date" name="fecha_pago" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Moneda</label>
                    <input type="text" class="form-control" value="<?= $loan['currency'] ?>" readonly>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-success w-100"><i class="bi bi-check-circle"></i> Registrar Pago</button>
                </div>
            </form>
        </div>
    </div>

    <?php else: ?>
    <!-- ── SELECCIONAR PRESTAMO ── -->
    <div class="card">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-md-8">
                    <select name="loan_id" class="form-select" required>
                        <option value="">Seleccionar préstamo activo...</option>
                        <?php $clients = $db->query("SELECT DISTINCT c.id, c.name FROM loans l JOIN clients c ON c.id=l.client_id WHERE $where AND l.status='activo' ORDER BY c.name"); ?>
                        <?php while($c = $clients->fetch_assoc()): 
                            $loans = $db->query("SELECT * FROM loans WHERE client_id={$c['id']} AND status='activo' AND $where");
                            while($l = $loans->fetch_assoc()): ?>
                        <option value="<?= $l['id'] ?>"><?= h($c['name']) ?> - #<?= $l['id'] ?> (<?= number_format($l['total_amount'],2) ?> <?= $l['currency'] ?>)</option>
                        <?php endwhile; endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100">Seleccionar</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php require_once '../../includes/footer.php'; ?>
