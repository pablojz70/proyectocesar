<?php
require_once '../../config/database.php';

$db = getDB();
$id = intval($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];
$is_admin = isAdmin();
$where = "1=1";

$sale = $db->query("SELECT s.*, c.name as client_name FROM sales s JOIN clients c ON c.id=s.client_id WHERE s.id=$id AND $where")->fetch_assoc();
if (!$sale) { $_SESSION['error'] = 'Venta no encontrada'; redirect('/modules/sales/history.php'); }

$existing_items = $db->query("SELECT si.*, p.name as product_name, p.stock FROM sale_items si JOIN products p ON p.id=si.product_id WHERE si.sale_id=$id");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = intval($_POST['client_id']);
    $sale_type = $_POST['sale_type'];
    $payment_currency = $_POST['payment_currency'];
    $status = $sale_type === 'contado' ? 'pagada' : ($sale['status'] === 'pagada' ? 'pagada' : ($_POST['status'] ?? 'pendiente'));
    $installments = intval($_POST['installments'] ?? 1);
    $exchange_rate = floatval($_POST['exchange_rate'] ?? 0);
    $product_ids = $_POST['product_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];

    if ($exchange_rate <= 0 && $payment_currency === 'BCV') {
        $_SESSION['error'] = 'Debe ingresar la tasa de cambio para BCV';
        redirect("/modules/sales/edit.php?id=$id");
    }

    $db->begin_transaction();
    try {
        $stmt = $db->prepare("UPDATE sales SET client_id=?, sale_type=?, payment_currency=?, exchange_rate=?, status=?, installments=? WHERE id=? AND $where");
        $stmt->bind_param("issdsii", $client_id, $sale_type, $payment_currency, $exchange_rate, $status, $installments, $id);
        $stmt->execute();

        // Restore stock for removed items
        $keep_ids = [];
        foreach ($product_ids as $pid) {
            $keep_ids[] = intval($pid);
        }
        $old_items = $db->query("SELECT * FROM sale_items WHERE sale_id=$id");
        while ($old = $old_items->fetch_assoc()) {
            if (!in_array($old['product_id'], $keep_ids)) {
                $db->query("UPDATE products SET stock = stock + {$old['quantity']} WHERE id = {$old['product_id']}");
                $db->query("DELETE FROM sale_items WHERE id = {$old['id']}");
            }
        }

        // Update existing items and add new ones
        $total_efectivo = 0; $total_euro = 0; $total_bs = 0;

        for ($i = 0; $i < count($product_ids); $i++) {
            $pid = intval($product_ids[$i]);
            $qty = intval($quantities[$i]);
            if ($qty <= 0) continue;

            $prod = $db->query("SELECT * FROM products WHERE id = $pid")->fetch_assoc();
            if (!$prod) throw new Exception("Producto no encontrado");

            // Check if this product was already in the sale
            $existing = $db->query("SELECT * FROM sale_items WHERE sale_id=$id AND product_id=$pid")->fetch_assoc();
            if ($existing) {
                $old_qty = $existing['quantity'];
                $diff = $qty - $old_qty;
                if ($diff > 0 && $prod['stock'] < $diff) throw new Exception("Stock insuficiente para: " . $prod['name']);
                $db->query("UPDATE products SET stock = stock - $diff WHERE id = $pid");
                $db->query("UPDATE sale_items SET quantity=$qty, unit_price_efectivo={$prod['price_efectivo']}, unit_price_euro={$prod['price_euro']}, unit_price_bs={$prod['price_bcv']} WHERE id={$existing['id']}");
            } else {
                if ($prod['stock'] < $qty) throw new Exception("Stock insuficiente para: " . $prod['name']);
                $db->query("UPDATE products SET stock = stock - $qty WHERE id = $pid");
                $stmt2 = $db->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price_efectivo, unit_price_euro, unit_price_bs) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt2->bind_param("iiiddd", $id, $pid, $qty, $prod['price_efectivo'], $prod['price_euro'], $prod['price_bcv']);
                $stmt2->execute();
            }

            $total_efectivo += $qty * $prod['price_efectivo'];
            $total_euro += $qty * $prod['price_euro'];
            $total_bs += $qty * $prod['price_bcv'];

            // Track product IDs that are kept
            $key = array_search($pid, $keep_ids);
            if ($key !== false) unset($keep_ids[$key]);
        }

        // Update totals
        $db->query("UPDATE sales SET total_efectivo=$total_efectivo, total_euro=$total_euro, total_bs=$total_bs WHERE id=$id");

        $db->commit();
        $_SESSION['success'] = "Venta #$id actualizada";
        redirect('/modules/sales/history.php');
    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
    }
}

