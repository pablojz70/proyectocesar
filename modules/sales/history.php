<?php
require_once '../../config/database.php';
$page_title = 'Historial de Ventas';
require_once '../../includes/header.php';

$db = getDB();
$user_id = $_SESSION['user_id'];
$is_admin = isAdmin();

$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-01');
$fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
$client_id = intval($_GET['client_id'] ?? 0);
$tipo = $_GET['tipo'] ?? '';
$estado = $_GET['estado'] ?? '';

$where = $is_admin ? "1=1" : "s.user_id = $user_id";
$where .= " AND DATE(s.created_at) BETWEEN '$fecha_desde' AND '$fecha_hasta'";
if ($client_id > 0) $where .= " AND s.client_id = $client_id";
if ($tipo) $where .= " AND s.sale_type = '$tipo'";
if ($estado) $where .= " AND s.status = '$estado'";

$sales = $db->query("SELECT s.*, c.name as client_name FROM sales s JOIN clients c ON c.id=s.client_id WHERE $where ORDER BY s.id DESC");
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Historial de Ventas</h4>
        <div class="no-print">
            <button class="btn btn-secondary" onclick="printDiv('print-area')"><i class="bi bi-printer"></i></button>
            <button class="btn btn-success" onclick="exportToExcel('sales-table', 'ventas_<?= date('Ymd') ?>')"><i class="bi bi-file-earmark-excel"></i></button>
            <a href="register.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Nueva Venta</a>
        </div>
    </div>
    <div class="card mb-3 no-print">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-md-2">
                    <label class="form-label">Desde</label>
                    <input type="date" name="fecha_desde" class="form-control" value="<?= $fecha_desde ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Hasta</label>
                    <input type="date" name="fecha_hasta" class="form-control" value="<?= $fecha_hasta ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tipo</label>
                    <select name="tipo" class="form-select">
                        <option value="">Todos</option>
                        <option value="contado" <?= $tipo=='contado'?'selected':'' ?>>Contado</option>
                        <option value="credito" <?= $tipo=='credito'?'selected':'' ?>>Cr&eacute;dito</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select">
                        <option value="">Todos</option>
                        <option value="pagada" <?= $estado=='pagada'?'selected':'' ?>>Pagada</option>
                        <option value="pendiente" <?= $estado=='pendiente'?'selected':'' ?>>Pendiente</option>
                        <option value="parcial" <?= $estado=='parcial'?'selected':'' ?>>Parcial</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                </div>
            </form>
        </div>
    </div>
    <div class="card" id="print-area">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="sales-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Cliente</th>
                            <th>Efectivo $</th>
                            <th>EURO €</th>
                            <th>Bs</th>
                            <th>Tipo</th>
                            <th>Pago</th>
                            <th>Estado</th>
                            <th>Cuotas</th>
                            <th>Fecha</th>
                            <th class="no-print">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($sales->num_rows === 0): ?>
                        <tr><td colspan="11" class="text-center py-4 text-muted">No hay ventas en este per&iacute;odo</td></tr>
                        <?php endif; ?>
                        <?php while($s = $sales->fetch_assoc()): ?>
                        <tr>
                            <td><?= $s['id'] ?></td>
                            <td><?= h($s['client_name']) ?></td>
                            <td>$<?= $s['total_efectivo'] > 0 ? number_format($s['total_efectivo'],2) : '-' ?></td>
                            <td>€<?= $s['total_euro'] > 0 ? number_format($s['total_euro'],2) : '-' ?></td>
                            <td>Bs. <?= $s['total_bs'] > 0 ? number_format($s['total_bs'],2) : '-' ?></td>
                            <td><span class="badge bg-<?= $s['sale_type']=='contado'?'success':'warning' ?>"><?= $s['sale_type'] ?></span></td>
                            <td><span class="badge bg-secondary"><?= $s['payment_currency'] ?></span></td>
                            <td>
                                <span class="badge bg-<?= $s['status']=='pagada'?'success':($s['status']=='pendiente'?'danger':'info') ?>">
                                    <?= $s['status'] ?>
                                </span>
                            </td>
                            <td><?= $s['installments'] ?></td>
                            <td><?= date('d/m/Y', strtotime($s['created_at'])) ?></td>
                            <td class="no-print">
                                <a href="detail.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-info" title="Ver detalle"><i class="bi bi-eye"></i></a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
