<?php
require_once '../../config/database.php';
$db = getDB();
$id = intval($_GET['id'] ?? 0);
$loan_id = intval($_GET['loan_id'] ?? 0);

$inst = $db->query("SELECT * FROM loan_installments WHERE id=$id AND loan_id=$loan_id")->fetch_assoc();
if (!$inst) { $_SESSION['error'] = 'Cuota no encontrada'; redirect('/modules/loans/collect.php?loan_id=' . $loan_id); }

$db->begin_transaction();
try {
    // Delete the installment
    $db->query("DELETE FROM loan_installments WHERE id=$id");
    
    // Set loan status back to activo if it was pagado
    $db->query("UPDATE loans SET status='activo' WHERE id=$loan_id AND status='pagado'");
    
    $db->commit();
    $_SESSION['success'] = 'Cuota eliminada. Pago revertido.';
} catch (Exception $e) {
    $db->rollback();
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
}

redirect('/modules/loans/collect.php?loan_id=' . $loan_id);
