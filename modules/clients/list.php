<?php
require_once '../../config/database.php';
$page_title = 'Clientes';
require_once '../../includes/header.php';

$db = getDB();
$user_id = $_SESSION['user_id'];
$search = $_GET['search'] ?? '';
$is_admin = isAdmin();

$where = "1=1";
if ($search) {
    $s = $db->real_escape_string($search);
    $where .= " AND (name LIKE '%$s%' OR cedula_rif LIKE '%$s%')";
}
$result = $db->query("SELECT * FROM clients WHERE $where ORDER BY name ASC");
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Gestión de Clientes</h4>
        <a href="create.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Nuevo Cliente</a>
    </div>
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-md-8">
                    <input type="text" name="search" class="form-control search-box" placeholder="Buscar por nombre o cédula..." value="<?= h($search) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> Buscar</button>
                </div>
                <div class="col-md-2">
                    <a href="list.php" class="btn btn-outline-secondary w-100">Limpiar</a>
                </div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Cédula/RIF</th>
                            <th>Teléfono</th>
                            <th>Observaciones</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows === 0): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No hay clientes registrados</td></tr>
                        <?php endif; ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?= h($row['name']) ?></strong></td>
                            <td><?= h($row['cedula_rif']) ?></td>
                            <td><?= h($row['phone']) ?></td>
                            <td><small class="text-muted"><?= h(substr($row['observations'] ?? '', 0, 50)) ?></small></td>
                            <td>
                                <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning" title="Editar"><i class="bi bi-pencil"></i></a>
                                <a href="delete.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirmDelete('¿Eliminar este cliente?')" title="Eliminar"><i class="bi bi-trash"></i></a>
                                <a href="../sales/history.php?client_id=<?= $row['id'] ?>" class="btn btn-sm btn-info" title="Ventas"><i class="bi bi-receipt"></i></a>
                                <a href="../loans/history.php?client_id=<?= $row['id'] ?>" class="btn btn-sm btn-secondary" title="Préstamos"><i class="bi bi-bank"></i></a>
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
