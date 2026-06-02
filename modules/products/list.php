<?php
require_once '../../config/database.php';
$page_title = 'Productos';
require_once '../../includes/header.php';

$db = getDB();
$user_id = $_SESSION['user_id'];
$is_admin = isAdmin();
$where = $is_admin ? "1=1" : "user_id = $user_id";

$result = $db->query("SELECT * FROM products WHERE $where ORDER BY name ASC");
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Gesti&oacute;n de Productos</h4>
        <div>
            <button class="btn btn-success" onclick="exportToExcel('products-table', 'productos_<?= date('Ymd') ?>')"><i class="bi bi-file-earmark-excel"></i> Exportar Excel</button>
            <a href="create.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Nuevo Producto</a>
        </div>
    </div>
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="products-table">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Nombre</th>
                            <th>Stock</th>
                            <th>Efectivo (Divisa)</th>
                            <th>EURO</th>
                            <th>BCV</th>
                            <th>Foto</th>
                            <th>Archivo</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows === 0): ?>
                        <tr><td colspan="9" class="text-center py-4 text-muted">No hay productos registrados</td></tr>
                        <?php endif; ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><span class="badge bg-secondary"><?= h($row['type']) ?></span></td>
                            <td><strong><?= h($row['name']) ?></strong></td>
                            <td>
                                <span class="badge bg-<?= $row['stock'] > 10 ? 'success' : ($row['stock'] > 0 ? 'warning' : 'danger') ?>">
                                    <?= $row['stock'] ?>
                                </span>
                            </td>
                            <td>$<?= number_format($row['price_efectivo'], 2) ?></td>
                            <td>€<?= number_format($row['price_euro'], 2) ?></td>
                            <td>Ref. <?= number_format($row['price_bcv'], 2) ?></td>
                            <td><?php if ($row['foto']): ?><a href="<?= BASE_URL ?>/uploads/products/<?= $row['foto'] ?>" target="_blank"><img src="<?= BASE_URL ?>/uploads/products/<?= $row['foto'] ?>" style="width:40px;height:40px;object-fit:cover;border-radius:5px" alt="Foto"></a><?php else: ?><span class="text-muted">-</span><?php endif; ?></td>
                            <td><?php if ($row['archivo']): ?><a href="<?= BASE_URL ?>/uploads/products/<?= $row['archivo'] ?>" target="_blank" class="btn btn-sm btn-outline-info"><i class="bi bi-file-earmark"></i></a><?php else: ?><span class="text-muted">-</span><?php endif; ?></td>
                            <td>
                                <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                                <a href="delete.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirmDelete('¿Eliminar este producto?')"><i class="bi bi-trash"></i></a>
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
