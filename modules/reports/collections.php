<?php
require_once '../../config/database.php';
$page_title = 'Reporte de Cobranzas';
require_once '../../includes/header.php';

$db = getDB();
$user_id = $_SESSION['user_id'];
$is_admin = isAdmin();
$where = "1=1";

$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-01');
$fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');

$morosos = $db->query("
    SELECT c.id, c.name, c.cedula_rif, c.phone,
        COUNT(s.id) as deudas_pendientes,
        SUM(s.total_efectivo - COALESCE((SELECT SUM(amount_efectivo) FROM payments WHERE sale_id=s.id),0)) as total_deuda_eur
    FROM sales s
    JOIN clients c ON c.id=s.client_id
    WHERE $where AND s.sale_type='credito' AND s.status!='pagada'
    GROUP BY s.client_id
    HAVING total_deuda_eur > 0
    ORDER BY total_deuda_eur DESC
");

$pagos_periodo = $db->query("
    SELECT COUNT(*) as total_pagos, COALESCE(SUM(p.amount_efectivo),0) as total_efectivo, COALESCE(SUM(p.amount_bs),0) as total_bs
    FROM payments p
    JOIN sales s ON s.id=p.sale_id
    WHERE $where AND DATE(p.payment_date) BETWEEN '$fecha_desde' AND '$fecha_hasta'
")->fetch_assoc();

$credito_total = $db->query("SELECT COALESCE(SUM(total_efectivo),0) as t FROM sales WHERE $where AND sale_type='credito'")->fetch_assoc()['t'];
$cobrado_total = $db->query("SELECT COALESCE(SUM(p.amount_efectivo),0) as t FROM payments p JOIN sales s ON s.id=p.sale_id WHERE $where")->fetch_assoc()['t'];
$eficiencia = $credito_total > 0 ? ($cobrado_total / $credito_total * 100) : 0;
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Reporte de Cobranzas</h4>
        <button class="btn btn-secondary" onclick="printDiv('print-area')"><i class="bi bi-printer"></i></button>
    </div>
    <div class="card mb-3 no-print">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-md-3">
                    <label class="form-label">Desde</label>
                    <input type="date" name="fecha_desde" class="form-control" value="<?= $fecha_desde ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Hasta</label>
                    <input type="date" name="fecha_hasta" class="form-control" value="<?= $fecha_hasta ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                </div>
            </form>
        </div>
    </div>
    <div id="print-area">
        <div class="row mb-3">
            <div class="col-md-3 mb-3">
                <div class="stat-card bg-gradient-primary">
                    <h3><?= $pagos_periodo['total_pagos'] ?></h3>
                    <small>Pagos Registrados</small>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card bg-gradient-success">
                    <h3>$<?= number_format($pagos_periodo['total_efectivo'],2) ?></h3>
                    <small>Cobrado en $</small>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card bg-gradient-info">
                    <h3>Bs. <?= number_format($pagos_periodo['total_bs'],2) ?></h3>
                    <small>Cobrado en Bs</small>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card bg-gradient-warning">
                    <h3><?= number_format($eficiencia,1) ?>%</h3>
                    <small>Eficiencia de Cobranza</small>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">Clientes Morosos</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr><th>Cliente</th><th>Cédula</th><th>Teléfono</th><th>Deudas Pendientes</th><th>Total Deuda $</th></tr>
                        </thead>
                        <tbody>
                            <?php if ($morosos->num_rows === 0): ?>
                            <tr><td colspan="5" class="text-center text-muted">No hay clientes morosos</td></tr>
                            <?php endif; ?>
                            <?php while($m = $morosos->fetch_assoc()): ?>
                            <tr class="table-danger">
                                <td><strong><?= h($m['name']) ?></strong></td>
                                <td><?= h($m['cedula_rif']) ?></td>
                                <td><?= h($m['phone']) ?></td>
                                <td><?= $m['deudas_pendientes'] ?></td>
                                <td><strong>$<?= number_format($m['total_deuda_eur'],2) ?></strong></td>
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
