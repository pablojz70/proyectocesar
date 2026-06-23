<?php
require_once '../../config/database.php';
$db = getDB();
$id = intval($_GET['id'] ?? 0);
$loan_id = intval($_GET['loan_id'] ?? 0);

$pago = $db->query("SELECT * FROM loan_payments WHERE id=$id AND loan_id=$loan_id")->fetch_assoc();
if (!$pago) { $_SESSION['error'] = 'Pago no encontrado'; redirect("/modules/loans/collect.php?loan_id=$loan_id"); }

$db->begin_transaction();
try {
    $monto = $pago['amount'];
    $loan = $db->query("SELECT * FROM loans WHERE id=$loan_id")->fetch_assoc();
    
    // Restaurar capital si era mensual y se abono a capital
    if ($loan['loan_type'] === 'mensual' && $loan['status'] === 'pagado') {
        $db->query("UPDATE loans SET status='activo', total_amount = total_amount + $monto WHERE id=$loan_id");
    } elseif ($loan['loan_type'] === 'mensual') {
        $db->query("UPDATE loans SET total_amount = total_amount + $monto WHERE id=$loan_id");
    } elseif ($loan['loan_type'] === 'plazo' && $loan['status'] === 'pagado') {
        $db->query("UPDATE loans SET status='activo' WHERE id=$loan_id");
    }
    
    $db->query("DELETE FROM loan_payments WHERE id=$id");
    
    // Recalcular estado de cuotas para plazo
    if ($loan['loan_type'] === 'plazo') {
        $total_pagado = $db->query("SELECT COALESCE(SUM(amount),0) as t FROM loan_payments WHERE loan_id=$loan_id")->fetch_assoc()['t'];
        $db->query("UPDATE loan_installments SET status='pendiente', paid_date=NULL, paid_amount=0 WHERE loan_id=$loan_id");
        $cuotas = $db->query("SELECT * FROM loan_installments WHERE loan_id=$loan_id ORDER BY installment_number");
        $acum = 0;
        while ($c = $cuotas->fetch_assoc()) {
            $acum += $c['amount'];
            if ($total_pagado >= $acum) {
                $db->query("UPDATE loan_installments SET status='pagada', paid_amount={$c['amount']} WHERE id={$c['id']}");
            } else break;
        }
    }
    
    $db->commit();
    $_SESSION['success'] = 'Pago #' . $id . ' eliminado';
} catch (Exception $e) {
    $db->rollback();
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
}
redirect("/modules/loans/collect.php?loan_id=$loan_id");
