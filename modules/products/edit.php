<?php
require_once '../../config/database.php';

$db = getDB();
$id = intval($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];
$is_admin = isAdmin();
$where = $is_admin ? "1=1" : "user_id = $user_id";

$product = $db->query("SELECT * FROM products WHERE id = $id AND $where")->fetch_assoc();
if (!$product) { $_SESSION['error'] = 'Producto no encontrado'; redirect('/modules/products/list.php'); }

$upload_dir = $_SERVER['DOCUMENT_ROOT'] . BASE_URL . '/uploads/products/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $db->real_escape_string($_POST['type']);
    $name = $db->real_escape_string($_POST['name']);
    $description = $db->real_escape_string($_POST['description'] ?? '');
    $stock = intval($_POST['stock']);
    $price_efectivo = floatval($_POST['price_efectivo']);
    $price_euro = floatval($_POST['price_euro']);
    $price_bcv = floatval($_POST['price_bcv']);
    $foto = $product['foto'];
    $archivo = $product['archivo'];

    if ($_FILES['foto']['name']) {
        if ($foto && file_exists($upload_dir . $foto)) unlink($upload_dir . $foto);
        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $foto = uniqid('foto_') . '.' . $ext;
        move_uploaded_file($_FILES['foto']['tmp_name'], $upload_dir . $foto);
    }
    if ($_FILES['archivo']['name']) {
        if ($archivo && file_exists($upload_dir . $archivo)) unlink($upload_dir . $archivo);
        $ext = pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION);
        $archivo = uniqid('arc_') . '.' . $ext;
        move_uploaded_file($_FILES['archivo']['tmp_name'], $upload_dir . $archivo);
    }

    $stmt = $db->prepare("UPDATE products SET type=?, name=?, description=?, stock=?, price_efectivo=?, price_euro=?, price_bcv=?, foto=?, archivo=? WHERE id=? AND $where");
    $stmt->bind_param("sssidddssi", $type, $name, $description, $stock, $price_efectivo, $price_euro, $price_bcv, $foto, $archivo, $id);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Producto actualizado';
        redirect('/modules/products/list.php');
    } else {
        $_SESSION['error'] = 'Error: ' . $db->error;
    }
}

$page_title = 'Editar Producto';
require_once '../../includes/header.php';
$foto_url = $product['foto'] ? BASE_URL . '/uploads/products/' . $product['foto'] : '';
$archivo_url = $product['archivo'] ? BASE_URL . '/uploads/products/' . $product['archivo'] : '';
?>
<div class="container-fluid">
    <h4 class="mb-3">Editar Producto</h4>
    <div class="card">
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Tipo <span class="text-danger">*</span></label>
                        <input type="text" name="type" class="form-control" value="<?= h($product['type']) ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?= h($product['name']) ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Stock <span class="text-danger">*</span></label>
                        <input type="number" name="stock" class="form-control" min="0" value="<?= $product['stock'] ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Precio Efectivo (Divisa) $ <span class="text-danger">*</span></label>
                        <input type="number" name="price_efectivo" class="form-control" step="0.01" min="0" value="<?= $product['price_efectivo'] ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Precio EURO € <span class="text-danger">*</span></label>
                        <input type="number" name="price_euro" class="form-control" step="0.01" min="0" value="<?= $product['price_euro'] ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Precio Bs <span class="text-danger">*</span></label>
                        <input type="number" name="price_bcv" class="form-control" step="0.01" min="0" value="<?= $product['price_bcv'] ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Foto del Producto</label>
                        <input type="file" name="foto" class="form-control" accept="image/*">
                        <?php if ($foto_url): ?>
                        <div class="mt-2"><img src="<?= $foto_url ?>" class="img-thumbnail" style="max-height:100px" alt="Foto"></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Archivo / Documento</label>
                        <input type="file" name="archivo" class="form-control">
                        <?php if ($archivo_url): ?>
                        <div class="mt-2"><a href="<?= $archivo_url ?>" target="_blank" class="btn btn-sm btn-outline-info"><i class="bi bi-file-earmark"></i> Ver archivo actual</a></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea name="description" class="form-control" rows="3"><?= h($product['description']) ?></textarea>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Actualizar</button>
                    <a href="list.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
