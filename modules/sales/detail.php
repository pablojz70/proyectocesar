<?php
require_once '../../config/database.php';
$page_title = 'Detalle de Venta';
require_once '../../includes/header.php';

$db = getDB();
$id = intval($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];
$is_admin = isAdmin();
$where = $is_admin ? "1=1" : "s.user_id = $user_id";

$sale = $db->query("SELECT s.*, c.name as client_name, c.cedula_rif, c.phone FROM sales s JOIN clients c ON c.id=s.client_id WHERE s.id=$id AND $where")->fetch_assoc();
if (!$sale) { $_SESSION['error'] = 'Venta no encontrada'; redirect('/modules/sales/history.php'); }

$items = $db->query("SELECT si.*, p.name as product_name FROM sale_items si JOIN products p ON p.id=si.product_id WHERE si.sale_id=$id");
$payments = $db->query("SELECT * FROM payments WHERE sale_id=$id ORDER BY payment_date");
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Venta #<?= $id ?></h4>
        <div>
            <button class="btn btn-secondary" onclick="printDiv('print-area')"><i class="bi bi-printer"></i></button>
            <?php if ($sale['sale_type'] === 'credito' && $sale['status'] !== 'pagada'): ?>
            <a href="../payments/register_payment.php?sale_id=<?= $id ?>&client_id=<?= $sale['client_id'] ?>" class="btn btn-success"><i class="bi bi-cash"></i> Registrar Pago</a>
            <?php endif; ?>
            <a href="history.php" class="btn btn-primary"><i class="bi bi-arrow-left"></i> Volver</a>
        </div>
    </div>
    <div id="print-area">
        <div class="row mb-3">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">Datos del Cliente</div>
                    <div class="card-body">
                        <p><strong>Nombre:</strong> <?= h($sale['client_name']) ?></p>
                        <p><strong>C&eacute;dula/RIF:</strong> <?= h($sale['cedula_rif']) ?></p>
                        <p><strong>Tel&eacute;fono:</strong> <?= h($sale['phone']) ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">Datos de la Venta</div>
                    <div class="card-body">
                        <p><strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($sale['created_at'])) ?></p>
                        <p><strong>Tipo:</strong> <span class="badge bg-<?= $sale['sale_type']=='contado'?'success':'warning' ?>"><?= $sale['sale_type'] ?></span></p>
                        <p><strong>Pago:</strong> <span class="badge bg-secondary"><?= $sale['payment_currency'] ?></span></p>
                        <p><strong>Estado:</strong> <span class="badge bg-<?= $sale['status']=='pagada'?'success':($sale['status']=='pendiente'?'danger':'info') ?>"><?= $sale['status'] ?></span></p>
                        <p><strong>Tasa:</strong> Bs. <?= number_format($sale['exchange_rate'],2) ?></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header">Productos</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr><th>Producto</th><th>Cantidad</th><th>Efectivo $</th><th>EURO €</th><th>BCV Ref</th><th>Subtotal $</th><th>Subtotal €</th><th>Total (Tasa&times;Ref)</th></tr>
                        </thead>
                        <tbody>
                            <?php while($item = $items->fetch_assoc()): ?>
                            <tr>
                                <td><?= h($item['product_name']) ?></td>
                                <td><?= $item['quantity'] ?></td>
                                <td>$<?= number_format($item['unit_price_efectivo'],2) ?></td>
                                <td>€<?= number_format($item['unit_price_euro'],2) ?></td>
                                <td>Ref. <?= number_format($item['unit_price_bs'],2) ?></td>
                                <td>$<?= number_format($item['quantity']*$item['unit_price_efectivo'],2) ?></td>
                                <td>€<?= number_format($item['quantity']*$item['unit_price_euro'],2) ?></td>
                                <td>Bs. <?= number_format($item['quantity']*$item['unit_price_bs']*$sale['exchange_rate'],2) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold">
                                <td colspan="4" class="text-end">Totales:</td>
                                <td>$<?= number_format($sale['total_efectivo'],2) ?></td>
                                <td>€<?= number_format($sale['total_euro'],2) ?></td>
                                <td>Bs. <?= number_format($sale['total_bs'],2) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        <?php if ($sale['sale_type'] === 'credito'): ?>
        <div class="card">
            <div class="card-header">Pagos Registrados</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>#</th><th>Efectivo $</th><th>EURO €</th><th>BCV Ref</th><th>Tasa</th><th>Fecha</th></tr></thead>
                        <tbody>
                            <?php if ($payments->num_rows === 0): ?>
                            <tr><td colspan="6" class="text-center text-muted">Sin pagos registrados</td></tr>
                            <?php endif; ?>
                            <?php while($p = $payments->fetch_assoc()): ?>
                            <tr>
                                <td><?= $p['id'] ?></td>
                                <td>$<?= number_format($p['amount_efectivo'],2) ?></td>
                                <td>€<?= number_format($p['amount_euro'],2) ?></td>
                                <td>Bs. <?= number_format($p['amount_bs'],2) ?></td>
                                <td><?= $p['exchange_rate'] ? number_format($p['exchange_rate'],2) : '-' ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($p['payment_date'])) ?></td>
                            </tr>
                            <?php endwhile; ?>
                            <tr class="fw-bold">
                                <td>$<?= number_format($sale['total_efectivo'],2) ?></td>
                                <td>€<?= number_format($sale['total_euro'],2) ?></td>
                                <td>Bs. <?= number_format($sale['total_bs'],2) ?></td>
                                <td colspan="3">Total Pagado</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
