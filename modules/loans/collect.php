<?php
require_once '../../config/database.php';

$db = getDB();
$user_id = $_SESSION['user_id'];
$is_admin = isAdmin();
$where = "1=1";

$loan_id = intval($_GET['loan_id'] ?? 0);
$client_id = intval($_GET['client_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loan_id_pay = intval($_POST['loan_id']);
    $loan = $db->query("SELECT * FROM loans WHERE id=$loan_id_pay AND $where")->fetch_assoc();
    if (!$loan) { $_SESSION['error'] = 'Pr&eacute;stamo no encontrado'; redirect('/modules/loans/history.php'); }

    $amount_paid = floatval($_POST['amount_paid'] ?? 0);
    $action = $_POST['action'] ?? 'normal';
    $fecha_pago = $_POST['fecha_pago'] ?? date('Y-m-d');

    $db->begin_transaction();
    try {
        if ($loan['loan_type'] === 'mensual') {
            $cap_pagado = floatval($_POST['capital_pagado'] ?? 0);
            $interes = floatval($_POST['interes'] ?? 0);
            $a_capital = floatval($_POST['a_capital'] ?? 0);

            if ($action === 'cancelar_total') {
                $capital_restante = $loan['total_amount'] - $cap_pagado;
                $dias_transcurridos = max(1, (time() - strtotime($loan['start_date'])) / 86400);
                $interes_diario = ($loan['amount'] * $loan['interest_rate'] / 100) / 30;
                $interes_devengado = $interes_diario * $dias_transcurridos;
                $total_cancelar = $capital_restante + $interes_devengado;

                if ($amount_paid < $total_cancelar - 0.01) {
                    throw new Exception("El monto mínimo para cancelar es " . number_format($total_cancelar, 2));
                }

                $a_capital = $amount_paid - $interes_devengado;
                $interes = $interes_devengado;
                $nuevo_capital = $capital_restante - $a_capital;

                $db->query("UPDATE loans SET status='pagado', total_amount = total_amount - $a_capital WHERE id=$loan_id_pay");
                $db->query("UPDATE loan_installments SET status='pagada', paid_date='$fecha_pago' WHERE loan_id=$loan_id_pay AND status='pendiente'");
            } else {
                $interes_vencido = $loan['monthly_payment'] - ($loan['total_amount'] - $loan['amount']);
                $a_capital = max(0, $amount_paid - $loan['monthly_payment']);

                if ($a_capital > 0) {
                    $db->query("UPDATE loans SET total_amount = total_amount - $a_capital WHERE id=$loan_id_pay");
                }

                $db->query("UPDATE loan_installments SET status='pagada', paid_date='$fecha_pago' WHERE loan_id=$loan_id_pay AND status='pendiente' LIMIT 1");

                $nuevo_capital = $loan['total_amount'] - $cap_pagado - $a_capital;
                if ($nuevo_capital <= 0) {
                    $db->query("UPDATE loans SET status='pagado' WHERE id=$loan_id_pay");
                } else {
                    $nueva_fecha = date('Y-m-d', strtotime('+1 month'));
                    $nuevo_interes = $nuevo_capital * $loan['interest_rate'] / 100;
                    $stmt2 = $db->prepare("INSERT INTO loan_installments (loan_id, installment_number, due_date, amount, status) VALUES (?, ?, ?, ?, 'pendiente')");
                    $siguiente = $db->query("SELECT COALESCE(MAX(installment_number),0)+1 as n FROM loan_installments WHERE loan_id=$loan_id_pay")->fetch_assoc()['n'];
                    $stmt2->bind_param("iisd", $loan_id_pay, $siguiente, $nueva_fecha, $nuevo_interes);
                    $stmt2->execute();
                }
            }
        } else {
            $installment_ids = $_POST['installment_ids'] ?? [];
            if (empty($installment_ids)) throw new Exception("Seleccione al menos una cuota");
            $total_due = 0;
            foreach ($installment_ids as $iid) {
                $iid = intval($iid);
                $inst = $db->query("SELECT * FROM loan_installments WHERE id=$iid AND loan_id=$loan_id_pay AND status='pendiente'")->fetch_assoc();
                if ($inst) $total_due += $inst['amount'];
            }
            if ($amount_paid < $total_due) throw new Exception("Monto insuficiente");
            foreach ($installment_ids as $iid) {
                $iid = intval($iid);
                $db->query("UPDATE loan_installments SET status='pagada', paid_date='$fecha_pago' WHERE id=$iid");
            }
            $pending = $db->query("SELECT COUNT(*) as c FROM loan_installments WHERE loan_id=$loan_id_pay AND status='pendiente'")->fetch_assoc()['c'];
            if ($pending == 0) $db->query("UPDATE loans SET status='pagado' WHERE id=$loan_id_pay");
        }

        $db->commit();
        $_SESSION['success'] = 'Pago registrado exitosamente';
        redirect('/modules/loans/history.php');
    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
        redirect("/modules/loans/collect.php?loan_id=$loan_id_pay");
    }
}

if ($loan_id > 0) {
    $loan = $db->query("SELECT l.*, c.name as client_name, c.cedula_rif FROM loans l JOIN clients c ON c.id=l.client_id WHERE l.id=$loan_id AND $where")->fetch_assoc();
    if (!$loan) { $_SESSION['error'] = 'Préstamo no encontrado'; redirect('/modules/loans/history.php'); }
    $installments = $db->query("SELECT * FROM loan_installments WHERE loan_id=$loan_id ORDER BY installment_number");
}

$clients_with_loans = $db->query("SELECT DISTINCT c.id, c.name, c.cedula_rif FROM loans l JOIN clients c ON c.id=l.client_id WHERE $where AND l.status='activo' ORDER BY c.name");

$page_title = 'Cobro de Cuotas';
require_once '../../includes/header.php';
?>
<div class="container-fluid">
    <h4 class="mb-3">Cobro de Cuotas de Pr&eacute;stamo</h4>

    <?php if (!$loan_id): ?>
    <div class="card">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-md-8">
                    <select name="loan_id" class="form-select" required>
                        <option value="">Seleccionar pr&eacute;stamo activo...</option>
                        <?php while($l = $clients_with_loans->fetch_assoc()): 
                            $loans_of = $db->query("SELECT * FROM loans WHERE client_id={$l['id']} AND status='activo' AND $where");
                            while($lo = $loans_of->fetch_assoc()): ?>
                        <option value="<?= $lo['id'] ?>"><?= h($l['name']) ?> - #<?= $lo['id'] ?> (<?= $lo['loan_type'] ?> - <?= number_format($lo['total_amount'],2) ?> <?= $lo['currency'] ?>)</option>
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

    <?php if (isset($loan) && $loan):
        $cap_pagado = ($loan['loan_type'] === 'mensual') ? $loan['amount'] - ($loan['total_amount']) : 0;
        $capital_restante = ($loan['loan_type'] === 'mensual') ? $loan['total_amount'] : 0;
    ?>
    <div class="card mb-3">
        <div class="card-header">
            <strong><?= h($loan['client_name']) ?></strong> - <?= h($loan['cedula_rif']) ?>
            | Pr&eacute;stamo #<?= $loan['id'] ?>
            <span class="badge bg-info ms-2"><?= $loan['loan_type'] ?></span>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3"><strong>Capital Original:</strong> <?= number_format($loan['amount'],2) ?> <?= $loan['currency'] ?></div>
                <?php if ($loan['loan_type'] === 'mensual'): ?>
                <div class="col-md-3"><strong>Inter&eacute;s Mensual:</strong> <?= number_format($loan['monthly_payment'],2) ?> <?= $loan['currency'] ?></div>
                <div class="col-md-3"><strong>Capital Restante:</strong> <span id="capital_restante"><?= number_format($capital_restante,2) ?></span> <?= $loan['currency'] ?></div>
                <?php else: ?>
                <div class="col-md-3"><strong>Total:</strong> <?= number_format($loan['total_amount'],2) ?> <?= $loan['currency'] ?></div>
                <div class="col-md-3"><strong>Cuota:</strong> <?= number_format($loan['monthly_payment'],2) ?> <?= $loan['currency'] ?></div>
                <?php endif; ?>
            </div>

            <form method="POST">
                <input type="hidden" name="loan_id" value="<?= $loan['id'] ?>">
                <input type="hidden" name="capital_pagado" id="capital_pagado" value="<?= $cap_pagado ?>">
                <input type="hidden" name="interes" id="interes_hidden" value="0">
                <input type="hidden" name="a_capital" id="a_capital" value="0">

                <div class="table-responsive mb-3">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th># Cuota</th>
                                <th>Fecha Vencimiento</th>
                                <th>Monto</th>
                                <th>Estado</th>
                                <th>Fecha Pago</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $total_pendiente = 0; ?>
                            <?php while($inst = $installments->fetch_assoc()): 
                                if ($inst['status'] === 'pendiente') $total_pendiente += $inst['amount'];
                            ?>
                            <tr class="<?= $inst['status']=='pagada'?'table-success':($inst['status']=='vencida'?'table-danger':'') ?>">
                                <td><?= $inst['installment_number'] ?></td>
                                <td><?= date('d/m/Y', strtotime($inst['due_date'])) ?></td>
                                <td><?= number_format($inst['amount'],2) ?> <?= $loan['currency'] ?></td>
                                <td><span class="badge bg-<?= $inst['status']=='pagada'?'success':($inst['status']=='vencida'?'danger':'warning') ?>"><?= $inst['status'] ?></span></td>
                                <td><?= $inst['paid_date'] ? date('d/m/Y', strtotime($inst['paid_date'])) : '-' ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Monto a Pagar</label>
                        <input type="number" name="amount_paid" id="amount_paid" class="form-control" step="0.01" min="0" value="<?= $total_pendiente ?>" required>
                        <small class="text-muted">Inter&eacute;s: <span id="interes_display"><?= number_format($loan['monthly_payment'],2) ?></span> | A capital: <span id="a_capital_display">0.00</span></small>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Fecha de Pago</label>
                        <input type="date" name="fecha_pago" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Moneda</label>
                        <select name="currency" class="form-select">
                            <option value="<?= $loan['currency'] ?>"><?= $loan['currency'] ?></option>
                        </select>
                    </div>
                    <?php if ($loan['loan_type'] === 'mensual'): ?>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="button" class="btn btn-danger w-100" onclick="calcularCancelacion()"><i class="bi bi-x-circle"></i> Cancelar Totalidad</button>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($loan['loan_type'] === 'plazo'): ?>
                <div class="mb-3">
                    <label><input type="checkbox" id="selectAll" onchange="toggleAll(this)"> Seleccionar todas las cuotas pendientes</label>
                </div>
                <?php endif; ?>

                <button type="submit" name="action" value="normal" class="btn btn-success btn-lg"><i class="bi bi-check-circle"></i> Procesar Pago</button>
                <a href="history.php" class="btn btn-secondary">Cancelar</a>

                <?php if ($loan['loan_type'] === 'mensual'): ?>
                <div id="cancelacion_info" style="display:none" class="card bg-light mt-3 p-3">
                    <h5>Cancelar Totalidad del Pr&eacute;stamo</h5>
                    <p>Capital restante: <strong id="cap_restante_txt">0.00</strong></p>
                    <p>Inter&eacute;s por d&iacute;a: <strong id="interes_dia_txt">0.00</strong></p>
                    <p>D&iacute;as transcurridos: <strong id="dias_txt">0</strong></p>
                    <p>Inter&eacute;s devengado: <strong id="interes_dev_txt">0.00</strong></p>
                    <p class="fs-5">Total a cancelar: <strong id="total_cancelar_txt" class="text-danger">0.00</strong></p>
                    <button type="submit" name="action" value="cancelar_total" class="btn btn-danger btn-lg"><i class="bi bi-check-circle"></i> Confirmar Cancelaci&oacute;n Total</button>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>
<script>
function toggleAll(source) {
    document.querySelectorAll('.installment-check').forEach(cb => {
        cb.checked = source.checked;
        if (cb.checked) cb.dispatchEvent(new Event('change'));
    });
}

document.getElementById('amount_paid').addEventListener('input', function() {
    var total = parseFloat(this.value) || 0;
    var interes = <?= $loan ? $loan['monthly_payment'] : 0 ?>;
    var aCapital = Math.max(0, total - interes);
    document.getElementById('interes_display').textContent = interes.toFixed(2);
    document.getElementById('interes_hidden').value = Math.min(interes, total);
    document.getElementById('a_capital').value = aCapital;
    document.getElementById('a_capital_display').textContent = aCapital.toFixed(2);
});

function calcularCancelacion() {
    var capital = <?= $capital_restante ?>;
    var tasaInteres = <?= $loan ? ($loan['interest_rate'] / 100) : 0 ?>;
    var fechaInicio = new Date('<?= $loan ? $loan['start_date'] : date('Y-m-d') ?>');
    var hoy = new Date();
    var dias = Math.max(1, Math.floor((hoy - fechaInicio) / (1000 * 60 * 60 * 24)));
    var interesDiario = (capital * tasaInteres) / 30;
    var interesDevengado = interesDiario * dias;
    var totalCancelar = capital + interesDevengado;

    document.getElementById('cap_restante_txt').textContent = capital.toFixed(2);
    document.getElementById('interes_dia_txt').textContent = interesDiario.toFixed(4);
    document.getElementById('dias_txt').textContent = dias;
    document.getElementById('interes_dev_txt').textContent = interesDevengado.toFixed(2);
    document.getElementById('total_cancelar_txt').textContent = totalCancelar.toFixed(2);
    document.getElementById('amount_paid').value = totalCancelar.toFixed(2);
    document.getElementById('cancelacion_info').style.display = 'block';
    document.getElementById('amount_paid').dispatchEvent(new Event('input'));
}

// Trigger initial calculation
setTimeout(function() {
    var ap = document.getElementById('amount_paid');
    if (ap) ap.dispatchEvent(new Event('input'));
}, 100);
</script>
<?php require_once '../../includes/footer.php'; ?>
