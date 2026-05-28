<?php
require_once '../../config/database.php';
$page_title = 'Reporte de Ventas';
require_once '../../includes/header.php';

$db = getDB();
$user_id = $_SESSION['user_id'];
$is_admin = isAdmin();
$where = $is_admin ? "1=1" : "user_id = $user_id";

$periodo = $_GET['periodo'] ?? 'mes';

switch($periodo) {
    case 'dia': $group = "DATE(created_at)"; $label = "Hoy"; $fecha_desde = date('Y-m-d'); break;
    case 'semana': $group = "WEEK(created_at)"; $label = "Esta Semana"; $fecha_desde = date('Y-m-d', strtotime('-7 days')); break;
    case 'mes': default: $group = "DATE_FORMAT(created_at,'%Y-%m')"; $label = "Este Mes"; $fecha_desde = date('Y-m-01'); break;
}

$ventas_periodo = $db->query("SELECT COUNT(*) as total_ventas, COALESCE(SUM(total_efectivo),0) as total_efectivo, COALESCE(SUM(total_bs),0) as total_bs FROM sales WHERE $where AND DATE(created_at) >= '$fecha_desde'")->fetch_assoc();
$contado = $db->query("SELECT COUNT(*) as c, COALESCE(SUM(total_efectivo),0) as t FROM sales WHERE $where AND sale_type='contado' AND DATE(created_at) >= '$fecha_desde'")->fetch_assoc();
$credito = $db->query("SELECT COUNT(*) as c, COALESCE(SUM(total_efectivo),0) as t FROM sales WHERE $where AND sale_type='credito' AND DATE(created_at) >= '$fecha_desde'")->fetch_assoc();
$top_clientes = $db->query("SELECT c.name, COUNT(s.id) as total_ventas, SUM(s.total_efectivo) as total FROM sales s JOIN clients c ON c.id=s.client_id WHERE $where AND DATE(s.created_at) >= '$fecha_desde' GROUP BY s.client_id ORDER BY total DESC LIMIT 5");
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Reporte de Ventas</h4>
        <button class="btn btn-secondary" onclick="printDiv('print-area')"><i class="bi bi-printer"></i></button>
    </div>
    <div class="card mb-3 no-print">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-md-3">
                    <select name="periodo" class="form-select">
                        <option value="dia" <?= $periodo=='dia'?'selected':'' ?>>Hoy</option>
                        <option value="semana" <?= $periodo=='semana'?'selected':'' ?>>Esta Semana</option>
                        <option value="mes" <?= $periodo=='mes'?'selected':'' ?>>Este Mes</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">Actualizar</button>
                </div>
            </form>
        </div>
    </div>
    <div id="print-area">
        <div class="row mb-3">
            <div class="col-md-3 mb-3">
                <div class="stat-card bg-gradient-primary">
                    <h3><?= $ventas_periodo['total_ventas'] ?></h3>
                    <small>Ventas del período</small>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card bg-gradient-success">
                    <h3>$<?= number_format($ventas_periodo['total_efectivo'],2) ?></h3>
                    <small>Efectivo $</small>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card bg-gradient-info">
                    <h3>Bs. <?= number_format($ventas_periodo['total_bs'],2) ?></h3>
                    <small>Total Bolívares</small>
                </div>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-header">Ventas por Tipo</div>
                    <div class="card-body">
                        <table class="table">
                            <tr>
                                <td><span class="badge bg-success">Contado</span></td>
                                <td><?= $contado['c'] ?> ventas</td>
                                <td><strong>$<?= number_format($contado['t'],2) ?></strong></td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-warning">Crédito</span></td>
                                <td><?= $credito['c'] ?> ventas</td>
                                <td><strong>$<?= number_format($credito['t'],2) ?></strong></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-header">Top 5 Clientes</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>Cliente</th><th>Ventas</th><th>Efectivo $</th></tr></thead>
                                <tbody>
                                    <?php while($tc = $top_clientes->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= h($tc['name']) ?></td>
                                        <td><?= $tc['total_ventas'] ?></td>
                                        <td>$<?= number_format($tc['total'],2) ?></td>
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
