<?php
require_once __DIR__ . '/config/database.php';
$page_title = 'Inicio';
require_once __DIR__ . '/includes/header.php';

$db = getDB();
$user_id = $_SESSION['user_id'];
$is_admin = isAdmin();

$where = "1=1";
$where_c = "1=1";
$where_p = "1=1";

$total_clients = $db->query("SELECT COUNT(*) as c FROM clients WHERE $where_c")->fetch_assoc()['c'];
$total_products = $db->query("SELECT COUNT(*) as c FROM products WHERE $where_p")->fetch_assoc()['c'];
$total_sales = $db->query("SELECT COUNT(*) as c, COALESCE(SUM(total_efectivo),0) as t FROM sales WHERE $where")->fetch_assoc();
$pending_sales = $db->query("SELECT COUNT(*) as c FROM sales WHERE $where AND sale_type='credito' AND status!='pagada'")->fetch_assoc()['c'];
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Inicio</h4>
        <small class="text-muted">Bienvenido, <?= h($_SESSION['full_name']) ?></small>
    </div>
    <div class="row mb-4">
        <?php if ($is_admin): ?>
        <div class="col-md-3 mb-3">
            <div class="stat-card bg-gradient-primary">
                <h3><?= $total_clients ?></h3>
                <small>Clientes Registrados</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card bg-gradient-success">
                <h3><?= $total_products ?></h3>
                <small>Productos</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card bg-gradient-info">
                <h3><?= $total_sales['c'] ?></h3>
                <small>Ventas Totales</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card bg-gradient-warning">
                <h3><?= number_format($total_sales['t'], 2) ?>$</h3>
                <small>Ventas en Efectivo $</small>
            </div>
        </div>
        <?php else: ?>
        <div class="col-md-4 mb-3">
            <div class="stat-card bg-gradient-primary">
                <h3><?= $total_clients ?></h3>
                <small>Clientes Registrados</small>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="stat-card bg-gradient-success">
                <h3><?= $total_products ?></h3>
                <small>Productos</small>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card p-3">
                <h6>Tasa BCV Hoy</h6>
                <h3 id="tasa-display" class="text-primary">Cargando...</h3>
                <button class="btn btn-sm btn-outline-primary" onclick="actualizarTasa()">Actualizar</button>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php if ($is_admin): ?>
    <div class="row mb-4">
        <div class="col-md-6 mb-3">
            <div class="stat-card bg-gradient-danger">
                <h3><?= $pending_sales ?></h3>
                <small>Ventas a Crédito Pendientes</small>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card p-3">
                <h6>Tasa BCV Hoy</h6>
                <h3 id="tasa-display" class="text-primary">Cargando...</h3>
                <button class="btn btn-sm btn-outline-primary" onclick="actualizarTasa()">Actualizar</button>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($is_admin): ?>
    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header">Últimas Ventas</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Cliente</th><th>Total $</th><th>Tipo</th><th>Estado</th><th>Fecha</th></tr></thead>
                            <tbody>
                                <?php $recent = $db->query("SELECT s.*, c.name as client_name FROM sales s JOIN clients c ON c.id=s.client_id WHERE $where ORDER BY s.id DESC LIMIT 5"); ?>
                                <?php while($r = $recent->fetch_assoc()): ?>
                                <tr>
                                    <td><?= h($r['client_name']) ?></td>
                                    <td>$<?= number_format($r['total_efectivo'],2) ?></td>
                                    <td><span class="badge bg-<?= $r['sale_type']=='contado'?'success':'warning' ?>"><?= $r['sale_type'] ?></span></td>
                                    <td><span class="badge bg-<?= $r['status']=='pagada'?'success':($r['status']=='pendiente'?'danger':'info') ?>"><?= $r['status'] ?></span></td>
                                    <td><?= date('d/m/Y', strtotime($r['created_at'])) ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header">Últimos Pr&eacute;stamos</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Cliente</th><th>Monto</th><th>Tipo</th><th>Estado</th></tr></thead>
                            <tbody>
                                <?php $loans = $db->query("SELECT l.*, c.name as client_name FROM loans l JOIN clients c ON c.id=l.client_id WHERE $where ORDER BY l.id DESC LIMIT 5"); ?>
                                <?php while($l = $loans->fetch_assoc()): ?>
                                <tr>
                                    <td><?= h($l['client_name']) ?></td>
                                    <td><?= number_format($l['amount'],2) ?> <?= $l['currency'] ?></td>
                                    <td><span class="badge bg-secondary"><?= $l['loan_type'] ?></span></td>
                                    <td><span class="badge bg-<?= $l['status']=='activo'?'warning':($l['status']=='pagado'?'success':'danger') ?>"><?= $l['status'] ?></span></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <script>
function actualizarTasa() {
    fetch('<?= BASE_URL ?>/api/get_exchange_rate.php')
        .then(r => r.json())
        .then(d => { document.getElementById('tasa-display').textContent = d.rate ? d.rate + ' Bs/EUR' : 'No disponible'; })
        .catch(() => { document.getElementById('tasa-display').textContent = 'Error al cargar'; });
}
actualizarTasa();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
