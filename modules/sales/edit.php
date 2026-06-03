<?php
require_once '../../config/database.php';

$db = getDB();
$id = intval($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];
$is_admin = isAdmin();
$where = "1=1";

$sale = $db->query("SELECT s.*, c.name as client_name FROM sales s JOIN clients c ON c.id=s.client_id WHERE s.id=$id AND $where")->fetch_assoc();
if (!$sale) { $_SESSION['error'] = 'Venta no encontrada'; redirect('/modules/sales/history.php'); }

$existing_items = $db->query("SELECT si.*, p.name as product_name FROM sale_items si JOIN products p ON p.id=si.product_id WHERE si.sale_id=$id");
$old_stock_deductions = [];
while ($item = $existing_items->fetch_assoc()) {
    $old_stock_deductions[$item['product_id']] = ($old_stock_deductions[$item['product_id']] ?? 0) + $item['quantity'];
}

$products = $db->query("SELECT * FROM products WHERE 1=1 ORDER BY name ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = intval($_POST['client_id']);
    $sale_type = $_POST['sale_type'];
    $payment_currency = $_POST['payment_currency'];
    $installments = intval($_POST['installments'] ?? 1);
    $exchange_rate = floatval($_POST['exchange_rate'] ?? 0);
    $product_ids = $_POST['product_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];

    if ($payment_currency === 'BCV' && $exchange_rate <= 0) {
        $_SESSION['error'] = 'Debe ingresar la tasa de cambio';
        redirect("/modules/sales/edit.php?id=$id");
    }

    $total_efectivo = 0; $total_euro = 0; $total_bs = 0; $items = [];

    $db->begin_transaction();
    try {
        // Restore old stock
        foreach ($old_stock_deductions as $pid => $qty) {
            $db->query("UPDATE products SET stock = stock + $qty WHERE id = $pid");
        }

        // Process new items
        for ($i = 0; $i < count($product_ids); $i++) {
            $pid = intval($product_ids[$i]);
            $qty = intval($quantities[$i]);
            if ($qty <= 0) continue;

            $prod = $db->query("SELECT * FROM products WHERE id = $pid")->fetch_assoc();
            if (!$prod) throw new Exception("Producto no encontrado");
            if ($prod['stock'] < $qty) throw new Exception("Stock insuficiente para: " . $prod['name']);

            $items[] = [
                'product_id' => $pid, 'quantity' => $qty,
                'unit_price_efectivo' => $prod['price_efectivo'],
                'unit_price_euro' => $prod['price_euro'],
                'unit_price_bs' => $prod['price_bcv']
            ];
            $total_efectivo += $qty * $prod['price_efectivo'];
            $total_euro += $qty * $prod['price_euro'];
            $total_bs += $qty * $prod['price_bcv'];
        }
        if (empty($items)) throw new Exception("Debe agregar al menos un producto");

        $status = $sale_type === 'contado' ? 'pagada' : 'pendiente';
        $stmt = $db->prepare("UPDATE sales SET user_id=?, client_id=?, sale_type=?, payment_currency=?, exchange_rate=?, total_efectivo=?, total_euro=?, total_bs=?, status=?, installments=? WHERE id=?");
        $stmt->bind_param("iissddddssi", $user_id, $client_id, $sale_type, $payment_currency, $exchange_rate, $total_efectivo, $total_euro, $total_bs, $status, $installments, $id);
        $stmt->execute();

        // Delete old items and insert new ones
        $db->query("DELETE FROM sale_items WHERE sale_id=$id");
        foreach ($items as $item) {
            $stmt2 = $db->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price_efectivo, unit_price_euro, unit_price_bs) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt2->bind_param("iiiddd", $id, $item['product_id'], $item['quantity'], $item['unit_price_efectivo'], $item['unit_price_euro'], $item['unit_price_bs']);
            $stmt2->execute();
            $db->query("UPDATE products SET stock = stock - {$item['quantity']} WHERE id = {$item['product_id']}");
        }

        $db->commit();
        $_SESSION['success'] = "Venta #$id actualizada exitosamente";
        redirect('/modules/sales/history.php');
    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
    }
}

$clients = $db->query("SELECT * FROM clients WHERE 1=1 ORDER BY name ASC");
$items_list = $db->query("SELECT si.*, p.name as product_name FROM sale_items si JOIN products p ON p.id=si.product_id WHERE si.sale_id=$id");
$rate = getExchangeRate();

