<?php
require_once '../../config/database.php';

$db = getDB();
$id = intval($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];
$is_admin = isAdmin();
$where = "1=1";

$loan = $db->query("SELECT l.*, c.name as client_name FROM loans l JOIN clients c ON c.id=l.client_id WHERE l.id=$id AND $where")->fetch_assoc();
if (!$loan) { $_SESSION['error'] = 'Préstamo no encontrado'; redirect('/modules/loans/history.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'];
    $amount = floatval($_POST['amount']);
    $interest_rate = floatval($_POST['interest_rate']);
    $loan_type = $_POST['loan_type'];
    $term_months = $loan_type === 'mensual' ? 1 : intval($_POST['term_months']);
    $currency = $_POST['currency'];

    if ($loan_type === 'mensual') {
        $total_interest = $amount * ($interest_rate / 100);
        $total_amount = $amount;
        $monthly_payment = $total_interest;
    } else {
        $total_interest = $amount * ($interest_rate / 100) * $term_months;
        $total_amount = $amount + $total_interest;
        $monthly_payment = $term_months > 0 ? $total_amount / $term_months : $total_amount;
    }

    $db->begin_transaction();
    try {
        $stmt = $db->prepare("UPDATE loans SET amount=?, interest_rate=?, term_months=?, loan_type=?, currency=?, total_interest=?, total_amount=?, monthly_payment=?, status=? WHERE id=? AND $where");
        $stmt->bind_param("ddissdddsi", $amount, $interest_rate, $term_months, $loan_type, $currency, $total_interest, $total_amount, $monthly_payment, $status, $id);
        $stmt->execute();

        // Regenerar cuotas
        $db->query("DELETE FROM loan_installments WHERE loan_id=$id");
        if ($loan_type === 'plazo') {
            for ($i = 1; $i <= $term_months; $i++) {
                $due_date = date('Y-m-d', strtotime($loan['start_date'] . " +$i months"));
                $ins_amount = ($i == $term_months) ? ($total_amount - ($monthly_payment * ($term_months - 1))) : $monthly_payment;
                $stmt2 = $db->prepare("INSERT INTO loan_installments (loan_id, installment_number, due_date, amount, status) VALUES (?, ?, ?, ?, 'pendiente')");
                $stmt2->bind_param("iisd", $id, $i, $due_date, $ins_amount);
                $stmt2->execute();
            }
        } else {
            $due_date = date('Y-m-d', strtotime($loan['start_date'] . " +1 month"));
            $stmt2 = $db->prepare("INSERT INTO loan_installments (loan_id, installment_number, due_date, amount, status) VALUES (?, 1, ?, ?, 'pendiente')");
            $stmt2->bind_param("isd", $id, $due_date, $total_interest);
            $stmt2->execute();
        }

        $db->commit();
        $_SESSION['success'] = 'Préstamo #' . $id . ' actualizado';
        redirect('/modules/loans/history.php');
    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
    }
}

$page_title = 'Editar Préstamo';
require_once '../../includes/header.php';
?>
<div class="container-fluid">
    <h4 class="mb-3">Editar Préstamo #<?= $id ?></h4>
    <div class="card">
        <div class="card-body">
            <p><strong>Cliente:</strong> <?= h($loan['client_name']) ?></p>
            <form method="POST" id="loanForm">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Monto <span class="text-danger">*</span></label>
                        <input type="number" name="amount" id="amount" class="form-control" step="0.01" min="0" value="<?= $loan['amount'] ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Interés Mensual (%) <span class="text-danger">*</span></label>
                        <input type="number" name="interest_rate" class="form-control" step="1" min="0" value="<?= $loan['interest_rate'] ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Moneda <span class="text-danger">*</span></label>
                        <select name="currency" class="form-select">
                            <option value="EUR" <?= $loan['currency']=='EUR'?'selected':'' ?>>EUR</option>
                            <option value="BS" <?= $loan['currency']=='BS'?'selected':'' ?>>BS</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Tipo <span class="text-danger">*</span></label>
                        <select name="loan_type" id="loan_type" class="form-select" onchange="toggleTipo()">
                            <option value="mensual" <?= $loan['loan_type']=='mensual'?'selected':'' ?>>Mensual</option>
                            <option value="plazo" <?= $loan['loan_type']=='plazo'?'selected':'' ?>>Plazo</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Estado</label>
                        <select name="status" class="form-select">
                            <option value="activo" <?= $loan['status']=='activo'?'selected':'' ?>>Activo</option>
                            <option value="pagado" <?= $loan['status']=='pagado'?'selected':'' ?>>Pagado</option>
                            <option value="vencido" <?= $loan['status']=='vencido'?'selected':'' ?>>Vencido</option>
                        </select>
                    </div>
                </div>
                <div class="row mb-3" id="plazo_div" style="display:<?= $loan['loan_type']=='plazo'?'block':'none' ?>">
                    <div class="col-md-3">
                        <label class="form-label">Plazo (meses) <span class="text-danger">*</span></label>
                        <input type="number" name="term_months" id="term_months" class="form-control" min="1" value="<?= $loan['term_months'] ?: 6 ?>">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                <a href="history.php" class="btn btn-secondary">Cancelar</a>
            </form>
        </div>
    </div>
</div>
<script>
function toggleTipo() {
    document.getElementById('plazo_div').style.display = document.getElementById('loan_type').value === 'plazo' ? 'block' : 'none';
}
</script>
<?php require_once '../../includes/footer.php'; ?>