$clients = $db->query("SELECT * FROM clients WHERE 1=1 ORDER BY name ASC");
$products = $db->query("SELECT * FROM products WHERE 1=1 AND stock > 0 ORDER BY name ASC");
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
                            <?php while($c = $clients->fetch_assoc()): ?>
                            <option value="<?= $c['id'] ?>" <?= $c['id']==$sale['client_id']?'selected':'' ?>><?= h($c['name']) ?> - <?= h($c['cedula_rif']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Tipo Venta <span class="text-danger">*</span></label>
                        <select name="sale_type" id="sale_type" class="form-select" required onchange="toggleEdit()">
                            <option value="contado" <?= $sale['sale_type']=='contado'?'selected':'' ?>>Contado</option>
                            <option value="credito" <?= $sale['sale_type']=='credito'?'selected':'' ?>>Cr&eacute;dito</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Tipo Pago <span class="text-danger">*</span></label>
                        <select name="payment_currency" id="payment_currency" class="form-select" required onchange="toggleEdit()">
                            <option value="EFECTIVO" <?= $sale['payment_currency']=='EFECTIVO'?'selected':'' ?>>Efectivo $</option>
                            <option value="EURO" <?= $sale['payment_currency']=='EURO'?'selected':'' ?>>EURO €</option>
                            <option value="BCV" <?= $sale['payment_currency']=='BCV'?'selected':'' ?>>BCV</option>
                        </select>
                    </div>
                    <div class="col-md-2" id="cuotas_div" style="display:<?= $sale['sale_type']=='credito'?'block':'none' ?>">
                        <label class="form-label">N° Cuotas</label>
                        <input type="number" name="installments" class="form-control" value="<?= $sale['installments'] ?>" min="1">
                    </div>
                    <div class="col-md-2" id="tasa_div" style="display:<?= $sale['payment_currency']=='BCV'?'block':'none' ?>">
                        <label class="form-label">Tasa BCV</label>
                        <input type="number" name="exchange_rate" class="form-control" step="0.01" value="<?= $sale['exchange_rate'] ?: $rate ?>">
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between">
                        <span>Productos</span>
                        <button type="button" class="btn btn-sm btn-success" onclick="addProductRow()"><i class="bi bi-plus"></i> Agregar</button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table mb-0" id="products-table">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Efectivo $</th>
                                        <th>EURO €</th>
                                        <th>Ref</th>
                                        <th>Cantidad</th>
                                        <th>Subtotal $</th>
                                        <th>Subtotal €</th>
                                        <th>Total Ref</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="products-body">
                                    <?php mysqli_data_seek($existing_items, 0); while($item = $existing_items->fetch_assoc()): ?>
                                    <tr class="sale-row">
                                        <td>
                                            <select name="product_id[]" class="form-select product-select">
                                                <option value="<?= $item['product_id'] ?>"><?= h($item['product_name']) ?> (Stock: <?= $item['stock'] ?>)</option>
                                                <?php mysqli_data_seek($products, 0); while($p = $products->fetch_assoc()): ?>
                                                <option value="<?= $p['id'] ?>" <?= $p['id']==$item['product_id']?'selected':'' ?> data-price-efectivo="<?= $p['price_efectivo'] ?>" data-price-euro="<?= $p['price_euro'] ?>" data-price-bs="<?= $p['price_bcv'] ?>" data-stock="<?= $p['stock'] ?>"><?= h($p['name']) ?> (Stock: <?= $p['stock'] ?>)</option>
                                                <?php endwhile; ?>
                                            </select>
                                        </td>
                                        <td><span class="price-efectivo" data-value="<?= $item['unit_price_efectivo'] ?>"><?= number_format($item['unit_price_efectivo'],2) ?></span></td>
                                        <td><span class="price-euro" data-value="<?= $item['unit_price_euro'] ?>"><?= number_format($item['unit_price_euro'],2) ?></span></td>
                                        <td><span class="price-bs" data-value="<?= $item['unit_price_bs'] ?>"><?= number_format($item['unit_price_bs'],2) ?></span></td>
                                        <td><input type="number" name="quantity[]" class="form-control qty" min="1" value="<?= $item['quantity'] ?>" required></td>
                                        <td><span class="subtotal-efectivo"><?= number_format($item['quantity']*$item['unit_price_efectivo'],2) ?></span></td>
                                        <td><span class="subtotal-euro"><?= number_format($item['quantity']*$item['unit_price_euro'],2) ?></span></td>
                                        <td><span class="subtotal-bs"><?= number_format($item['quantity']*$item['unit_price_bs'],2) ?></span></td>
                                        <td><button type="button" class="btn btn-sm btn-danger" onclick="if(confirm('Eliminar este producto? Se restaurar\u00e1 el stock.')) removeProductRow(this)"><i class="bi bi-x"></i></button></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-save"></i> Guardar Cambios</button>
                <a href="detail.php?id=<?= $id ?>" class="btn btn-info btn-lg">Cancelar</a>
            </form>
        </div>
    </div>
</div>
<script>
function toggleEdit() {
    document.getElementById('cuotas_div').style.display = document.getElementById('sale_type').value === 'credito' ? 'block' : 'none';
    document.getElementById('tasa_div').style.display = document.getElementById('payment_currency').value === 'BCV' ? 'block' : 'none';
}

function addProductRow() {
    var tbody = document.getElementById('products-body');
    var first = tbody.querySelector('.sale-row');
    if (!first) return;
    var clone = first.cloneNode(true);
    clone.querySelectorAll('input, select').forEach(function(el) { el.value = ''; });
    clone.querySelector('input.qty').value = '1';
    tbody.appendChild(clone);
    attachEvents(clone);
}

function removeProductRow(btn) {
    btn.closest('tr').remove();
    calcTotals();
}

function attachEvents(row) {
    row.querySelector('.product-select').addEventListener('change', function() {
        var opt = this.options[this.selectedIndex];
        var tr = this.closest('tr');
        if (opt.value) {
            tr.querySelector('.price-efectivo').textContent = parseFloat(opt.dataset.priceEfectivo).toFixed(2);
            tr.querySelector('.price-efectivo').dataset.value = opt.dataset.priceEfectivo;
            tr.querySelector('.price-euro').textContent = parseFloat(opt.dataset.priceEuro).toFixed(2);
            tr.querySelector('.price-euro').dataset.value = opt.dataset.priceEuro;
            tr.querySelector('.price-bs').textContent = parseFloat(opt.dataset.priceBs).toFixed(2);
            tr.querySelector('.price-bs').dataset.value = opt.dataset.priceBs;
        }
        calcTotals();
    });
    row.querySelector('.qty').addEventListener('input', calcTotals);
}
document.querySelectorAll('.sale-row').forEach(attachEvents);
toggleEdit();
</script>
<?php require_once '../../includes/footer.php'; ?>
