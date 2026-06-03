<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($page_title ?? 'Sistema de Ventas y Créditos') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</head>
<body>
<?php if (!isset($_SESSION['user_id'])) redirect('/login.php'); ?>

<!-- Mobile top bar -->
<div class="mobile-top-bar d-md-none">
    <button class="btn" onclick="toggleSidebar()"><i class="bi bi-list fs-4"></i></button>
    <span class="fw-bold"><?= h($page_title ?? 'Sistema') ?></span>
    <div class="dropdown">
        <a class="text-dark" href="#" data-bs-toggle="dropdown"><i class="bi bi-person-circle fs-5"></i></a>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><span class="dropdown-item-text text-muted"><?= h($_SESSION['full_name']) ?> (<?= h($_SESSION['user_role']) ?>)</span></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?= BASE_URL ?>/logout.php"><i class="bi bi-box-arrow-right"></i> Cerrar Sesi&oacute;n</a></li>
        </ul>
    </div>
</div>

<!-- Sidebar overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<div class="d-flex" id="wrapper">
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header d-none d-md-block">
            <a href="<?= BASE_URL ?>/dashboard.php"><img src="<?= BASE_URL ?>/imagen/cesar.png" style="height:40px;vertical-align:middle" alt="Cesar"> Sistema</a>
        </div>
        <div class="sidebar-header d-md-none">
            <a href="<?= BASE_URL ?>/dashboard.php">💰 Cesar</a>
            <button class="btn btn-sm btn-outline-dark float-end" onclick="toggleSidebar()"><i class="bi bi-x-lg"></i></button>
        </div>
        <ul class="nav flex-column sidebar-nav">
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/dashboard.php" onclick="closeSidebar()"><img src="<?= BASE_URL ?>/imagen/1inicio.png" class="sidebar-icon" alt=""> Inicio</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/modules/clients/list.php" onclick="closeSidebar()"><img src="<?= BASE_URL ?>/imagen/2clientes.png" class="sidebar-icon" alt=""> Clientes</a></li>
            <?php if (isAdmin()): ?>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/modules/products/list.php" onclick="closeSidebar()"><img src="<?= BASE_URL ?>/imagen/3productos.png" class="sidebar-icon" alt=""> Productos</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/modules/loans/register.php" onclick="closeSidebar()"><img src="<?= BASE_URL ?>/imagen/7prestamos.png" class="sidebar-icon" alt=""> Pr&eacute;stamos</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/modules/loans/history.php" onclick="closeSidebar()"><img src="<?= BASE_URL ?>/imagen/8historial.png" class="sidebar-icon" alt=""> Hist. Pr&eacute;stamos</a></li>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><img src="<?= BASE_URL ?>/imagen/9reportes.png" class="sidebar-icon" alt=""> Reportes</a>
                <ul class="dropdown-menu dropdown-menu-dark">
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/modules/reports/sales.php" onclick="closeSidebar()">Ventas</a></li>
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/modules/reports/profitability.php" onclick="closeSidebar()">Rentabilidad</a></li>
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/modules/reports/inventory.php" onclick="closeSidebar()">Inventario</a></li>
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/modules/reports/collections.php" onclick="closeSidebar()">Cobranzas</a></li>
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/modules/loans/report.php" onclick="closeSidebar()">Pr&eacute;stamos</a></li>
                </ul>
            </li>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/modules/users/list.php" onclick="closeSidebar()"><img src="<?= BASE_URL ?>/imagen/10usuarios.png" class="sidebar-icon" alt=""> Usuarios</a></li>
            <?php endif; ?>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/modules/sales/register.php" onclick="closeSidebar()"><img src="<?= BASE_URL ?>/imagen/4vender.png" class="sidebar-icon" alt=""> Vender</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/modules/sales/history.php" onclick="closeSidebar()"><img src="<?= BASE_URL ?>/imagen/5ventas.png" class="sidebar-icon" alt=""> Hist. Ventas</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/modules/payments/consult_debt.php" onclick="closeSidebar()"><img src="<?= BASE_URL ?>/imagen/6cobranza.png" class="sidebar-icon" alt=""> Cobranzas</a></li>
            <li class="nav-item d-none d-md-block"><a class="nav-link text-danger" href="<?= BASE_URL ?>/logout.php" onclick="closeSidebar()"><i class="bi bi-box-arrow-right"></i> Cerrar Sesi&oacute;n</a></li>
            <li class="nav-item d-md-none"><a class="nav-link text-danger" href="<?= BASE_URL ?>/logout.php" onclick="closeSidebar()"><i class="bi bi-box-arrow-right"></i> Cerrar Sesi&oacute;n</a></li>
        </ul>
    </div>
    <div id="page-content-wrapper">
        <div class="container-fluid mt-3">
<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show"><?= h($_SESSION['success']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?= h($_SESSION['error']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>
