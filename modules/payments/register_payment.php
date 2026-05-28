<?php
require_once '../../config/database.php';
$page_title = 'Registrar Pago';
require_once '../../includes/header.php';

$db = getDB();
$user_id = $_SESSION['user_id'];
$is_admin = isAdmin();

$sale_id = intval($_GET['sale_id'] ?? 0);
$client_id = intval($_GET['client_id'] ?? 0);

$where_sales = $is_admin ? "1=1" : "s.user_id = $user_id";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sale_id_pay = intval($_POST['sale_id']);
    $amount_efectivo = floatval($_POST['amount_efectivo'] ?? 0);
    $amount_euro = floatval($_POST['amount_euro'] ?? 0);
    $amount_bs = floatval($_POST['amount_bs'] ?? 0);
    $exchange_rate = floatval($_POST['exchange_rate'] ?? 0);

    if ($amount_efectivo <= 0 && $amount_euro <= 0 && $amount_bs <= 0) {
        $_SESSION['error'] = 'Debe ingresar un monto v&aacute;lido';
        redirect("/modules/payments/register_payment.php?sale_id=$sale_id_pay&client_id=$client_id");
    }

    $sale = $db->query("SELECT * FROM sales s WHERE s.id=$sale_id_pay AND $where_sales")->fetch_assoc();
    if (!$sale) { $_SESSION['error'] = 'Venta no encontrada'; redirect('/modules/payments/consult_debt.php'); }

    $paid_efectivo = $db->query("SELECT COALESCE(SUM(amount_efectivo),0) as total FROM payments WHERE sale_id=$sale_id_pay")->fetch_assoc()['total'];
    $remaining_efectivo = $sale['total_efectivo'] - $paid_efectivo;

    if ($amount_efectivo > $remaining_efectivo + 0.01) {
        $_SESSION['error'] = "El monto excede el saldo pendiente";
        redirect("/modules/payments/register_payment.php?sale_id=$sale_id_pay&client_id=$client_id");
    }

    $stmt = $db->prepare("INSERT INTO payments (sale_id, amount_efectivo, amount_euro, amount_bs, exchange_rate) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ddddd", $sale_id_pay, $amount_efectivo, $amount_euro, $amount_bs, $exchange_rate);
    if ($stmt->execute()) {
        $new_paid = $paid_efectivo + $amount_efectivo;
        $new_status = abs($new_paid - $sale['total_efectivo']) < 0.01 ? 'pagada' : 'parcial';
        $db->query("UPDATE sales SET status='$new_status' WHERE id=$sale_id_pay");
        $_SESSION['success'] = 'Pago registrado exitosamente';
        redirect('/modules/payments/consult_debt.php?client_id=' . $sale['client_id']);
    } else {
        $_SESSION['error'] = 'Error al registrar pago';
    }
}

$sales_list = [];
if ($client_id > 0) {
    $res = $db->query("SELECT s.*, c.name as client_name FROM sales s JOIN clients c ON c.id=s.client_id WHERE s.client_id=$client_id AND s.sale_type='credito' AND s.status!='pagada' AND $where_sales ORDER BY s.id DESC");
    while($r = $res->fetch_assoc()) {
        $paid = $db->query("SELECT COALESCE(SUM(amount_efectivo),0) as total FROM payments WHERE sale_id={$r['id']}")->fetch_assoc()['total'];
        $r['saldo'] = $r['total_efectivo'] - $paid;
        $sales_list[] = $r;
    }
    $client = $db->query("SELECT * FROM clients WHERE id=$client_id")->fetch_assoc();
}

if ($sale_id > 0) {
    $single = $db->query("SELECT s.*, c.name as client_name, c.id as cid FROM sales s JOIN clients c ON c.id=s.client_id WHERE s.id=$sale_id AND $where_sales")->fetch_assoc();
    if ($single) {
        $client_id = $single['cid'];
        $client = $db->query("SELECT * FROM clients WHERE id=$client_id")->fetch_assoc();
        $paid = $db->query("SELECT COALESCE(SUM(amount_efectivo),0) as total FROM payments WHERE sale_id=$sale_id")->fetch_assoc()['total'];
        $single['saldo'] = $single['total_efectivo'] - $paid;
        $sales_list = [$single];
    }
}
?>
<div class="container-fluid">
    <h4 class="mb-3">Registrar Pago</h4>

    <?php if (!$client_id): ?>
    <div class="card">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-md-8">
                    <select name="client_id" class="form-select" required>
                        <option value="">Seleccionar cliente...</option>
                        <?php $clients = $db->query("SELECT * FROM clients WHERE $where_sales ORDER BY name"); ?>
                        <?php while($c = $clients->fetch_assoc()): ?>
                        <option value="<?= $c['id'] ?>"><?= h($c['name']) ?> - <?= h($c['cedula_rif']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100">Seleccionar</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($client && !empty($sales_list)): ?>
    <div class="card mb-3">
        <div class="card-header">
            Cliente: <strong><?= h($client['name']) ?></strong> - <?= h($client['cedula_rif']) ?>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="client_id" value="<?= $client_id ?>">
                <div class="mb-3">
                    <label class="form-label">Seleccionar Venta</label>
                    <select name="sale_id" class="form-select" required>
                        <option value="">Seleccionar...</option>
                        <?php foreach($sales_list as $sl): ?>
                        <option value="<?= $sl['id'] ?>" <?= $sale_id==$sl['id']?'selected':'' ?>>
                            Venta #<?= $sl['id'] ?> - Saldo: $<?= number_format($sl['saldo'],2) ?> (<?= date('d/m/Y', strtotime($sl['created_at'])) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Efectivo $</label>
                        <input type="number" name="amount_efectivo" class="form-control" step="0.01" min="0" value="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">EURO €</label>
                        <input type="number" name="amount_euro" class="form-control" step="0.01" min="0" value="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Bol&iacute;vares Bs</label>
                        <input type="number" name="amount_bs" class="form-control" step="0.01" min="0" value="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tasa (Bs/Divisa)</label>
                        <input type="number" name="exchange_rate" class="form-control" step="0.01" min="0" value="<?= getExchangeRate() ?>">
                    </div>
                </div>
                <button type="submit" class="btn btn-success"><i class="bi bi-check-circle"></i> Confirmar Pago</button>
                <a href="consult_debt.php?client_id=<?= $client_id ?>" class="btn btn-secondary">Cancelar</a>
            </form>
        </div>
    </div>
    <?php elseif ($client_id > 0): ?>
    <div class="alert alert-info">Este cliente no tiene deudas pendientes</div>
    <?php endif; ?>
</div>
<?php require_once '../../includes/footer.php'; ?>
