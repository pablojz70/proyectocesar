<?php
require_once '../../config/database.php';
$page_title = 'Registrar Venta';
require_once '../../includes/header.php';

$db = getDB();
$user_id = $_SESSION['user_id'];
$is_admin = isAdmin();
$where = $is_admin ? "1=1" : "user_id = $user_id";

$products = $db->query("SELECT * FROM products WHERE $where AND stock > 0 ORDER BY name ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = intval($_POST['client_id']);
    $sale_type = $_POST['sale_type'];
    $payment_currency = $_POST['payment_currency'];
    $installments = intval($_POST['installments'] ?? 1);
    $exchange_rate = floatval($_POST['exchange_rate'] ?? 0);

    if ($payment_currency === 'BCV' && $exchange_rate <= 0) {
        $_SESSION['error'] = 'Debe ingresar la tasa de cambio para pago en BCV';
        redirect('/modules/sales/register.php');
    }

    $total_efectivo = 0;
    $total_euro = 0;
    $total_bs = 0;
    $items = [];

    $db->begin_transaction();
    try {
        for ($i = 0; $i < count($product_ids); $i++) {
            $pid = intval($product_ids[$i]);
            $qty = intval($quantities[$i]);
            if ($qty <= 0) continue;

            $prod = $db->query("SELECT * FROM products WHERE id = $pid AND $where")->fetch_assoc();
            if (!$prod) throw new Exception("Producto no encontrado");
            if ($prod['stock'] < $qty) throw new Exception("Stock insuficiente para: " . $prod['name']);

            $items[] = [
                'product_id' => $pid,
                'quantity' => $qty,
                'unit_price_efectivo' => $prod['price_efectivo'],
                'unit_price_euro' => $prod['price_euro'],
                'unit_price_bs' => $prod['price_bcv']
            ];
            $total_efectivo += $qty * $prod['price_efectivo'];
            $total_euro += $qty * $prod['price_euro'];
            $total_bs += $qty * $prod['price_bcv'];
        }

        if (empty($items)) throw new Exception("Debe agregar al menos un producto");

        $stmt = $db->prepare("INSERT INTO sales (user_id, client_id, sale_type, payment_currency, exchange_rate, total_efectivo, total_euro, total_bs, status, installments) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $status = $sale_type === 'contado' ? 'pagada' : 'pendiente';
        $stmt->bind_param("iissddddds", $user_id, $client_id, $sale_type, $payment_currency, $exchange_rate, $total_efectivo, $total_euro, $total_bs, $status, $installments);
        $stmt->execute();
        $sale_id = $db->insert_id;

        foreach ($items as $item) {
            $stmt2 = $db->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price_efectivo, unit_price_euro, unit_price_bs) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt2->bind_param("iiiddd", $sale_id, $item['product_id'], $item['quantity'], $item['unit_price_efectivo'], $item['unit_price_euro'], $item['unit_price_bs']);
            $stmt2->execute();
            $db->query("UPDATE products SET stock = stock - {$item['quantity']} WHERE id = {$item['product_id']}");
        }

        if ($sale_type === 'contado') {
            $stmt3 = $db->prepare("INSERT INTO payments (sale_id, amount_efectivo, amount_euro, amount_bs, exchange_rate) VALUES (?, ?, ?, ?, ?)");
            $stmt3->bind_param("idddd", $sale_id, $total_efectivo, $total_euro, $total_bs, $exchange_rate);
            $stmt3->execute();
        }

        $db->commit();
        $_SESSION['success'] = "Venta #$sale_id registrada exitosamente";
        redirect('/modules/sales/history.php');
    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
    }
}

