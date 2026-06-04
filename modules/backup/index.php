<?php
require_once '../../config/database.php';
if (!isAdmin()) { $_SESSION['error'] = 'Acceso denegado'; redirect('/dashboard.php'); }
$page_title = 'Respaldo';
require_once '../../includes/header.php';

$db = getDB();

// Get database size info
$tables = $db->query("SHOW TABLE STATUS FROM `" . DB_NAME . "`");
$total_size = 0;
$table_count = 0;
while ($t = $tables->fetch_assoc()) {
    $total_size += $t['Data_length'] + $t['Index_length'];
    $table_count++;
}

// Check if backup file exists
$backup_dir = $_SERVER['DOCUMENT_ROOT'] . BASE_URL . '/backups/';
if (!is_dir($backup_dir)) mkdir($backup_dir, 0777, true);
$backup_file = $backup_dir . 'backup_' . date('Y-m-d') . '.sql';
$backup_exists = file_exists($backup_file);
$backup_date = $backup_exists ? date('d/m/Y H:i', filemtime($backup_file)) : 'Nunca';
$backup_size = $backup_exists ? filesize($backup_file) : 0;
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">M&oacute;dulo de Respaldo</h4>
    </div>

    <div class="row mb-3">
        <div class="col-md-4 mb-3">
            <div class="stat-card bg-gradient-primary">
                <h3><?= $table_count ?></h3>
                <small>Tablas en la BD</small>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="stat-card bg-gradient-info">
                <h3><?= number_format($total_size / 1024, 1) ?> KB</h3>
                <small>Tama&ntilde;o de la BD</small>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="stat-card bg-gradient-success">
                <h3><?= $backup_exists ? number_format($backup_size / 1024, 1) . ' KB' : '---' ?></h3>
                <small>Respaldos disponibles</small>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <div class="card">
                <div class="card-header">Generar Respaldo</div>
                <div class="card-body">
                    <p class="text-muted">Descarga un archivo SQL con toda la base de datos (clientes, productos, ventas, pr&eacute;stamos, etc.)</p>
                    <?php if ($backup_exists): ?>
                    <p><small>Último respaldo: <strong><?= $backup_date ?></strong></small></p>
                    <?php endif; ?>
                    <a href="backup_db.php" class="btn btn-primary btn-lg"><i class="bi bi-download"></i> Descargar Respaldo SQL</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card">
                <div class="card-header">Restaurar Respaldo</div>
                <div class="card-body">
                    <p class="text-muted">Selecciona un archivo SQL para restaurar la base de datos. ATENCI&Oacute;N: Sobrescribir&aacute; todos los datos actuales.</p>
                    <form method="POST" enctype="multipart/form-data" action="restore_db.php">
                        <div class="mb-3">
                            <input type="file" name="sql_file" class="form-control" accept=".sql" required>
                        </div>
                        <button type="submit" class="btn btn-danger" onclick="return confirm('¿Estás seguro? Se borrarán TODOS los datos actuales y se restaurarán los del respaldo.')">
                            <i class="bi bi-upload"></i> Restaurar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Tablas de la Base de Datos</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr><th>Tabla</th><th>Registros</th><th>Tama&ntilde;o</th><th>&Uacute;ltima actualizaci&oacute;n</th></tr>
                    </thead>
                    <tbody>
                        <?php
                        $tables->data_seek(0);
                        while($t = $tables->fetch_assoc()):
                        ?>
                        <tr>
                            <td><strong><?= $t['Name'] ?></strong></td>
                            <td><?= number_format($t['Rows']) ?></td>
                            <td><?= number_format(($t['Data_length']+$t['Index_length'])/1024, 1) ?> KB</td>
                            <td><?= $t['Update_time'] ? date('d/m/Y H:i', strtotime($t['Update_time'])) : '-' ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
