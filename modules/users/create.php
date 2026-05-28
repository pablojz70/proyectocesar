<?php
require_once '../../config/database.php';
if (!isAdmin()) { $_SESSION['error'] = 'Acceso denegado'; redirect('/dashboard.php'); }
$page_title = 'Nuevo Usuario';
require_once '../../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    $username = $db->real_escape_string($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $full_name = $db->real_escape_string($_POST['full_name']);
    $role = $_POST['role'];

    $stmt = $db->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $username, $password, $full_name, $role);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Usuario creado';
        redirect('/modules/users/list.php');
    } else {
        $_SESSION['error'] = 'Error: ' . $db->error;
    }
}
?>
<div class="container-fluid">
    <h4 class="mb-3">Nuevo Usuario</h4>
    <div class="card">
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Usuario <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nombre Completo <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Contraseña <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Rol <span class="text-danger">*</span></label>
                        <select name="role" class="form-select">
                            <option value="seller">Vendedor</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Guardar</button>
                <a href="list.php" class="btn btn-secondary">Cancelar</a>
            </form>
        </div>
    </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
