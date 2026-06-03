<?php require_once 'config/database.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Ventas y Créditos - Iniciar Sesión</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; }
        .card { border: none; border-radius: 15px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        .card-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 15px 15px 0 0 !important; padding: 20px; text-align: center; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; }
        .btn-primary:hover { background: linear-gradient(135deg, #5a6fd6 0%, #6a4192 100%); }
        .logo-icon { font-size: 3rem; }
        .logo-img { height: 80px; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                    <div class="card">
                    <div class="card-header">
                        <img src="<?= BASE_URL ?>/imagen/cesar.png" class="logo-img" alt="Cesar">
                        <h3 class="mb-0">Sistema de Ventas y Cr&eacute;ditos</h3>
                        <small>Inicia sesión para continuar</small>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'):
                            $username = $_POST['username'] ?? '';
                            $password = $_POST['password'] ?? '';
                            $db = getDB();
                            $stmt = $db->prepare("SELECT id, username, password, full_name, role, active FROM users WHERE username = ? AND active = 1");
                            $stmt->bind_param("s", $username);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            if ($user = $result->fetch_assoc()) {
                                if (password_verify($password, $user['password'])) {
                                    $_SESSION['user_id'] = $user['id'];
                                    $_SESSION['username'] = $user['username'];
                                    $_SESSION['full_name'] = $user['full_name'];
                                    $_SESSION['user_role'] = $user['role'];
                                    redirect('/dashboard.php');
                                }
                            }
                            echo '<div class="alert alert-danger">Usuario o contraseña incorrectos</div>';
                        endif; ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Usuario</label>
                                <input type="text" name="username" class="form-control form-control-lg" required autofocus>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Contraseña</label>
                                <input type="password" name="password" class="form-control form-control-lg" required>
                            </div>
                            <button type="submit" class="btn btn-primary btn-lg w-100">Ingresar</button>
                        </form>
                        <div class="text-center mt-3">
                            <small class="text-muted">Usuarios por defecto: admin / vendedor1 - Contraseña: password</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
