<?php
require_once '../../config/database.php';
$page_title = 'Nuevo Cliente';
require_once '../../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    $name = $db->real_escape_string($_POST['name']);
    $cedula = $db->real_escape_string($_POST['cedula_rif']);
    $phone = $db->real_escape_string($_POST['phone'] ?? '');
    $observations = $db->real_escape_string($_POST['observations'] ?? '');
    $user_id = $_SESSION['user_id'];

    $check = $db->query("SELECT id FROM clients WHERE user_id = $user_id AND cedula_rif = '$cedula'");
    if ($check->num_rows > 0) {
        $_SESSION['error'] = 'Ya existe un cliente con esa cédula/RIF';
    } else {
        $stmt = $db->prepare("INSERT INTO clients (user_id, name, cedula_rif, phone, observations) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $user_id, $name, $cedula, $phone, $observations);
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Cliente creado exitosamente';
            redirect('/modules/clients/list.php');
        } else {
            $_SESSION['error'] = 'Error al crear cliente: ' . $db->error;
        }
    }
}
?>
<div class="container-fluid">
    <h4 class="mb-3">Nuevo Cliente</h4>
    <div class="card">
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nombre Completo <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Cédula / RIF <span class="text-danger">*</span></label>
                        <input type="text" name="cedula_rif" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Observaciones</label>
                        <textarea name="observations" class="form-control" rows="3"></textarea>
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
