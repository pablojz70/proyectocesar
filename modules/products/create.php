<?php
require_once '../../config/database.php';
$page_title = 'Nuevo Producto';
require_once '../../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    $type = $db->real_escape_string($_POST['type']);
    $name = $db->real_escape_string($_POST['name']);
    $description = $db->real_escape_string($_POST['description'] ?? '');
    $stock = intval($_POST['stock']);
    $price_efectivo = floatval($_POST['price_efectivo']);
    $price_euro = floatval($_POST['price_euro']);
    $price_bcv = floatval($_POST['price_bcv']);
    $user_id = $_SESSION['user_id'];

    $stmt = $db->prepare("INSERT INTO products (user_id, type, name, description, stock, price_efectivo, price_euro, price_bcv) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssiddd", $user_id, $type, $name, $description, $stock, $price_efectivo, $price_euro, $price_bcv);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Producto creado exitosamente';
        redirect('/modules/products/list.php');
    } else {
        $_SESSION['error'] = 'Error: ' . $db->error;
    }
}
?>
<div class="container-fluid">
    <h4 class="mb-3">Nuevo Producto</h4>
    <div class="card">
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Tipo <span class="text-danger">*</span></label>
                        <input type="text" name="type" class="form-control" placeholder="Ej: Electrónico, Ropa, Calzado" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Stock <span class="text-danger">*</span></label>
                        <input type="number" name="stock" class="form-control" min="0" value="0" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Precio Efectivo (Divisa) $ <span class="text-danger">*</span></label>
                        <input type="number" name="price_efectivo" class="form-control" step="0.01" min="0" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Precio EURO € <span class="text-danger">*</span></label>
                        <input type="number" name="price_euro" class="form-control" step="0.01" min="0" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Precio Bs <span class="text-danger">*</span></label>
                        <input type="number" name="price_bcv" class="form-control" step="0.01" min="0" required>
                    </div>
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Guardar</button>
                    <a href="list.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
