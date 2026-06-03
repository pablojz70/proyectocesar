<?php
require_once '../../config/database.php';
$page_title = 'Editar Venta';
require_once '../../includes/header.php';

$db = getDB();
$id = intval($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];
$is_admin = isAdmin();
$where = "1=1";

$sale = $db->query("SELECT s.*, c.name as client_name FROM sales s JOIN clients c ON c.id=s.client_id WHERE s.id=$id AND $where")->fetch_assoc();
if (!$sale) { $_SESSION['error'] = 'Venta no encontrada'; redirect('/modules/sales/history.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sale_type = $_POST['sale_type'];
    $status = $_POST['status'];
    $stmt = $db->prepare("UPDATE sales SET sale_type=?, status=? WHERE id=? AND $where");
    $stmt->bind_param("ssi", $sale_type, $status, $id);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Venta #' . $id . ' actualizada';
        redirect('/modules/sales/history.php');
    } else {
        $_SESSION['error'] = 'Error: ' . $db->error;
    }
}
?>
<div class="container-fluid">
    <h4 class="mb-3">Editar Venta #<?= $id ?></h4>
    <div class="card">
        <div class="card-body">
            <p><strong>Cliente:</strong> <?= h($sale['client_name']) ?></p>
            <p><strong>Total:</strong> $<?= number_format($sale['total_efectivo'],2) ?> / €<?= number_format($sale['total_euro'],2) ?> / Bs. <?= number_format($sale['total_bs'],2) ?></p>
            <form method="POST">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Tipo de Venta</label>
                        <select name="sale_type" class="form-select">
                            <option value="contado" <?= $sale['sale_type']=='contado'?'selected':'' ?>>Contado</option>
                            <option value="credito" <?= $sale['sale_type']=='credito'?'selected':'' ?>>Cr&eacute;dito</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Estado</label>
                        <select name="status" class="form-select">
                            <option value="pagada" <?= $sale['status']=='pagada'?'selected':'' ?>>Pagada</option>
                            <option value="pendiente" <?= $sale['status']=='pendiente'?'selected':'' ?>>Pendiente</option>
                            <option value="parcial" <?= $sale['status']=='parcial'?'selected':'' ?>>Parcial</option>
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
