<?php
// POST-only handler — processes a loan return and redirects back to /loans
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/loans');
}

if (!csrf_verify()) {
    flash_set('error', 'Invalid token.');
    redirect('/loans');
}

$pdo     = get_pdo();
$tid     = (int)$tenant['id'];
$user    = current_user();
$loan_id = (int)($_POST['loan_id'] ?? 0);

if (!$loan_id) {
    flash_set('error', 'No loan specified.');
    redirect('/loans');
}

// Load the loan
$s = $pdo->prepare("
    SELECT l.*, i.name AS item_name
    FROM loans l
    JOIN items i ON i.id = l.item_id
    WHERE l.id = ? AND l.tenant_id = ? AND l.returned_at IS NULL
");
$s->execute([$loan_id, $tid]);
$loan = $s->fetch();

if (!$loan) {
    flash_set('error', 'Loan not found or already returned.');
    redirect('/loans');
}

$pdo->beginTransaction();
try {
    // Mark returned
    $pdo->prepare('UPDATE loans SET returned_at = NOW() WHERE id = ? AND tenant_id = ?')
        ->execute([$loan_id, $tid]);

    // Restore quantity
    $pdo->prepare('UPDATE items SET quantity = quantity + ? WHERE id = ? AND tenant_id = ?')
        ->execute([$loan['quantity_loaned'], $loan['item_id'], $tid]);

    $pdo->commit();

    log_activity($tid, (int)$user['id'], 'return',
        'Returned ' . $loan['quantity_loaned'] . 'x "' . $loan['item_name'] . '"');

    flash_set('success', '"' . $loan['item_name'] . '" marked as returned.');
} catch (Exception $e) {
    $pdo->rollBack();
    flash_set('error', 'Failed to process return. Please try again.');
}

redirect('/loans');
