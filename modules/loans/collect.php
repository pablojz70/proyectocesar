<?php
require_once '../../config/database.php';

$db = getDB();
$user_id = $_SESSION['user_id'];
$is_admin = isAdmin();
$where = "1=1";

$loan_id = intval($_GET['loan_id'] ?? 0);

// ── POST: Registrar pago ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loan_id_pay = intval($_POST['loan_id']);
    $monto = floatval($_POST['monto'] ?? 0);
    $fecha = $_POST['fecha_pago'] ?? date('Y-m-d');
    $action = $_POST['action'] ?? 'normal';

    if ($monto <= 0) {
        $_SESSION['error'] = 'Debe ingresar un monto v&aacute;lido';
        redirect("/modules/loans/collect.php?loan_id=$loan_id_pay");
    }

    $loan = $db->query("SELECT * FROM loans WHERE id=$loan_id_pay AND $where")->fetch_assoc();
    if (!$loan) { $_SESSION['error'] = 'Pr&eacute;stamo no encontrado'; redirect('/modules/loans/history.php'); }

    $db->begin_transaction();
    try {
        $loan_type = $loan['loan_type'];

        if ($loan_type === 'plazo') {
            $r = $db->query("INSERT INTO loan_payments (loan_id, amount, payment_date) VALUES ($loan_id_pay, $monto, '$fecha')");
            if (!$r) throw new Exception("Error al registrar pago: " . $db->error);
            $total_pagado = $db->query("SELECT COALESCE(SUM(amount),0) as t FROM loan_payments WHERE loan_id=$loan_id_pay")->fetch_assoc()['t'];

            $cuotas = $db->query("SELECT * FROM loan_installments WHERE loan_id=$loan_id_pay AND status='pendiente' ORDER BY installment_number");
            $acum = 0;
            while ($c = $cuotas->fetch_assoc()) {
                $acum += $c['amount'];
                if ($total_pagado >= $acum) {
                    $db->query("UPDATE loan_installments SET status='pagada', paid_date='$fecha', paid_amount={$c['amount']} WHERE id={$c['id']}");
                } else break;
            }
            $pendientes = $db->query("SELECT COUNT(*) as c FROM loan_installments WHERE loan_id=$loan_id_pay AND status='pendiente'")->fetch_assoc()['c'];
            if ($pendientes == 0) $db->query("UPDATE loans SET status='pagado' WHERE id=$loan_id_pay");
        } else {
            // Obtener valores ANTES de insertar el pago
            $capital_actual = $db->query("SELECT total_amount FROM loans WHERE id=$loan_id_pay")->fetch_assoc()['total_amount'];
            $ult_pago = $db->query("SELECT payment_date FROM loan_payments WHERE loan_id=$loan_id_pay ORDER BY payment_date DESC, id DESC LIMIT 1")->fetch_assoc();
            $ult_fecha = $ult_pago ? $ult_pago['payment_date'] : $loan['start_date'];

            // Insertar pago
            $r = $db->query("INSERT INTO loan_payments (loan_id, amount, payment_date) VALUES ($loan_id_pay, $monto, '$fecha')");
            if (!$r) throw new Exception("Error al registrar pago: " . $db->error);

            // MoraP entre ultimo pago y este pago
            $fecha_ult = new DateTime($ult_fecha);
            $fecha_pago_dt = new DateTime($fecha);
            $diff = $fecha_ult->diff($fecha_pago_dt);
            $mora_p = ($diff->y * 12) + $diff->m;
            if ($diff->d > 15) $mora_p++;
            $mora_p = max(0, $mora_p);

            // Interes del periodo = MoraP * Interes Mensual
            $interes_periodo = $loan['monthly_payment'] * $mora_p;

            if ($monto <= $interes_periodo) {
                // Pago no cubre interes del periodo, se acumula
            } else {
                $exc = $monto - $interes_periodo;
                $nuevo_cap = max(0, $capital_actual - $exc);
                $db->query("UPDATE loans SET total_amount = $nuevo_cap WHERE id=$loan_id_pay");
                if ($nuevo_cap <= 0) $db->query("UPDATE loans SET status='pagado' WHERE id=$loan_id_pay");
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
    if (!$loan) { $_SESSION['error'] = 'Pr&eacute;stamo no encontrado'; redirect('/modules/loans/history.php'); }
    $pagos = $db->query("SELECT * FROM loan_payments WHERE loan_id=$loan_id ORDER BY payment_date, id");
    $total_pagado = $db->query("SELECT COALESCE(SUM(amount),0) as t FROM loan_payments WHERE loan_id=$loan_id")->fetch_assoc()['t'];
    $hoy = new DateTime();

    $interes_mensual = $loan['monthly_payment'];
    $inicio = new DateTime($loan['start_date']);
    $diff_total = $inicio->diff($hoy);

    if ($loan['loan_type'] === 'plazo') {
        $cuota_fija = $loan['term_months'] > 0 ? $loan['total_amount'] / $loan['term_months'] : 0;
        $cuotas_pagadas = $cuota_fija > 0 ? floor($total_pagado / $cuota_fija) : 0;
        $cuotas_restantes = max(0, $loan['term_months'] - $cuotas_pagadas);
        $mora = ($diff_total->y * 12) + $diff_total->m;
        $mora += $diff_total->d > 15 ? 1 : 0;
        $deuda_restante = max(0, $loan['total_amount'] - $total_pagado);
        $capital_restante = 0;
        $total_cuota = $cuota_fija;
    } else {
        $mora_total = ($diff_total->y * 12) + $diff_total->m;
        $mora_total += $diff_total->d > 15 ? 1 : 0;
        $mora = $mora_total;
        $total_cuota = $loan['amount'] + ($interes_mensual * $mora_total);
        $deuda_bruta = $loan['amount'] + ($interes_mensual * $mora_total);

        // Generar filas por cada pago que redujo capital
        $filas = [];
        $cap_act = $loan['amount'];
        $fecha_base = $loan['start_date'];
        $abono_acum = 0;
        $pagos_filas = $db->query("SELECT * FROM loan_payments WHERE loan_id=$loan_id ORDER BY payment_date, id");
        while ($pf = $pagos_filas->fetch_assoc()) {
            $abono_acum += $pf['amount'];
            // MoraP entre fecha base y este pago
            $fb = new DateTime($fecha_base);
            $fp = new DateTime($pf['payment_date']);
            $df = $fb->diff($fp);
            $mp = ($df->y * 12) + $df->m;
            if ($df->d > 15) $mp++;
            $mp = max(0, $mp);
            $interes_per = $interes_mensual * $mp;
            if ($abono_acum > $interes_per) {
                $exc_p = $abono_acum - $interes_per;
                $nuevo_cap = max(0, $cap_act - $exc_p);
                if ($nuevo_cap < $cap_act) {
                    $filas[] = [
                        'capital' => $nuevo_cap,
                        'interes' => $nuevo_cap * $loan['interest_rate'] / 100,
                        'fecha_base' => $pf['payment_date'],
                        'mora_p' => $mp,
                        'abono' => $abono_acum,
                        'cap_anterior' => $cap_act
                    ];
                    $cap_act = $nuevo_cap;
                    $fecha_base = $pf['payment_date'];
                    $abono_acum = 0;
                }
            }
        }

        $capital_restante = $cap_act;
        if (empty($filas)) {
            $deuda_restante = max(0, $deuda_bruta - $total_pagado);
            $interes_pagado = $total_pagado > ($interes_mensual * $mora_total);
        } else {
            $ult = end($filas);
            $deuda_restante = $ult['capital'];
            $interes_pagado = true;
        }
    }
}

$page_title = 'Cobro de Pr&eacute;stamo';
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <?php if ($loan_id > 0 && $loan): ?>
    <!-- ── RESUMEN ── -->
    <div class="card mb-3">
        <div class="card-header"><strong><?= h($loan['client_name']) ?></strong> - Pr&eacute;stamo #<?= $loan['id'] ?></div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead><tr>
                    <th>#</th><th>Cliente</th><th>Moneda</th><th>Capital</th><th>%</th>
                    <th>Inter&eacute;s</th><th>Fecha</th><th>Tipo</th><th>Mora</th>
                    <th>Cuota</th><th>Deuda</th><th>Cap. Restante</th><th>Abono</th>
                </tr></thead>
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
                    <?php $mora_acum = $mora; foreach ($filas as $idx => $fila): 
                        $mora_acum -= $fila['mora_p'];
                        $mora_f2 = max(0, $mora_acum);
                        $nueva_fecha = date('d/', strtotime($loan['start_date'])) . date('m/Y', strtotime($fila['fecha_base']));
                    ?>
                    <tr class="table-info">
                        <td><?= $loan['id'] ?>*<?= $idx+1 ?></td>
                        <td><?= h($loan['client_name']) ?></td>
                        <td><?= $loan['currency'] ?></td>
                        <td><?= number_format($fila['capital'],2) ?></td>
                        <td><?= intval($loan['interest_rate']) ?>%</td>
                        <td><?= number_format($fila['interes'],2) ?></td>
                        <td><?= $nueva_fecha ?></td>
                        <td>Mensual</td>
                        <td><?= $mora_f2 ?></td>
                        <td><?= number_format($fila['capital'] + ($fila['interes'] * $mora_f2),2) ?></td>
                        <td><?= number_format($fila['capital'] + ($fila['interes'] * $mora_f2),2) ?></td>
                        <td><strong><?= number_format($fila['capital'],2) ?></strong></td>
                        <td><?= number_format($fila['abono'],2) ?></td>
                    </tr>
                    <?php endforeach; ?>
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
                    <?php $ult_fecha_mora = $loan['start_date']; $acum = 0; while($p = $pagos->fetch_assoc()): $acum += $p['amount']; 
                        $desde = new DateTime($ult_fecha_mora);
                        $hasta = new DateTime($p['payment_date']);
                        $diff = $desde->diff($hasta);
                        $mora_p = ($diff->y * 12) + $diff->m;
                        if ($diff->d > 15) $mora_p++;
                        $mora_p = max(0, $mora_p);
                        $ult_fecha_mora = $p['payment_date'];
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
            <?php
            $fecha_min = $db->query("SELECT MAX(payment_date) as f FROM loan_payments WHERE loan_id=$loan_id")->fetch_assoc()['f'];
            if (!$fecha_min) $fecha_min = $loan['start_date'];
            $fecha_max = date('Y-m-d');

            // Calcular Pago Completo
            $pago_monto = $deuda_restante;
            $int_final = 0;
            $cap_final = $capital_restante;
            if ($loan['loan_type'] === 'mensual' && $total_pagado > 0) {
                $ult_f = $db->query("SELECT MAX(payment_date) as f FROM loan_payments WHERE loan_id=$loan_id")->fetch_assoc()['f'];
                if (!$ult_f) $ult_f = $loan['start_date'];
                $d_hoy = max(0, (new DateTime())->diff(new DateTime($ult_f))->days);
                $int_final = ($capital_restante * $loan['interest_rate'] / 100 / 30) * $d_hoy;
                $pago_monto = $capital_restante + $int_final;
            }
            ?>
            <form method="POST" class="row g-2" onsubmit="this.querySelector('button[type=submit]').disabled=true">
                <input type="hidden" name="loan_id" value="<?= $loan['id'] ?>">
                <div class="col-md-3">
                    <label class="form-label">Monto a Pagar</label>
                    <input type="number" name="monto" id="monto_pago" class="form-control" step="0.01" min="0" required>
                    <input type="hidden" id="pago_completo_monto" value="<?= $pago_monto ?>">
                    <input type="hidden" id="interes_final_val" value="<?= $int_final ?>">
                    <input type="hidden" id="capital_final_val" value="<?= $cap_final ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha de Pago</label>
                    <input type="date" name="fecha_pago" class="form-control" value="<?= date('Y-m-d') ?>" min="<?= $fecha_min ?>" max="<?= $fecha_max ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Moneda</label>
                    <input type="text" class="form-control" value="<?= $loan['currency'] ?>" readonly>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" name="action" value="normal" class="btn btn-success w-100"><i class="bi bi-check-circle"></i> Registrar Pago</button>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <a href="?loan_id=<?= $loan['id'] ?>&pago_completo=1" class="btn btn-danger w-100" onclick="pagoCompleto()"><i class="bi bi-credit-card"></i> Pago Completo</a>
                </div>
            </form>
            <div id="resumen_pago" class="card bg-light mt-3 p-3" style="display:none">
                <h6>Resumen de Pago Completo</h6>
                <div class="row">
                    <div class="col-md-4">Inter&eacute;s final: <strong id="interes_final_txt">0.00</strong></div>
                    <div class="col-md-4">Capital restante: <strong id="capital_final_txt">0.00</strong></div>
                    <div class="col-md-4">Total a pagar: <strong id="total_final_txt" class="text-danger">0.00</strong></div>
                </div>
                <small class="text-muted">Complete el monto y presione "Registrar Pago" para finalizar.</small>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- ── SELECCIONAR PRESTAMO ── -->
    <div class="card">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-md-8">
                    <select name="loan_id" class="form-select" required>
                        <option value="">Seleccionar pr&eacute;stamo activo...</option>
                        <?php $clients = $db->query("SELECT DISTINCT c.id, c.name FROM loans l JOIN clients c ON c.id=l.client_id WHERE $where AND l.status='activo' ORDER BY c.name"); ?>
                        <?php while($c = $clients->fetch_assoc()): 
                            $loans = $db->query("SELECT * FROM loans WHERE client_id={$c['id']} AND status='activo' AND $where");
                            while($l = $loans->fetch_assoc()): ?>
                        <option value="<?= $l['id'] ?>"><?= h($c['name']) ?> - #<?= $l['id'] ?> (<?= $l['loan_type'] ?> - <?= number_format($l['total_amount'],2) ?> <?= $l['currency'] ?>)</option>
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
<script>
function pagoCompleto() {
    var monto = document.getElementById('pago_completo_monto').value;
    document.getElementById('monto_pago').value = monto;
    document.querySelector('[name=fecha_pago]').value = '<?= date('Y-m-d') ?>';
    document.getElementById('interes_final_txt').textContent = document.getElementById('interes_final_val').value;
    document.getElementById('capital_final_txt').textContent = document.getElementById('capital_final_val').value;
    document.getElementById('total_final_txt').textContent = monto;
    document.getElementById('resumen_pago').style.display = 'block';
}
</script>
<?php require_once '../../includes/footer.php'; ?>
