<?php
require_once '../../config/database.php';
$page_title = 'Registrar Préstamo';
require_once '../../includes/header.php';

$db = getDB();
$user_id = $_SESSION['user_id'];
$is_admin = isAdmin();
$where = $is_admin ? "1=1" : "user_id = $user_id";

$clients = $db->query("SELECT * FROM clients WHERE $where ORDER BY name ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = intval($_POST['client_id']);
    $currency = $_POST['currency'];
    $amount = floatval($_POST['amount']);
    $interest_rate = floatval($_POST['interest_rate']);
    $term_months = intval($_POST['term_months']);
    $start_date = $_POST['start_date'];
    $user_id_loan = $user_id;

    $total_interest = $amount * ($interest_rate / 100) * $term_months;
    $total_amount = $amount + $total_interest;
    $monthly_payment = $term_months > 0 ? $total_amount / $term_months : $total_amount;

    $db->begin_transaction();
    try {
        $stmt = $db->prepare("INSERT INTO loans (user_id, client_id, currency, amount, interest_rate, term_months, total_interest, total_amount, monthly_payment, start_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'activo')");
        $stmt->bind_param("iisddiddds", $user_id_loan, $client_id, $currency, $amount, $interest_rate, $term_months, $total_interest, $total_amount, $monthly_payment, $start_date);
        $stmt->execute();
        $loan_id = $db->insert_id;

        for ($i = 1; $i <= $term_months; $i++) {
            $due_date = date('Y-m-d', strtotime($start_date . " +$i months"));
            $ins_amount = ($i == $term_months) ? ($total_amount - ($monthly_payment * ($term_months - 1))) : $monthly_payment;
            $stmt2 = $db->prepare("INSERT INTO loan_installments (loan_id, installment_number, due_date, amount, status) VALUES (?, ?, ?, ?, 'pendiente')");
            $stmt2->bind_param("iisd", $loan_id, $i, $due_date, $ins_amount);
            $stmt2->execute();
        }

        $db->commit();
        $_SESSION['success'] = "Préstamo #$loan_id registrado exitosamente";
        redirect('/modules/loans/history.php');
    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
    }
}
?>
<div class="container-fluid">
    <h4 class="mb-3">Registrar Préstamo</h4>
    <div class="card">
        <div class="card-body">
            <form method="POST" id="loanForm">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Cliente <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <select name="client_id" class="form-select" required>
                                <option value="">Seleccionar cliente...</option>
                                <?php while($c = $clients->fetch_assoc()): ?>
                                <option value="<?= $c['id'] ?>"><?= h($c['name']) ?> - <?= h($c['cedula_rif']) ?></option>
                                <?php endwhile; ?>
                            </select>
                            <a href="../clients/create.php" class="btn btn-outline-primary" target="_blank">+ Nuevo</a>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Moneda <span class="text-danger">*</span></label>
                        <select name="currency" class="form-select" required>
                            <option value="EFECTIVO">Euros (EUR)</option>
                            <option value="BS">Bolívares (Bs)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Monto del Préstamo <span class="text-danger">*</span></label>
                        <input type="number" name="amount" id="amount" class="form-control" step="0.01" min="0" required>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Interés Mensual (%) <span class="text-danger">*</span></label>
                        <input type="number" name="interest_rate" id="interest_rate" class="form-control" step="0.01" min="0" value="5" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Plazo (meses) <span class="text-danger">*</span></label>
                        <input type="number" name="term_months" id="term_months" class="form-control" min="1" value="6" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Fecha de Inicio <span class="text-danger">*</span></label>
                        <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="card mb-3 bg-light">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <label class="form-label">Interés Total</label>
                                <h5 id="total_interest_display" class="text-primary">0.00</h5>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Total a Pagar</label>
                                <h5 id="total_amount_display" class="text-danger">0.00</h5>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Cuota Mensual</label>
                                <h5 id="monthly_payment_display" class="text-success">0.00</h5>
                            </div>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-circle"></i> Confirmar Préstamo</button>
                <a href="history.php" class="btn btn-secondary btn-lg">Cancelar</a>
            </form>
        </div>
    </div>
</div>
<script>
document.getElementById('loanForm').addEventListener('input', function() {
    const amount = parseFloat(document.getElementById('amount').value) || 0;
    const rate = parseFloat(document.getElementById('interest_rate').value) || 0;
    const months = parseInt(document.getElementById('term_months').value) || 1;
    const totalInterest = amount * (rate / 100) * months;
    const totalAmount = amount + totalInterest;
    const monthlyPayment = totalAmount / months;
    document.getElementById('total_interest_display').textContent = totalInterest.toFixed(2);
    document.getElementById('total_amount_display').textContent = totalAmount.toFixed(2);
    document.getElementById('monthly_payment_display').textContent = monthlyPayment.toFixed(2);
});
</script>
<?php require_once '../../includes/footer.php'; ?>
