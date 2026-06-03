<?php
require_once '../../config/database.php';

$db = getDB();
$id = intval($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];
$is_admin = isAdmin();
$where = $is_admin ? "1=1" : "user_id = $user_id";

$loan = $db->query("SELECT l.*, c.name as client_name FROM loans l JOIN clients c ON c.id=l.client_id WHERE l.id=$id AND $where")->fetch_assoc();
if (!$loan) { $_SESSION['error'] = 'Préstamo no encontrado'; redirect('/modules/loans/history.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'];
    $stmt = $db->prepare("UPDATE loans SET status=? WHERE id=? AND $where");
    $stmt->bind_param("si", $status, $id);
    if ($stmt->execute()) {
        if ($status === 'pagado') {
            $db->query("UPDATE loan_installments SET status='pagada', paid_date=CURDATE() WHERE loan_id=$id AND status='pendiente'");
        }
        $_SESSION['success'] = 'Préstamo #' . $id . ' actualizado';
        redirect('/modules/loans/history.php');
    } else {
        $_SESSION['error'] = 'Error: ' . $db->error;
    }
}

$page_title = 'Editar Pr&eacute;stamo';
require_once '../../includes/header.php';
?>
<div class="container-fluid">
    <h4 class="mb-3">Editar Pr&eacute;stamo #<?= $id ?></h4>
    <div class="card">
        <div class="card-body">
            <p><strong>Cliente:</strong> <?= h($loan['client_name']) ?></p>
            <p><strong>Monto:</strong> <?= number_format($loan['amount'],2) ?> <?= $loan['currency'] ?></p>
            <form method="POST">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Estado</label>
                        <select name="status" class="form-select">
                            <option value="activo" <?= $loan['status']=='activo'?'selected':'' ?>>Activo</option>
                            <option value="pagado" <?= $loan['status']=='pagado'?'selected':'' ?>>Pagado</option>
                            <option value="vencido" <?= $loan['status']=='vencido'?'selected':'' ?>>Vencido</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                <a href="history.php" class="btn btn-secondary">Cancelar</a>
            </form>
        </div>
    </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
