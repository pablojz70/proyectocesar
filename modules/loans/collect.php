<?php
require_once '../../config/database.php';
$page_title = 'Cobro de Cuotas de Préstamo';
require_once '../../includes/header.php';

$db = getDB();
$user_id = $_SESSION['user_id'];
$is_admin = isAdmin();
$where = $is_admin ? "1=1" : "l.user_id = $user_id";

$loan_id = intval($_GET['loan_id'] ?? 0);
$client_id = intval($_GET['client_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loan_id_pay = intval($_POST['loan_id']);
    $installment_ids = $_POST['installment_ids'] ?? [];
    $amount_paid = floatval($_POST['amount_paid'] ?? 0);
    $currency = $_POST['currency'] ?? 'EUR';

    if (empty($installment_ids)) {
        $_SESSION['error'] = 'Seleccione al menos una cuota';
        redirect("/modules/loans/collect.php?loan_id=$loan_id_pay");
    }

    $db->begin_transaction();
    try {
        $total_due = 0;
        foreach ($installment_ids as $iid) {
            $iid = intval($iid);
            $inst = $db->query("SELECT * FROM loan_installments WHERE id=$iid AND loan_id=$loan_id_pay AND status='pendiente'")->fetch_assoc();
            if ($inst) $total_due += $inst['amount'];
        }

        if ($amount_paid < $total_due) {
            throw new Exception("El monto pagado ($amount_paid) es menor que el total de cuotas seleccionadas ($total_due)");
        }

        foreach ($installment_ids as $iid) {
            $iid = intval($iid);
            $db->query("UPDATE loan_installments SET status='pagada', paid_date=CURDATE() WHERE id=$iid AND loan_id=$loan_id_pay AND status='pendiente'");
        }

        $pending = $db->query("SELECT COUNT(*) as c FROM loan_installments WHERE loan_id=$loan_id_pay AND status='pendiente'")->fetch_assoc()['c'];
        if ($pending == 0) {
            $db->query("UPDATE loans SET status='pagado' WHERE id=$loan_id_pay");
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
?>
<div class="container-fluid">
    <h4 class="mb-3">Cobro de Cuotas de Préstamo</h4>

    <?php if (!$loan_id): ?>
    <div class="card">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-md-8">
                    <select name="loan_id" class="form-select" required>
                        <option value="">Seleccionar préstamo activo...</option>
                        <?php while($l = $clients_with_loans->fetch_assoc()): 
                            $loans_of = $db->query("SELECT * FROM loans WHERE client_id={$l['id']} AND status='activo' AND $where");
                            while($lo = $loans_of->fetch_assoc()):
                        ?>
                        <option value="<?= $lo['id'] ?>"><?= h($l['name']) ?> - Préstamo #<?= $lo['id'] ?> (<?= number_format($lo['total_amount'],2) ?> <?= $lo['currency'] ?>)</option>
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

    <?php if (isset($loan) && $loan): ?>
    <div class="card mb-3">
        <div class="card-header">
            <strong><?= h($loan['client_name']) ?></strong> - <?= h($loan['cedula_rif']) ?>
            | Préstamo #<?= $loan['id'] ?> | Saldo: <?= number_format($loan['total_amount'],2) ?> <?= $loan['currency'] ?>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="loan_id" value="<?= $loan['id'] ?>">
                <div class="table-responsive mb-3">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAll" onchange="toggleAll(this)"></th>
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
                                <td>
                                    <?php if ($inst['status'] === 'pendiente'): ?>
                                    <input type="checkbox" name="installment_ids[]" value="<?= $inst['id'] ?>" class="installment-check" data-amount="<?= $inst['amount'] ?>">
                                    <?php endif; ?>
                                </td>
                                <td><?= $inst['installment_number'] ?></td>
                                <td><?= date('d/m/Y', strtotime($inst['due_date'])) ?></td>
                                <td><?= number_format($inst['amount'],2) ?> <?= $loan['currency'] ?></td>
                                <td>
                                    <span class="badge bg-<?= $inst['status']=='pagada'?'success':($inst['status']=='vencida'?'danger':'warning') ?>">
                                        <?= $inst['status'] ?>
                                    </span>
                                </td>
                                <td><?= $inst['paid_date'] ? date('d/m/Y', strtotime($inst['paid_date'])) : '-' ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Total Seleccionado</label>
                        <h5 id="total-selected" class="text-primary">0.00 <?= $loan['currency'] ?></h5>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Monto a Pagar</label>
                        <input type="number" name="amount_paid" id="amount_paid" class="form-control" step="0.01" min="0" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Moneda</label>
                        <select name="currency" class="form-select">
                            <option value="<?= $loan['currency'] ?>"><?= $loan['currency'] ?></option>
                            <option value="<?= $loan['currency'] == 'EUR' ? 'BS' : 'EUR' ?>"><?= $loan['currency'] == 'EUR' ? 'Bolívares' : 'Euros' ?></option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-check-circle"></i> Cobrar Cuotas Seleccionadas</button>
                <a href="history.php" class="btn btn-secondary">Cancelar</a>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>
<script>
function toggleAll(source) {
    document.querySelectorAll('.installment-check').forEach(cb => cb.checked = source.checked);
    updateTotal();
}
document.querySelectorAll('.installment-check').forEach(cb => cb.addEventListener('change', updateTotal));
function updateTotal() {
    let total = 0;
    document.querySelectorAll('.installment-check:checked').forEach(cb => {
        total += parseFloat(cb.dataset.amount) || 0;
    });
    document.getElementById('total-selected').textContent = total.toFixed(2);
    document.getElementById('amount_paid').value = total.toFixed(2);
}
</script>
<?php require_once '../../includes/footer.php'; ?>
