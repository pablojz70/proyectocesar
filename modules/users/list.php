<?php
require_once '../../config/database.php';
if (!isAdmin()) { $_SESSION['error'] = 'Acceso denegado'; redirect('/dashboard.php'); }
$page_title = 'Usuarios';
require_once '../../includes/header.php';

$db = getDB();
$result = $db->query("SELECT * FROM users ORDER BY role, full_name");
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Gestión de Usuarios</h4>
        <a href="create.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Nuevo Usuario</a>
    </div>
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Nombre Completo</th>
                            <th>Rol</th>
                            <th>Activo</th>
                            <th>Registro</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($u = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= h($u['username']) ?></td>
                            <td><?= h($u['full_name']) ?></td>
                            <td><span class="badge bg-<?= $u['role']=='admin'?'danger':'primary' ?>"><?= $u['role'] ?></span></td>
                            <td><span class="badge bg-<?= $u['active']?'success':'secondary' ?>"><?= $u['active']?'Sí':'No' ?></span></td>
                            <td><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                            <td>
                                <a href="edit.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                <a href="delete.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirmDelete('¿Eliminar este usuario?')"><i class="bi bi-trash"></i></a>
                                <?php endif; ?>
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
