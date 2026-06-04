<?php
/** @var array $tenant */
$page_title = 'Active Loans';
$pdo        = get_pdo();
$tid        = (int)$tenant['id'];
$user       = current_user();

$s = $pdo->prepare("
    SELECT
        l.*,
        i.name        AS item_name,
        i.barcode     AS item_barcode,
        i.photo_path  AS item_photo,
        b.name        AS borrower_name,
        b.email       AS borrower_email,
        CASE WHEN l.due_date IS NOT NULL AND l.due_date < CURDATE() THEN 1 ELSE 0 END AS is_overdue
    FROM loans l
    JOIN items     i ON i.id = l.item_id
    JOIN borrowers b ON b.id = l.borrower_id
    WHERE l.tenant_id = ? AND l.returned_at IS NULL
    ORDER BY is_overdue DESC, l.checked_out_at ASC
");
$s->execute([$tid]);
$loans = $s->fetchAll();

require __DIR__ . '/../templates/header.php';
require __DIR__ . '/../templates/sidebar.php';
?>

<main class="main-content">
    <div class="topbar">
        <div class="topbar-title">
            <h1>Active Loans</h1>
            <span class="topbar-sub"><?= count($loans) ?> active loan<?= count($loans) !== 1 ? 's' : '' ?></span>
        </div>
        <div class="topbar-actions">
            <a href="/loans/return" class="btn btn-secondary">📷 Scan to Return</a>
            <a href="/loans/checkout" class="btn btn-primary">New Check Out</a>
        </div>
    </div>

    <?= flash_html() ?>

    <div class="card">
        <?php if (empty($loans)): ?>
            <div class="empty-state">
                <p>No active loans. <a href="/loans/checkout">Check something out</a>.</p>
            </div>
        <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Borrower</th>
                    <th>Qty</th>
                    <th>Checked Out</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th style="width:100px">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($loans as $loan): ?>
                <tr class="<?= $loan['is_overdue'] ? 'row-overdue' : '' ?>">
                    <td>
                        <div style="display:flex; align-items:center; gap:0.5rem">
                            <?php if ($loan['item_photo']): ?>
                                <img src="/uploads/<?= h($loan['item_photo']) ?>" class="item-thumb" alt="">
                            <?php endif; ?>
                            <div>
                                <a href="/items/edit?id=<?= (int)$loan['item_id'] ?>" class="table-link fw-medium">
                                    <?= h($loan['item_name']) ?>
                                </a>
                                <?php if ($loan['item_barcode']): ?>
                                    <br><span class="barcode-text" data-copy="<?= h($loan['item_barcode']) ?>" style="font-size:0.75rem"><?= h($loan['item_barcode']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <a href="/borrowers?id=<?= (int)$loan['borrower_id'] ?>" class="table-link">
                            <?= h($loan['borrower_name']) ?>
                        </a>
                        <?php if ($loan['borrower_email']): ?>
                            <br><span class="text-muted" style="font-size:0.8rem"><?= h($loan['borrower_email']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="fw-medium"><?= (int)$loan['quantity_loaned'] ?></td>
                    <td class="text-muted"><?= h(date('d M Y', strtotime($loan['checked_out_at']))) ?></td>
                    <td>
                        <?php if ($loan['due_date']): ?>
                            <span class="<?= $loan['is_overdue'] ? 'text-danger fw-medium' : 'text-muted' ?>">
                                <?= h(date('d M Y', strtotime($loan['due_date']))) ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($loan['is_overdue']): ?>
                            <span class="pill pill-danger">Overdue</span>
                        <?php else: ?>
                            <span class="pill pill-warning">On Loan</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="btn btn-xs btn-success return-btn"
                            data-loan-id="<?= (int)$loan['id'] ?>"
                            data-item-name="<?= h(addslashes($loan['item_name'])) ?>"
                            data-borrower="<?= h(addslashes($loan['borrower_name'])) ?>">
                            Return
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</main>

<!-- Return confirmation modal -->
<div id="return-modal" class="modal-overlay" style="display:none">
    <div class="modal-box">
        <h3 class="modal-title">Confirm Return</h3>
        <p class="modal-body" id="return-modal-body"></p>
        <form method="POST" action="/loans/return" id="return-form">
            <?= csrf_field() ?>
            <input type="hidden" name="loan_id" id="return-loan-id" value="">
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" id="return-cancel">Cancel</button>
                <button type="submit" class="btn btn-success">Mark as Returned</button>
            </div>
        </form>
    </div>
</div>

<script>
var returnModal = document.getElementById('return-modal');
var returnBody  = document.getElementById('return-modal-body');
var returnId    = document.getElementById('return-loan-id');

document.querySelectorAll('.return-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        returnBody.textContent = 'Return "' + this.dataset.itemName + '" from ' + this.dataset.borrower + '?';
        returnId.value = this.dataset.loanId;
        returnModal.style.display = 'flex';
    });
});
document.getElementById('return-cancel').addEventListener('click', function() {
    returnModal.style.display = 'none';
});
</script>

<?php require __DIR__ . '/../templates/footer.php'; ?>
