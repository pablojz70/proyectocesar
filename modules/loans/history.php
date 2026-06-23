<?php
require_once '../../config/database.php';
$page_title = 'Historial de Préstamos';
require_once '../../includes/header.php';

$db = getDB();
$user_id = $_SESSION['user_id'];
$is_admin = isAdmin();
$where = "1=1";

$search_client = intval($_GET['client_id'] ?? 0);
$estado = $_GET['estado'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';

if ($search_client > 0) $where .= " AND l.client_id = $search_client";
if ($estado) $where .= " AND l.status = '$estado'";
if ($fecha_desde) $where .= " AND DATE(l.created_at) >= '$fecha_desde'";
if ($fecha_hasta) $where .= " AND DATE(l.created_at) <= '$fecha_hasta'";

$loans = $db->query("SELECT l.*, c.name as client_name, c.cedula_rif,
    COALESCE((SELECT SUM(amount) FROM loan_payments WHERE loan_id=l.id),0) as total_abonado
    FROM loans l
    JOIN clients c ON c.id=l.client_id
    WHERE $where
    ORDER BY l.id DESC");
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Historial de Préstamos</h4>
        <div class="no-print">
            <button class="btn btn-secondary" onclick="printDiv('print-area')"><i class="bi bi-printer"></i></button>
            <button class="btn btn-success" onclick="exportToExcel('loans-table', 'prestamos_<?= date('Ymd') ?>')"><i class="bi bi-file-earmark-excel"></i></button>
            <a href="register.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Nuevo Préstamo</a>
        </div>
    </div>
    <div class="card mb-3 no-print">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-md-2">
                    <label class="form-label">Cliente</label>
                    <select name="client_id" class="form-select">
                        <option value="">Todos</option>
                        <?php $clients = $db->query("SELECT DISTINCT c.id, c.name FROM loans l JOIN clients c ON c.id=l.client_id WHERE $where GROUP BY c.id ORDER BY c.name"); ?>
                        <?php while($c = $clients->fetch_assoc()): ?>
                        <option value="<?= $c['id'] ?>" <?= $search_client==$c['id']?'selected':'' ?>><?= h($c['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select">
                        <option value="">Todos</option>
                        <option value="activo" <?= $estado=='activo'?'selected':'' ?>>Activo</option>
                        <option value="pagado" <?= $estado=='pagado'?'selected':'' ?>>Pagado</option>
                        <option value="vencido" <?= $estado=='vencido'?'selected':'' ?>>Vencido</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Desde</label>
                    <input type="date" name="fecha_desde" class="form-control" value="<?= $fecha_desde ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Hasta</label>
                    <input type="date" name="fecha_hasta" class="form-control" value="<?= $fecha_hasta ?>">
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
                <table class="table table-hover mb-0" id="loans-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Cliente</th>
                            <th>Moneda</th>
                            <th>Capital</th>
                            <th>%</th>
                            <th>Inicio</th>
                            <th>Tipo</th>
                            <th>Mora</th>
                            <th>Deuda</th>
                            <th>Cap. Restante</th>
                            <th>Abono</th>
                            <th>Estado</th>
                            <th class="no-print">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($loans->num_rows === 0): ?>
                        <tr><td colspan="13" class="text-center py-4 text-muted">No hay pr&eacute;stamos registrados</td></tr>
                        <?php endif; ?>
                        <?php while($l = $loans->fetch_assoc()): 
                            if ($l['loan_type'] === 'plazo') {
                                $cv = $l['term_months'] > 0 ? $l['total_amount'] / $l['term_months'] : 0;
                                $pc = $cv > 0 ? floor($l['total_abonado'] / $cv) : 0;
                                $cuota_actual = $pc + 1;
                                $fecha_venc = date('Y-m-d', strtotime($l['start_date'] . " +$cuota_actual months"));
                                $hoy = new DateTime();
                                $venc = new DateTime($fecha_venc);
                                if ($hoy > $venc) {
                                    $diff = $venc->diff($hoy);
                                    $mora = ($diff->y * 12) + $diff->m;
                                    $mora += $diff->d > 15 ? 1 : 0;
                                } else {
                                    $mora = 0;
                                }
                            } else {
                                $start = new DateTime($l['start_date']);
                                $now = new DateTime();
                                $diff = $start->diff($now);
                                $meses_total = ($diff->y * 12) + $diff->m;
                                $meses_total += $diff->d > 15 ? 1 : 0;
                                $meses_pagados = $l['monthly_payment'] > 0 ? floor($l['total_abonado'] / $l['monthly_payment']) : 0;
                                $mora = max(0, $meses_total - $meses_pagados);
                            }
                        ?>
                        <tr>
                            <td><?= $l['id'] ?></td>
                            <td><?= h($l['client_name']) ?></td>
                            <td><?= $l['currency'] ?></td>
                            <td><?= number_format($l['amount'],2) ?></td>
                            <td><?= intval($l['interest_rate']) ?>%</td>
                            <td><?= date('d/m/Y', strtotime($l['start_date'])) ?></td>
                            <td><?= $l['loan_type'] === 'mensual' ? 'Mensual' : $l['term_months'] . ' meses' ?></td>
                            <td><?= $l['status'] === 'pagado' ? '-' : $mora ?></td>
                            <td><strong><?= $l['loan_type'] === 'mensual' ? number_format($l['amount'] + ($l['monthly_payment'] * ($mora > 0 ? $mora : 1)),2) : number_format(max(0, $l['total_amount'] - $l['total_abonado']),2) ?></strong></td>
                            <td><?php
                                if ($l['loan_type'] === 'plazo') {
                                    $cv = $l['term_months'] > 0 ? $l['total_amount'] / $l['term_months'] : 0;
                                    $pc = $cv > 0 ? floor($l['total_abonado'] / $cv) : 0;
                                    echo max(0, $l['term_months'] - $pc) . ' cuotas';
                                } else {
                                    $a_pagar = $l['monthly_payment'] * ($mora > 0 ? $mora : 1);
                                    $exc = max(0, $l['total_abonado'] - $a_pagar);
                                    echo number_format(max(0, $l['amount'] - $exc),2);
                                }
                            ?></td>
                            <td><?= $l['total_abonado'] > 0 ? number_format($l['total_abonado'],2) : '-' ?></td>
                            <td>
                                <span class="badge bg-<?= $l['status']=='activo'?'warning':($l['status']=='pagado'?'success':'danger') ?>">
                                    <?= $l['status'] ?>
                                </span>
                            </td>
                            <td class="no-print">
                                <a href="collect.php?loan_id=<?= $l['id'] ?>" class="btn btn-sm btn-success" title="Cobrar"><i class="bi bi-cash"></i></a>
                                <a href="edit.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-warning" title="Editar"><i class="bi bi-pencil"></i></a>
                                <a href="delete.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirmDelete('¿Eliminar pr&eacute;stamo #<?= $l['id'] ?>?')" title="Eliminar"><i class="bi bi-trash"></i></a>
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
