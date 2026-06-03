<?php
require_once '../../config/database.php';

$db = getDB();
$id = intval($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];
$is_admin = isAdmin();
$where = "1=1";

$sale = $db->query("SELECT s.*, c.name as client_name FROM sales s JOIN clients c ON c.id=s.client_id WHERE s.id=$id AND $where")->fetch_assoc();
if (!$sale) { $_SESSION['error'] = 'Venta no encontrada'; redirect('/modules/sales/history.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = intval($_POST['client_id']);
    $sale_type = $_POST['sale_type'];
    $payment_currency = $_POST['payment_currency'];
    $status = $_POST['status'];
    $installments = intval($_POST['installments'] ?? 1);
    $exchange_rate = floatval($_POST['exchange_rate'] ?? 0);

    $stmt = $db->prepare("UPDATE sales SET client_id=?, sale_type=?, payment_currency=?, status=?, installments=?, exchange_rate=? WHERE id=? AND $where");
    $stmt->bind_param("issssdi", $client_id, $sale_type, $payment_currency, $status, $installments, $exchange_rate, $id);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Venta #' . $id . ' actualizada';
        redirect('/modules/sales/history.php');
    } else {
        $_SESSION['error'] = 'Error: ' . $db->error;
    }
}

$clients = $db->query("SELECT * FROM clients WHERE 1=1 ORDER BY name ASC");
$items = $db->query("SELECT si.*, p.name as product_name FROM sale_items si JOIN products p ON p.id=si.product_id WHERE si.sale_id=$id");

$page_title = 'Editar Venta';
require_once '../../includes/header.php';
?>
<div class="container-fluid">
    <h4 class="mb-3">Editar Venta #<?= $id ?></h4>
    <div class="card">
        <div class="card-body">
            <form method="POST">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Cliente</label>
                        <select name="client_id" class="form-select">
                            <?php while($c = $clients->fetch_assoc()): ?>
                            <option value="<?= $c['id'] ?>" <?= $c['id']==$sale['client_id']?'selected':'' ?>><?= h($c['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Tipo Venta</label>
                        <select name="sale_type" class="form-select">
                            <option value="contado" <?= $sale['sale_type']=='contado'?'selected':'' ?>>Contado</option>
                            <option value="credito" <?= $sale['sale_type']=='credito'?'selected':'' ?>>Cr&eacute;dito</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Tipo Pago</label>
                        <select name="payment_currency" class="form-select">
                            <option value="EFECTIVO" <?= $sale['payment_currency']=='EFECTIVO'?'selected':'' ?>>Efectivo $</option>
                            <option value="EURO" <?= $sale['payment_currency']=='EURO'?'selected':'' ?>>EURO €</option>
                            <option value="BCV" <?= $sale['payment_currency']=='BCV'?'selected':'' ?>>BCV</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Estado</label>
                        <select name="status" class="form-select">
                            <option value="pagada" <?= $sale['status']=='pagada'?'selected':'' ?>>Pagada</option>
                            <option value="pendiente" <?= $sale['status']=='pendiente'?'selected':'' ?>>Pendiente</option>
                            <option value="parcial" <?= $sale['status']=='parcial'?'selected':'' ?>>Parcial</option>
                        </select>
                    </div>
                    <div class="col-md-2" id="cuotas_div" style="display:<?= $sale['sale_type']=='credito'?'block':'none' ?>">
                        <label class="form-label">N° Cuotas</label>
                        <input type="number" name="installments" class="form-control" value="<?= $sale['installments'] ?>" min="1">
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3" id="tasa_div" style="display:<?= $sale['payment_currency']=='BCV'?'block':'none' ?>">
                        <label class="form-label">Tasa de Cambio</label>
                        <input type="number" name="exchange_rate" class="form-control" step="0.01" value="<?= $sale['exchange_rate'] ?>">
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-header">Productos de la Venta</div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Producto</th><th>Cantidad</th><th>Efectivo $</th><th>EURO €</th><th>Ref</th></tr></thead>
                            <tbody>
                                <?php while($item = $items->fetch_assoc()): ?>
                                <tr>
                                    <td><?= h($item['product_name']) ?></td>
                                    <td><?= $item['quantity'] ?></td>
                                    <td>$<?= number_format($item['unit_price_efectivo'],2) ?></td>
                                    <td>€<?= number_format($item['unit_price_euro'],2) ?></td>
                                    <td>Ref. <?= number_format($item['unit_price_bs'],2) ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                <a href="detail.php?id=<?= $id ?>" class="btn btn-info">Ver Detalle</a>
                <a href="history.php" class="btn btn-secondary">Volver</a>
            </form>
        </div>
    </div>
</div>
<script>
document.querySelector('[name=sale_type]').addEventListener('change', function() {
    document.getElementById('cuotas_div').style.display = this.value === 'credito' ? 'block' : 'none';
});
document.querySelector('[name=payment_currency]').addEventListener('change', function() {
    document.getElementById('tasa_div').style.display = this.value === 'BCV' ? 'block' : 'none';
});
</script>
<?php require_once '../../includes/footer.php'; ?>
