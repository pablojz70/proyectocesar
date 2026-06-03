<?php
require_once '../../config/database.php';
$page_title = 'Consultar Deuda de Cliente por productos Adquiridos';
require_once '../../includes/header.php';

$db = getDB();
$user_id = $_SESSION['user_id'];
$is_admin = isAdmin();

$search = $_GET['search'] ?? '';
$client_id = intval($_GET['client_id'] ?? 0);

$where_clients = $is_admin ? "1=1" : "user_id = $user_id";

if ($client_id > 0) {
    $deudas = $db->query("
        SELECT s.*, c.name as client_name, c.cedula_rif,
            (s.total_efectivo - COALESCE((SELECT SUM(amount_efectivo) FROM payments WHERE sale_id=s.id),0)) as saldo_efectivo,
            (s.total_euro - COALESCE((SELECT SUM(amount_euro) FROM payments WHERE sale_id=s.id),0)) as saldo_euro,
            (s.total_bs - COALESCE((SELECT SUM(amount_bs) FROM payments WHERE sale_id=s.id),0)) as saldo_bs
        FROM sales s
        JOIN clients c ON c.id=s.client_id
        WHERE s.id IN (SELECT sale_id FROM sale_items) AND s.sale_type='credito' AND s.status!='pagada'
        AND s.client_id = $client_id
        AND $where_clients
        ORDER BY s.created_at DESC
    ");
    $client = $db->query("SELECT * FROM clients WHERE id=$client_id AND $where_clients")->fetch_assoc();
}
?>
<div class="container-fluid">
    <h4 class="mb-3">Consultar Deuda de Cliente por productos Adquiridos</h4>
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-md-8">
                    <input type="text" name="search" class="form-control" placeholder="Buscar cliente por nombre o c&eacute;dula..." value="<?= h($search) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Buscar</button>
                </div>
                <div class="col-md-2">
                    <a href="consult_debt.php" class="btn btn-secondary w-100">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <?php if ($search && !$client_id): ?>
    <div class="card mb-3">
        <div class="card-body">
            <h5>Resultados de b&uacute;squeda</h5>
            <div class="list-group">
                <?php $s = $db->real_escape_string($search);
                $results = $db->query("SELECT * FROM clients WHERE $where_clients AND (name LIKE '%$s%' OR cedula_rif LIKE '%$s%') LIMIT 10"); ?>
                <?php if ($results->num_rows === 0): ?>
                <p class="text-muted">No se encontraron clientes</p>
                <?php endif; ?>
                <?php while($r = $results->fetch_assoc()): ?>
                <a href="?client_id=<?= $r['id'] ?>" class="list-group-item list-group-item-action">
                    <strong><?= h($r['name']) ?></strong> - <?= h($r['cedula_rif']) ?>
                </a>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($client_id > 0 && $client): ?>
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="mb-0"><?= h($client['name']) ?> - <?= h($client['cedula_rif']) ?></h5>
        </div>
        <div class="card-body">
            <?php if (isset($deudas) && $deudas->num_rows === 0): ?>
            <p class="text-success"><i class="bi bi-check-circle"></i> Este cliente no tiene deudas pendientes</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Venta #</th>
                            <th>Fecha</th>
                            <th>Total $</th>
                            <th>Total €</th>
                            <th>Total Bs</th>
                            <th>Saldo $</th>
                            <th>Saldo €</th>
                            <th>Saldo Bs</th>
                            <th>Cuotas</th>
                            <th>Acci&oacute;n</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $total_deuda_efectivo = 0; $total_deuda_euro = 0; $total_deuda_bs = 0; ?>
                        <?php while($d = $deudas->fetch_assoc()): 
                            $total_deuda_efectivo += $d['saldo_efectivo'];
                            $total_deuda_euro += $d['saldo_euro'];
                            $total_deuda_bs += $d['saldo_bs'];
                        ?>
                        <tr>
                            <td>#<?= $d['id'] ?></td>
                            <td><?= date('d/m/Y', strtotime($d['created_at'])) ?></td>
                            <td>$<?= number_format($d['total_efectivo'],2) ?></td>
                            <td>€<?= number_format($d['total_euro'],2) ?></td>
                            <td>Bs. <?= number_format($d['total_bs'],2) ?></td>
                            <td><strong>$<?= number_format($d['saldo_efectivo'],2) ?></strong></td>
                            <td><strong>€<?= number_format($d['saldo_euro'],2) ?></strong></td>
                            <td><strong>Bs. <?= number_format($d['saldo_bs'],2) ?></strong></td>
                            <td><?= $d['installments'] ?></td>
                            <td>
                                <a href="register_payment.php?sale_id=<?= $d['id'] ?>&client_id=<?= $client_id ?>" class="btn btn-sm btn-success">Cobrar</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                    <tfoot class="fw-bold">
                        <tr>
                            <td colspan="4" class="text-end">Total Adeudado:</td>
                            <td>$<?= number_format($total_deuda_efectivo,2) ?></td>
                            <td>€<?= number_format($total_deuda_euro,2) ?></td>
                            <td>Bs. <?= number_format($total_deuda_bs,2) ?></td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <a href="register_payment.php?client_id=<?= $client_id ?>" class="btn btn-success"><i class="bi bi-cash"></i> Registrar Pago</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php require_once '../../includes/footer.php'; ?>
