<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($page_title ?? 'Sistema de Ventas y Créditos') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<?php if (!isset($_SESSION['user_id'])) redirect('/login.php'); ?>
<div class="d-flex" id="wrapper">
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="<?= BASE_URL ?>/dashboard.php">💰 Ventas y Créditos</a>
        </div>
        <ul class="nav flex-column sidebar-nav">
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/dashboard.php"><img src="<?= BASE_URL ?>/imagen/1inicio.png" class="sidebar-icon" alt=""> Inicio</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/modules/clients/list.php"><img src="<?= BASE_URL ?>/imagen/2clientes.png" class="sidebar-icon" alt=""> Clientes</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/modules/products/list.php"><img src="<?= BASE_URL ?>/imagen/3productos.png" class="sidebar-icon" alt=""> Productos</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/modules/sales/register.php"><img src="<?= BASE_URL ?>/imagen/4vender.png" class="sidebar-icon" alt=""> Vender Productos</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/modules/sales/history.php"><img src="<?= BASE_URL ?>/imagen/5ventas.png" class="sidebar-icon" alt=""> Ventas</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/modules/payments/consult_debt.php"><img src="<?= BASE_URL ?>/imagen/6cobranza.png" class="sidebar-icon" alt=""> Cobranzas</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/modules/loans/register.php"><img src="<?= BASE_URL ?>/imagen/7prestamos.png" class="sidebar-icon" alt=""> Pr&eacute;stamos</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/modules/loans/history.php"><img src="<?= BASE_URL ?>/imagen/8historial.png" class="sidebar-icon" alt=""> Hist. Pr&eacute;stamos</a></li>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><img src="<?= BASE_URL ?>/imagen/9reportes.png" class="sidebar-icon" alt=""> Reportes</a>
                <ul class="dropdown-menu dropdown-menu-dark">
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/modules/reports/sales.php">Ventas</a></li>
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/modules/reports/profitability.php">Rentabilidad</a></li>
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/modules/reports/inventory.php">Inventario</a></li>
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/modules/reports/collections.php">Cobranzas</a></li>
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/modules/loans/report.php">Pr&eacute;stamos</a></li>
                </ul>
            </li>
            <?php if (isAdmin()): ?>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/modules/users/list.php"><img src="<?= BASE_URL ?>/imagen/10usuarios.png" class="sidebar-icon" alt=""> Usuarios</a></li>
            <?php endif; ?>
        </ul>
        <div class="sidebar-footer">
            <div class="dropdown">
                <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle"></i> <?= h($_SESSION['full_name']) ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-dark">
                    <li><span class="dropdown-item-text text-muted"><?= h($_SESSION['user_role']) ?></span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/logout.php"><i class="bi bi-box-arrow-right"></i> Cerrar Sesión</a></li>
                </ul>
            </div>
        </div>
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
