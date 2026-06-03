<?php
require_once '../../config/database.php';
$page_title = 'Editar Cliente';
require_once '../../includes/header.php';

$db = getDB();
$id = intval($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];
$is_admin = isAdmin();
$where = "1=1";

$client = $db->query("SELECT * FROM clients WHERE id = $id AND $where")->fetch_assoc();
if (!$client) { $_SESSION['error'] = 'Cliente no encontrado'; redirect('/modules/clients/list.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $db->real_escape_string($_POST['name']);
    $cedula = $db->real_escape_string($_POST['cedula_rif']);
    $phone = $db->real_escape_string($_POST['phone'] ?? '');
    $observations = $db->real_escape_string($_POST['observations'] ?? '');

    $stmt = $db->prepare("UPDATE clients SET name=?, cedula_rif=?, phone=?, observations=? WHERE id=? AND $where");
    $stmt->bind_param("ssssi", $name, $cedula, $phone, $observations, $id);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Cliente actualizado';
        redirect('/modules/clients/list.php');
    } else {
        $_SESSION['error'] = 'Error: ' . $db->error;
    }
}
?>
<div class="container-fluid">
    <h4 class="mb-3">Editar Cliente</h4>
    <div class="card">
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nombre Completo <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?= h($client['name']) ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Cédula / RIF <span class="text-danger">*</span></label>
                        <input type="text" name="cedula_rif" class="form-control" value="<?= h($client['cedula_rif']) ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="phone" class="form-control" value="<?= h($client['phone']) ?>">
                    </div>
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Observaciones</label>
                        <textarea name="observations" class="form-control" rows="3"><?= h($client['observations']) ?></textarea>
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
