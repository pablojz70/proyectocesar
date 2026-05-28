<?php
require_once '../../config/database.php';
if (!isAdmin()) { $_SESSION['error'] = 'Acceso denegado'; redirect('/dashboard.php'); }
$page_title = 'Editar Usuario';
require_once '../../includes/header.php';

$db = getDB();
$id = intval($_GET['id'] ?? 0);
$user = $db->query("SELECT * FROM users WHERE id=$id")->fetch_assoc();
if (!$user) { $_SESSION['error'] = 'Usuario no encontrado'; redirect('/modules/users/list.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $db->real_escape_string($_POST['full_name']);
    $role = $_POST['role'];
    $active = intval($_POST['active']);

    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET full_name=?, role=?, active=?, password=? WHERE id=?");
        $stmt->bind_param("ssisi", $full_name, $role, $active, $password, $id);
    } else {
        $stmt = $db->prepare("UPDATE users SET full_name=?, role=?, active=? WHERE id=?");
        $stmt->bind_param("ssii", $full_name, $role, $active, $id);
    }
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Usuario actualizado';
        redirect('/modules/users/list.php');
    } else {
        $_SESSION['error'] = 'Error: ' . $db->error;
    }
}
?>
<div class="container-fluid">
    <h4 class="mb-3">Editar Usuario: <?= h($user['username']) ?></h4>
    <div class="card">
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nombre Completo</label>
                        <input type="text" name="full_name" class="form-control" value="<?= h($user['full_name']) ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nueva Contraseña (dejar vacío para mantener)</label>
                        <input type="password" name="password" class="form-control">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Rol</label>
                        <select name="role" class="form-select">
                            <option value="seller" <?= $user['role']=='seller'?'selected':'' ?>>Vendedor</option>
                            <option value="admin" <?= $user['role']=='admin'?'selected':'' ?>>Administrador</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Activo</label>
                        <select name="active" class="form-select">
                            <option value="1" <?= $user['active']?'selected':'' ?>>Sí</option>
                            <option value="0" <?= !$user['active']?'selected':'' ?>>No</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Actualizar</button>
                <a href="list.php" class="btn btn-secondary">Cancelar</a>
            </form>
        </div>
    </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