$page_title = 'Editar Venta';
require_once '../../includes/header.php';
?>
<div class="container-fluid">
    <h4 class="mb-3">Editar Venta #<?= $id ?></h4>
    <div class="card">
        <div class="card-body">
            <form method="POST" id="saleForm" novalidate>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Cliente <span class="text-danger">*</span></label>
                        <select name="client_id" class="form-select" required>
                            <option value="">Seleccionar cliente...</option>
                            <?php while($c = $clients->fetch_assoc()): ?>
                            <option value="<?= $c['id'] ?>" <?= $c['id']==$sale['client_id']?'selected':'' ?>><?= h($c['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tipo de Venta</label>
                        <select name="sale_type" id="sale_type" class="form-select" onchange="toggleFields()">
                            <option value="contado" <?= $sale['sale_type']=='contado'?'selected':'' ?>>Contado</option>
                            <option value="credito" <?= $sale['sale_type']=='credito'?'selected':'' ?>>Cr&eacute;dito</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tipo de Pago</label>
                        <select name="payment_currency" id="payment_currency" class="form-select" onchange="toggleFields()">
                            <option value="EFECTIVO" <?= $sale['payment_currency']=='EFECTIVO'?'selected':'' ?>>Efectivo $</option>
                            <option value="EURO" <?= $sale['payment_currency']=='EURO'?'selected':'' ?>>EURO €</option>
                            <option value="BCV" <?= $sale['payment_currency']=='BCV'?'selected':'' ?>>BCV</option>
                        </select>
                    </div>
                    <div class="col-md-2" id="cuotas_div" style="display:<?= $sale['sale_type']=='credito'?'block':'none' ?>">
                        <label class="form-label">N&deg; Cuotas</label>
                        <input type="number" name="installments" class="form-control" value="<?= $sale['installments'] ?>" min="1">
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4" id="tasa_div" style="display:<?= $sale['payment_currency']=='BCV'?'block':'none' ?>">
                        <label class="form-label">Tasa de Cambio (Bs/Divisa)</label>
                        <div class="input-group">
                            <input type="number" name="exchange_rate" class="form-control" step="0.01" value="<?= $sale['exchange_rate'] ?: $rate ?>">
                            <button type="button" class="btn btn-outline-secondary" onclick="fetchTasa()">Auto</button>
                        </div>
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between">
                        <span>Productos</span>
                        <button type="button" class="btn btn-sm btn-success" onclick="addProductRow()"><i class="bi bi-plus"></i> Agregar</button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table mb-0">
                                <thead>
                                    <tr>
                                        <th>Producto</th><th>Efectivo $</th><th>EURO €</th><th>Ref</th><th>Cantidad</th><th>Subtotal $</th><th>Subtotal €</th><th>Total Ref</th><th></th>
                                    </tr>
                                </thead>
                                <tbody id="products-body">
                                    <?php $idx = 0; mysqli_data_seek($items_list, 0); while($item = $items_list->fetch_assoc()): ?>
                                    <tr class="sale-row">
                                        <td>
                                            <select name="product_id[]" class="form-select product-select">
                                                <option value="">Seleccionar...</option>
                                                <?php mysqli_data_seek($products, 0); while($p = $products->fetch_assoc()): ?>
                                                <option value="<?= $p['id'] ?>" <?= $p['id']==$item['product_id']?'selected':'' ?> data-price-efectivo="<?= $p['price_efectivo'] ?>" data-price-euro="<?= $p['price_euro'] ?>" data-price-bs="<?= $p['price_bcv'] ?>" data-stock="<?= $p['stock'] ?>">
                                                    <?= h($p['name']) ?> (Stock: <?= $p['stock'] ?>)
                                                </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </td>
                                        <td><span class="price-efectivo" data-value="<?= $item['unit_price_efectivo'] ?>"><?= number_format($item['unit_price_efectivo'],2) ?></span></td>
                                        <td><span class="price-euro" data-value="<?= $item['unit_price_euro'] ?>"><?= number_format($item['unit_price_euro'],2) ?></span></td>
                                        <td><span class="price-bs" data-value="<?= $item['unit_price_bs'] ?>"><?= number_format($item['unit_price_bs'],2) ?></span></td>
                                        <td><input type="number" name="quantity[]" class="form-control qty" min="1" value="<?= $item['quantity'] ?>"></td>
                                        <td><span class="subtotal-efectivo"><?= number_format($item['quantity']*$item['unit_price_efectivo'],2) ?></span></td>
                                        <td><span class="subtotal-euro"><?= number_format($item['quantity']*$item['unit_price_euro'],2) ?></span></td>
                                        <td><span class="subtotal-bs"><?= number_format($item['quantity']*$item['unit_price_bs'],2) ?></span></td>
                                        <td><button type="button" class="btn btn-sm btn-danger" onclick="removeProductRow(this)"><i class="bi bi-x"></i></button></td>
                                    </tr>
                                    <?php $idx++; endwhile; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="fw-bold">
                                        <td colspan="4" class="text-end">Totales:</td>
                                        <td id="total-efectivo"><?= number_format($sale['total_efectivo'],2) ?></td>
                                        <td id="total-euro"><?= number_format($sale['total_euro'],2) ?></td>
                                        <td id="total-bs"><?= number_format($sale['total_bs'],2) ?></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-circle"></i> Guardar Cambios</button>
                <a href="detail.php?id=<?= $id ?>" class="btn btn-info">Ver Detalle</a>
                <a href="history.php" class="btn btn-secondary">Volver</a>
            </form>
        </div>
    </div>
</div>
<script>
<?php mysqli_data_seek($products, 0); ?>
let productCache = [];
document.querySelectorAll('.product-select option').forEach(o => {
    if (o.value) productCache[o.value] = { price_efectivo: o.dataset.priceEfectivo, price_euro: o.dataset.priceEuro, price_bs: o.dataset.priceBs, stock: o.dataset.stock };
});

function addProductRow() {
    var tbody = document.getElementById('products-body');
    var first = tbody.querySelector('.sale-row');
    var clone = first.cloneNode(true);
    clone.querySelectorAll('input, select').forEach(el => el.value = '');
    clone.querySelector('input.qty').value = '1';
    clone.querySelector('.subtotal-efectivo').textContent = '0.00';
    clone.querySelector('.subtotal-euro').textContent = '0.00';
    clone.querySelector('.subtotal-bs').textContent = '0.00';
    clone.querySelector('.price-efectivo').dataset.value = '0';
    clone.querySelector('.price-euro').dataset.value = '0';
    clone.querySelector('.price-bs').dataset.value = '0';
    tbody.appendChild(clone);
    attachEvents(clone);
}
function removeProductRow(btn) {
    var rows = document.querySelectorAll('.sale-row');
    if (rows.length > 1) btn.closest('tr').remove();
    calcTotals();
}
function attachEvents(row) {
    row.querySelector('.product-select').addEventListener('change', function() {
        var opt = this.options[this.selectedIndex];
        if (opt.value) {
            row.querySelector('.price-efectivo').textContent = parseFloat(opt.dataset.priceEfectivo).toFixed(2);
            row.querySelector('.price-efectivo').dataset.value = opt.dataset.priceEfectivo;
            row.querySelector('.price-euro').textContent = parseFloat(opt.dataset.priceEuro).toFixed(2);
            row.querySelector('.price-euro').dataset.value = opt.dataset.priceEuro;
            row.querySelector('.price-bs').textContent = parseFloat(opt.dataset.priceBs).toFixed(2);
            row.querySelector('.price-bs').dataset.value = opt.dataset.priceBs;
            row.querySelector('input.qty').max = opt.dataset.stock;
        } else {
            row.querySelector('.price-efectivo').textContent = '0.00';
            row.querySelector('.price-efectivo').dataset.value = '0';
            row.querySelector('.price-euro').textContent = '0.00';
            row.querySelector('.price-euro').dataset.value = '0';
            row.querySelector('.price-bs').textContent = '0.00';
            row.querySelector('.price-bs').dataset.value = '0';
        }
        calcTotals();
    });
    row.querySelector('.qty').addEventListener('input', calcTotals);
}

document.querySelectorAll('.sale-row').forEach(function(r) { attachEvents(r); });

function toggleFields() {
    document.getElementById('cuotas_div').style.display = document.getElementById('sale_type').value === 'credito' ? 'block' : 'none';
    document.getElementById('tasa_div').style.display = document.getElementById('payment_currency').value === 'BCV' ? 'block' : 'none';
}

function fetchTasa() {
    fetch('<?= BASE_URL ?>/api/get_exchange_rate.php')
        .then(r => r.json())
        .then(d => { if (d.rate) document.querySelector('[name=exchange_rate]').value = d.rate; });
}
</script>
<?php require_once '../../includes/footer.php'; ?>