$clients = $db->query("SELECT * FROM clients WHERE $where ORDER BY name ASC");
$rate = getExchangeRate();
?>
<div class="container-fluid">
    <h4 class="mb-3">Registrar Venta</h4>
    <div class="card">
        <div class="card-body">
            <form method="POST" id="saleForm">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Cliente <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <select name="client_id" class="form-select" required>
                                <option value="">Seleccionar cliente...</option>
                                <?php while($c = $clients->fetch_assoc()): ?>
                                <option value="<?= $c['id'] ?>"><?= h($c['name']) ?> - <?= h($c['cedula_rif']) ?></option>
                                <?php endwhile; ?>
                            </select>
                            <a href="../clients/create.php" class="btn btn-outline-primary" target="_blank">+ Nuevo</a>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tipo de Venta <span class="text-danger">*</span></label>
                        <select name="sale_type" id="sale_type" class="form-select" required onchange="toggleFields()">
                            <option value="contado">Contado</option>
                            <option value="credito">Cr&eacute;dito</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tipo de Pago <span class="text-danger">*</span></label>
                        <select name="payment_currency" id="payment_currency" class="form-select" required onchange="toggleFields()">
                            <option value="EFECTIVO">Efectivo (Divisa) $</option>
                            <option value="EURO">EURO €</option>
                            <option value="BCV">BCV (Bol&iacute;vares)</option>
                        </select>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4" id="exchange_rate_div" style="display:none">
                        <label class="form-label">Tasa de Cambio (Bs/Divisa)</label>
                        <div class="input-group">
                            <input type="number" name="exchange_rate" id="exchange_rate" class="form-control" step="0.01" min="0" value="<?= $rate ?>">
                            <button type="button" class="btn btn-outline-secondary" onclick="fetchTasa()">Auto</button>
                        </div>
                        <small class="text-muted" id="tasa_info"><?= $rate ? "Tasa actual: Bs. ".number_format($rate,2) : "Ingrese tasa manual" ?></small>
                    </div>
                    <div class="col-md-2" id="installments_div" style="display:none">
                        <label class="form-label">N&deg; Cuotas</label>
                        <input type="number" name="installments" class="form-control" value="1" min="1">
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
                                        <th>Bs</th>
                                        <th>Cantidad</th>
                                        <th>Subtotal $</th>
                                        <th>Subtotal €</th>
                                        <th>Subtotal Bs</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="products-body">
                                    <tr class="sale-row">
                                        <td>
                                            <select name="product_id[]" class="form-select product-select" required>
                                                <option value="">Seleccionar...</option>
                                                <?php mysqli_data_seek($products, 0); while($p = $products->fetch_assoc()): ?>
                                                <option value="<?= $p['id'] ?>" data-price-efectivo="<?= $p['price_efectivo'] ?>" data-price-euro="<?= $p['price_euro'] ?>" data-price-bs="<?= $p['price_bcv'] ?>" data-stock="<?= $p['stock'] ?>">
                                                    <?= h($p['name']) ?> (Stock: <?= $p['stock'] ?>)
                                                </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </td>
                                        <td><span class="price-efectivo" data-value="0">0.00</span></td>
                                        <td><span class="price-euro" data-value="0">0.00</span></td>
                                        <td><span class="price-bs" data-value="0">0.00</span></td>
                                        <td><input type="number" name="quantity[]" class="form-control qty" min="1" value="1" required></td>
                                        <td><span class="subtotal-efectivo">0.00</span></td>
                                        <td><span class="subtotal-euro">0.00</span></td>
                                        <td><span class="subtotal-bs">0.00</span></td>
                                        <td><button type="button" class="btn btn-sm btn-danger" onclick="removeProductRow(this)"><i class="bi bi-x"></i></button></td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="fw-bold">
                                        <td colspan="4" class="text-end">Totales:</td>
                                        <td id="total-efectivo">0.00</td>
                                        <td id="total-euro">0.00</td>
                                        <td id="total-bs">0.00</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-circle"></i> Confirmar Venta</button>
                <a href="history.php" class="btn btn-secondary btn-lg">Cancelar</a>
            </form>
        </div>
    </div>
</div>
<script>
let productCache = [];
document.querySelectorAll('.product-select option').forEach(o => {
    if (o.value) {
        productCache[o.value] = { price_efectivo: o.dataset.priceEfectivo, price_euro: o.dataset.priceEuro, price_bs: o.dataset.priceBs, stock: o.dataset.stock };
    }
});

function addProductRow() {
    const tbody = document.getElementById('products-body');
    const first = tbody.querySelector('.sale-row');
    const clone = first.cloneNode(true);
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
    const rows = document.querySelectorAll('.sale-row');
    if (rows.length > 1) {
        btn.closest('tr').remove();
        calcTotals();
    }
}

function attachEvents(row) {
    row.querySelector('.product-select').addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        const td = this.closest('tr');
        if (opt.value) {
            td.querySelector('.price-efectivo').textContent = parseFloat(opt.dataset.priceEfectivo).toFixed(2);
            td.querySelector('.price-efectivo').dataset.value = opt.dataset.priceEfectivo;
            td.querySelector('.price-euro').textContent = parseFloat(opt.dataset.priceEuro).toFixed(2);
            td.querySelector('.price-euro').dataset.value = opt.dataset.priceEuro;
            td.querySelector('.price-bs').textContent = parseFloat(opt.dataset.priceBs).toFixed(2);
            td.querySelector('.price-bs').dataset.value = opt.dataset.priceBs;
            td.querySelector('input.qty').max = opt.dataset.stock;
        } else {
            td.querySelector('.price-efectivo').textContent = '0.00';
            td.querySelector('.price-efectivo').dataset.value = '0';
            td.querySelector('.price-euro').textContent = '0.00';
            td.querySelector('.price-euro').dataset.value = '0';
            td.querySelector('.price-bs').textContent = '0.00';
            td.querySelector('.price-bs').dataset.value = '0';
        }
        calcTotals();
    });
    row.querySelector('.qty').addEventListener('input', calcTotals);
}
attachEvents(document.querySelector('.sale-row'));

function toggleFields() {
    var idiv = document.getElementById('installments_div');
    var ediv = document.getElementById('exchange_rate_div');
    if (idiv) idiv.style.display = document.getElementById('sale_type').value === 'credito' ? 'block' : 'none';
    if (ediv) ediv.style.display = document.getElementById('payment_currency').value === 'BCV' ? 'block' : 'none';
}
toggleFields();

function fetchTasa() {
    fetch('<?= BASE_URL ?>/api/get_exchange_rate.php')
        .then(r => r.json())
        .then(d => {
            if (d.rate) {
                document.getElementById('exchange_rate').value = d.rate;
                document.getElementById('tasa_info').textContent = 'Tasa actualizada: Bs. ' + d.rate.toFixed(2);
            }
        });
}
</script>
<?php require_once '../../includes/footer.php'; ?>
