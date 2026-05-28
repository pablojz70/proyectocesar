<?php
require_once '../../config/database.php';
$page_title = 'Reporte de Inventario';
require_once '../../includes/header.php';

$db = getDB();
$user_id = $_SESSION['user_id'];
$is_admin = isAdmin();
$where = $is_admin ? "1=1" : "user_id = $user_id";

$products = $db->query("SELECT * FROM products WHERE $where ORDER BY stock ASC");
$total_productos = $db->query("SELECT COUNT(*) as c, SUM(stock) as s FROM products WHERE $where")->fetch_assoc();
$sin_stock = $db->query("SELECT COUNT(*) as c FROM products WHERE $where AND stock = 0")->fetch_assoc()['c'];
$bajo_stock = $db->query("SELECT COUNT(*) as c FROM products WHERE $where AND stock > 0 AND stock <= 5")->fetch_assoc()['c'];
?>
<div class="container-fluid">
    <h4 class="mb-3">Reporte de Inventario</h4>
    <button class="btn btn-secondary mb-3" onclick="printDiv('print-area')"><i class="bi bi-printer"></i></button>
    <div id="print-area">
        <div class="row mb-3">
            <div class="col-md-3 mb-3">
                <div class="stat-card bg-gradient-primary">
                    <h3><?= $total_productos['c'] ?></h3>
                    <small>Total Productos</small>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card bg-gradient-success">
                    <h3><?= $total_productos['s'] ?></h3>
                    <small>Unidades en Stock</small>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card bg-gradient-danger">
                    <h3><?= $sin_stock ?></h3>
                    <small>Sin Stock</small>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card bg-gradient-warning">
                    <h3><?= $bajo_stock ?></h3>
                    <small>Stock Bajo (≤5)</small>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">Stock Actual de Productos</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr><th>Producto</th><th>Tipo</th><th>Stock</th><th>Precio EURO</th><th>Valor Total EURO</th></tr>
                        </thead>
                        <tbody>
                            <?php while($p = $products->fetch_assoc()): ?>
                            <tr class="<?= $p['stock']==0?'table-danger':($p['stock']<=5?'table-warning':'') ?>">
                                <td><?= h($p['name']) ?></td>
                                <td><span class="badge bg-secondary"><?= h($p['type']) ?></span></td>
                                <td><strong><?= $p['stock'] ?></strong></td>
                                <td>€<?= number_format($p['price_eur'],2) ?></td>
                                <td>€<?= number_format($p['stock'] * $p['price_eur'],2) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
