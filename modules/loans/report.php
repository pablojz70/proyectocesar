<?php
require_once '../../config/database.php';
$page_title = 'Reporte de Préstamos';
require_once '../../includes/header.php';

$db = getDB();
$user_id = $_SESSION['user_id'];
$is_admin = isAdmin();
$where = "1=1";

$fecha_desde = $_GET['fecha_desde'] ?? date('Y-01-01');
$fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');

$total_prestado = $db->query("SELECT COALESCE(SUM(amount),0) as total FROM loans WHERE $where AND DATE(created_at) BETWEEN '$fecha_desde' AND '$fecha_hasta'")->fetch_assoc()['total'];
$total_intereses = $db->query("SELECT COALESCE(SUM(total_interest),0) as total FROM loans WHERE $where AND DATE(created_at) BETWEEN '$fecha_desde' AND '$fecha_hasta'")->fetch_assoc()['total'];
$activos = $db->query("SELECT COUNT(*) as c, COALESCE(SUM(total_amount),0) as t FROM loans WHERE $where AND status='activo'")->fetch_assoc();
$pagados_count = $db->query("SELECT COUNT(*) as c, COALESCE(SUM(total_amount),0) as t FROM loans WHERE $where AND status='pagado' AND DATE(created_at) BETWEEN '$fecha_desde' AND '$fecha_hasta'")->fetch_assoc();
$vencidos = $db->query("SELECT l.*, c.name as client_name FROM loans l JOIN clients c ON c.id=l.client_id WHERE $where AND l.status='vencido' ORDER BY l.id DESC LIMIT 10");
$proximas_cuotas = $db->query("
    SELECT li.*, l.client_id, c.name as client_name, l.currency 
    FROM loan_installments li 
    JOIN loans l ON l.id=li.loan_id 
    JOIN clients c ON c.id=l.client_id 
    WHERE $where AND li.status='pendiente' AND li.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) 
    ORDER BY li.due_date ASC LIMIT 10
");
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Reporte de Préstamos</h4>
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
                    <h3><?= number_format($total_prestado,2) ?></h3>
                    <small>Total Prestado</small>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card bg-gradient-success">
                    <h3><?= number_format($pagados_count['t'],2) ?></h3>
                    <small>Total Cobrado</small>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card bg-gradient-info">
                    <h3><?= number_format($total_intereses,2) ?></h3>
                    <small>Intereses Generados</small>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card bg-gradient-warning">
                    <h3><?= $activos['c'] ?> Activos / <?= $pagados_count['c'] ?> Pagados</h3>
                    <small>Préstamos</small>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-header">Préstamos Vencidos</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>Cliente</th><th>Monto</th><th>Estado</th></tr></thead>
                                <tbody>
                                    <?php if ($vencidos->num_rows === 0): ?>
                                    <tr><td colspan="3" class="text-center text-muted">No hay préstamos vencidos</td></tr>
                                    <?php endif; ?>
                                    <?php while($v = $vencidos->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= h($v['client_name']) ?></td>
                                        <td><?= number_format($v['total_amount'],2) ?> <?= $v['currency'] ?></td>
                                        <td><span class="badge bg-danger">Vencido</span></td>
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
                    <div class="card-header">Próximos Cobros (30 días)</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>Cliente</th><th>Cuota</th><th>Vence</th><th>Monto</th></tr></thead>
                                <tbody>
                                    <?php if ($proximas_cuotas->num_rows === 0): ?>
                                    <tr><td colspan="4" class="text-center text-muted">No hay cobros próximos</td></tr>
                                    <?php endif; ?>
                                    <?php while($pc = $proximas_cuotas->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= h($pc['client_name']) ?></td>
                                        <td>#<?= $pc['installment_number'] ?></td>
                                        <td><?= date('d/m/Y', strtotime($pc['due_date'])) ?></td>
                                        <td><?= number_format($pc['amount'],2) ?> <?= $pc['currency'] ?></td>
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
