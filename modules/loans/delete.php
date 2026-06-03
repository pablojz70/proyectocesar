<?php
require_once '../../config/database.php';
$db = getDB();
$id = intval($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];
$is_admin = isAdmin();
$where = "1=1";

$loan = $db->query("SELECT * FROM loans WHERE id=$id AND $where")->fetch_assoc();
if (!$loan) { $_SESSION['error'] = 'Préstamo no encontrado'; redirect('/modules/loans/history.php'); }

$db->query("DELETE FROM loan_installments WHERE loan_id=$id");
$db->query("DELETE FROM loans WHERE id=$id");
$_SESSION['success'] = 'Préstamo #' . $id . ' eliminado';
redirect('/modules/loans/history.php');
