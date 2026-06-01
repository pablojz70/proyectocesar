<?php
require_once '../../config/database.php';
$page_title = 'Reporte de Rentabilidad';
require_once '../../includes/header.php';

$db = getDB();
$user_id = $_SESSION['user_id'];
$is_admin = isAdmin();
$where = $is_admin ? "1=1" : "p.user_id = $user_id";
$where_s = $is_admin ? "1=1" : "s.user_id = $user_id";

$mas_vendidos = $db->query("
    SELECT p.name, p.type, SUM(si.quantity) as total_qty, SUM(si.quantity * si.unit_price_efectivo) as total_revenue
    FROM sale_items si
    JOIN products p ON p.id=si.product_id
    JOIN sales s ON s.id=si.sale_id
    WHERE $where AND $where_s
    GROUP BY si.product_id
    ORDER BY total_qty DESC
    LIMIT 10
");

$mas_rentables = $db->query("
    SELECT p.name, p.type, AVG(si.unit_price_efectivo) as avg_price, SUM(si.quantity * si.unit_price_efectivo) as total_venta,
        (SUM(si.quantity * si.unit_price_efectivo) * 0.3) as estimado_costo
    FROM sale_items si
    JOIN products p ON p.id=si.product_id
    JOIN sales s ON s.id=si.sale_id
    WHERE $where AND $where_s
    GROUP BY si.product_id
    ORDER BY total_venta DESC
    LIMIT 10
");

$margen = $db->query("
    SELECT COALESCE(AVG(si.unit_price_efectivo),0) as precio_promedio FROM sale_items si
    JOIN sales s ON s.id=si.sale_id
    WHERE $where_s
")->fetch_assoc();
?>
<div class="container-fluid">
    <h4 class="mb-3">Reporte de Rentabilidad</h4>
    <button class="btn btn-secondary mb-3" onclick="printDiv('print-area')"><i class="bi bi-printer"></i></button>
    <div id="print-area">
        <div class="row mb-3">
            <div class="col-md-4 mb-3">
                <div class="stat-card bg-gradient-info">
                    <h3>$<?= number_format($margen['precio_promedio'],2) ?></h3>
                    <small>Precio Promedio ($)</small>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-header">Productos Más Vendidos</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>Producto</th><th>Tipo</th><th>Cantidad</th><th>Efectivo $</th></tr></thead>
                                <tbody>
                                    <?php while($mv = $mas_vendidos->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= h($mv['name']) ?></td>
                                        <td><span class="badge bg-secondary"><?= h($mv['type']) ?></span></td>
                                        <td><?= $mv['total_qty'] ?></td>
                                        <td>$<?= number_format($mv['total_revenue'],2) ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-header">Productos Más Rentables</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>Producto</th><th>Total Ventas</th></tr></thead>
                                <tbody>
                                    <?php while($mr = $mas_rentables->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= h($mr['name']) ?></td>
                                        <td><strong>$<?= number_format($mr['total_venta'],2) ?></strong></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
