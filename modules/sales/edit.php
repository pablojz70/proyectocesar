<?php
require_once '../../config/database.php';

$db = getDB();
$id = intval($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];
$is_admin = isAdmin();
$where = "1=1";

$sale = $db->query("SELECT s.*, c.name as client_name FROM sales s JOIN clients c ON c.id=s.client_id WHERE s.id=$id AND $where")->fetch_assoc();
if (!$sale) { $_SESSION['error'] = 'Venta no encontrada'; redirect('/modules/sales/history.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = intval($_POST['client_id']);
    $sale_type = $_POST['sale_type'];
    $payment_currency = $_POST['payment_currency'];
    $status = $_POST['status'];
    $installments = intval($_POST['installments'] ?? 1);
    $exchange_rate = floatval($_POST['exchange_rate'] ?? 0);
    $product_ids = $_POST['product_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $delete_items = $_POST['delete_item'] ?? [];

    $db->begin_transaction();
    try {
        // Restaurar stock de items eliminados
        foreach ($delete_items as $di) {
            $di = intval($di);
            $item = $db->query("SELECT * FROM sale_items WHERE id=$di AND sale_id=$id")->fetch_assoc();
            if ($item) {
                $db->query("UPDATE products SET stock = stock + {$item['quantity']} WHERE id = {$item['product_id']}");
                $db->query("DELETE FROM sale_items WHERE id=$di");
            }
        }

        // Eliminar items viejos que no estan en la nueva lista si se reemplazaron
        $old_items = $db->query("SELECT id FROM sale_items WHERE sale_id=$id");
        while ($oi = $old_items->fetch_assoc()) {
            if (!in_array($oi['id'], $delete_items)) {
                // Mantener items existentes
            }
        }

        // Agregar/quitar productos nuevos
        $total_efectivo = 0;
        $total_euro = 0;
        $total_bs = 0;

        // Procesar items nuevos
        for ($i = 0; $i < count($product_ids); $i++) {
            $pid = intval($product_ids[$i]);
            $qty = intval($quantities[$i]);
            if ($qty <= 0 || !$pid) continue;

            $prod = $db->query("SELECT * FROM products WHERE id = $pid")->fetch_assoc();
            if (!$prod) throw new Exception("Producto no encontrado");
            if ($prod['stock'] < $qty) throw new Exception("Stock insuficiente para: " . $prod['name']);

            $db->query("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price_efectivo, unit_price_euro, unit_price_bs) VALUES ($id, $pid, $qty, {$prod['price_efectivo']}, {$prod['price_euro']}, {$prod['price_bcv']})");
            $db->query("UPDATE products SET stock = stock - $qty WHERE id = $pid");
            $total_efectivo += $qty * $prod['price_efectivo'];
            $total_euro += $qty * $prod['price_euro'];
            $total_bs += $qty * $prod['price_bcv'];
        }

        // Recalcular totales de items existentes
        $items = $db->query("SELECT si.*, p.price_efectivo, p.price_euro, p.price_bcv FROM sale_items si JOIN products p ON p.id=si.product_id WHERE si.sale_id=$id");
        while ($it = $items->fetch_assoc()) {
            $total_efectivo += $it['quantity'] * $it['price_efectivo'];
            $total_euro += $it['quantity'] * $it['price_euro'];
            $total_bs += $it['quantity'] * $it['price_bcv'];
        }

        $stmt = $db->prepare("UPDATE sales SET client_id=?, sale_type=?, payment_currency=?, exchange_rate=?, total_efectivo=?, total_euro=?, total_bs=?, status=?, installments=? WHERE id=? AND $where");
        $stmt->bind_param("issdddddsi", $client_id, $sale_type, $payment_currency, $exchange_rate, $total_efectivo, $total_euro, $total_bs, $status, $installments, $id);
        $stmt->execute();

        $db->commit();
        $_SESSION['success'] = 'Venta #' . $id . ' actualizada';
        redirect('/modules/sales/history.php');
    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
    }
}

$clients = $db->query("SELECT * FROM clients WHERE 1=1 ORDER BY name ASC");
$products = $db->query("SELECT * FROM products WHERE 1=1 AND stock > 0 ORDER BY name ASC");
$items = $db->query("SELECT si.*, p.name as product_name FROM sale_items si JOIN products p ON p.id=si.product_id WHERE si.sale_id=$id");

$page_title = 'Editar Venta';
require_once '../../includes/header.php';
?>
<div class="container-fluid">
    <h4 class="mb-3">Editar Venta #<?= $id ?></h4>
    <div class="card">
        <div class="card-body">
            <form method="POST" id="editSaleForm" novalidate>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Cliente</label>
                        <select name="client_id" class="form-select">
                            <?php while($c = $clients->fetch_assoc()): ?>
                            <option value="<?= $c['id'] ?>" <?= $c['id']==$sale['client_id']?'selected':'' ?>><?= h($c['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Tipo Venta</label>
                        <select name="sale_type" id="sale_type" class="form-select" onchange="toggleEditFields()">
                            <option value="contado" <?= $sale['sale_type']=='contado'?'selected':'' ?>>Contado</option>
                            <option value="credito" <?= $sale['sale_type']=='credito'?'selected':'' ?>>Cr&eacute;dito</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Tipo Pago</label>
                        <select name="payment_currency" id="payment_currency" class="form-select" onchange="toggleEditFields()">
                            <option value="EFECTIVO" <?= $sale['payment_currency']=='EFECTIVO'?'selected':'' ?>>Efectivo $</option>
                            <option value="EURO" <?= $sale['payment_currency']=='EURO'?'selected':'' ?>>EURO €</option>
                            <option value="BCV" <?= $sale['payment_currency']=='BCV'?'selected':'' ?>>BCV</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Estado</label>
                        <select name="status" class="form-select">
                            <option value="pagada" <?= $sale['status']=='pagada'?'selected':'' ?>>Pagada</option>
                            <option value="pendiente" <?= $sale['status']=='pendiente'?'selected':'' ?>>Pendiente</option>
                            <option value="parcial" <?= $sale['status']=='parcial'?'selected':'' ?>>Parcial</option>
                        </select>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3" id="edit_cuotas_div" style="display:<?= $sale['sale_type']=='credito'?'block':'none' ?>">
                        <label class="form-label">N° Cuotas</label>
                        <input type="number" name="installments" class="form-control" value="<?= $sale['installments'] ?>" min="1">
                    </div>
                    <div class="col-md-3" id="edit_tasa_div" style="display:<?= $sale['payment_currency']=='BCV'?'block':'none' ?>">
                        <label class="form-label">Tasa de Cambio</label>
                        <input type="number" name="exchange_rate" class="form-control" step="0.01" value="<?= $sale['exchange_rate'] ?>">
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between">
                        <span>Productos de la Venta</span>
                        <button type="button" class="btn btn-sm btn-success" onclick="addEditProductRow()"><i class="bi bi-plus"></i> Agregar Producto</button>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr><th>Producto</th><th>Cantidad</th><th>Efectivo $</th><th>EURO €</th><th>Ref</th><th></th></tr>
                            </thead>
                            <tbody id="edit-items-body">
                                <?php while($item = $items->fetch_assoc()): ?>
                                <tr class="edit-item-row">
                                    <td><?= h($item['product_name']) ?> <input type="hidden" name="keep_item[]" value="<?= $item['id'] ?>"></td>
                                    <td><?= $item['quantity'] ?></td>
                                    <td>$<?= number_format($item['unit_price_efectivo'],2) ?></td>
                                    <td>€<?= number_format($item['unit_price_euro'],2) ?></td>
                                    <td>Ref. <?= number_format($item['unit_price_bs'],2) ?></td>
                                    <td><button type="button" class="btn btn-sm btn-danger" onclick="marcarEliminar(this)" title="Eliminar (restaura stock)"><i class="bi bi-trash"></i> <input type="hidden" name="delete_item[]" value=""></button></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card mb-3" id="new-products-card" style="display:none">
                    <div class="card-header">Agregar Nuevos Productos</div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Producto</th><th>Cantidad</th><th>Precio $</th><th>Precio €</th><th>Ref</th><th></th></tr></thead>
                            <tbody id="new-products-body"></tbody>
                        </table>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                <a href="detail.php?id=<?= $id ?>" class="btn btn-info">Ver Detalle</a>
                <a href="history.php" class="btn btn-secondary">Volver</a>
            </form>
        </div>
    </div>
</div>
<script>
function toggleEditFields() {
    document.getElementById('edit_cuotas_div').style.display = document.getElementById('sale_type').value === 'credito' ? 'block' : 'none';
    document.getElementById('edit_tasa_div').style.display = document.getElementById('payment_currency').value === 'BCV' ? 'block' : 'none';
}

function marcarEliminar(btn) {
    if (!confirm('¿Eliminar este producto? Se restaurar\u00e1 el stock.')) return;
    var row = btn.closest('tr');
    row.querySelector('[name="delete_item[]"]').value = row.querySelector('[name="keep_item[]"]').value;
    row.style.display = 'none';
}

var prodCache = [];
document.querySelectorAll('#product-select option').forEach(function(o) {
    if (o.value) prodCache[o.value] = { price_efectivo: o.dataset.priceEfectivo, price_euro: o.dataset.priceEuro, price_bs: o.dataset.priceBs, stock: o.dataset.stock };
});

function addEditProductRow() {
    document.getElementById('new-products-card').style.display = 'block';
    var tbody = document.getElementById('new-products-body');
    var row = document.createElement('tr');
    row.className = 'edit-new-row';
    var opts = '';
    <?php mysqli_data_seek($products, 0); while($p = $products->fetch_assoc()): ?>
    opts += '<option value="<?= $p['id'] ?>" data-price-efectivo="<?= $p['price_efectivo'] ?>" data-price-euro="<?= $p['price_euro'] ?>" data-price-bs="<?= $p['price_bcv'] ?>"><?= h($p['name']) ?></option>';
    <?php endwhile; ?>
    row.innerHTML = '<td><select name="product_id[]" class="form-select form-select-sm"><?php mysqli_data_seek($products, 0); while($p = $products->fetch_assoc()): ?><option value="<?= $p['id'] ?>"><?= h($p['name']) ?></option><?php endwhile; ?></select></td>' +
        '<td><input type="number" name="quantity[]" class="form-control form-control-sm" value="1" min="1"></td>' +
        '<td id="pe-0">0.00</td><td id="peu-0">0.00</td><td id="pbs-0">0.00</td>' +
        '<td><button type="button" class="btn btn-sm btn-danger" onclick="this.closest(\'tr\').remove()"><i class="bi bi-x"></i></button></td>';
    tbody.appendChild(row);
}
</script>
<?php require_once '../../includes/footer.php'; ?>
