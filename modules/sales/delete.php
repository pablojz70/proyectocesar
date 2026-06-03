<?php
require_once '../../config/database.php';
$db = getDB();
$id = intval($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];
$is_admin = isAdmin();
$where = "1=1";

$sale = $db->query("SELECT * FROM sales s WHERE s.id=$id AND $where")->fetch_assoc();
if (!$sale) { $_SESSION['error'] = 'Venta no encontrada'; redirect('/modules/sales/history.php'); }

$db->begin_transaction();
try {
    $items = $db->query("SELECT * FROM sale_items WHERE sale_id=$id");
    while ($item = $items->fetch_assoc()) {
        $db->query("UPDATE products SET stock = stock + {$item['quantity']} WHERE id = {$item['product_id']}");
    }
    $db->query("DELETE FROM payments WHERE sale_id=$id");
    $db->query("DELETE FROM sale_items WHERE sale_id=$id");
    $db->query("DELETE FROM sales WHERE id=$id");
    $db->commit();
    $_SESSION['success'] = 'Venta #' . $id . ' eliminada (stock restaurado)';
} catch (Exception $e) {
    $db->rollback();
    $_SESSION['error'] = 'Error al eliminar: ' . $e->getMessage();
}
redirect('/modules/sales/history.php');
