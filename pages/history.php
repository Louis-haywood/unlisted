<?php
$page_title   = 'Loan History';
$pdo          = get_pdo();
$tid          = (int)$tenant['id'];
$current_page = max(1, (int)($_GET['page'] ?? 1));
$per_page     = 25;

$s = $pdo->prepare('SELECT COUNT(*) FROM loans WHERE tenant_id = ? AND returned_at IS NOT NULL');
$s->execute([$tid]);
$total = (int)$s->fetchColumn();
$pag   = paginate($total, $per_page, $current_page);

$s = $pdo->prepare("
    SELECT
        l.*,
        i.name  AS item_name,
        b.name  AS borrower_name,
        DATEDIFF(l.returned_at, l.checked_out_at) AS duration_days
    FROM loans l
    JOIN items     i ON i.id = l.item_id
    JOIN borrowers b ON b.id = l.borrower_id
    WHERE l.tenant_id = ? AND l.returned_at IS NOT NULL
    ORDER BY l.returned_at DESC
    LIMIT ? OFFSET ?
");
$s->execute([$tid, $per_page, $pag['offset']]);
$history = $s->fetchAll();

require __DIR__ . '/../templates/header.php';
require __DIR__ . '/../templates/sidebar.php';
?>

<main class="main-content">
    <div class="topbar">
        <div class="topbar-title">
            <h1>Loan History</h1>
            <span class="topbar-sub"><?= number_format($total) ?> completed loan<?= $total !== 1 ? 's' : '' ?></span>
        </div>
    </div>

    <?= flash_html() ?>

    <div class="card">
        <?php if (empty($history)): ?>
            <div class="empty-state">
                <p>No completed loans yet. Items you return will appear here.</p>
            </div>
        <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Borrower</th>
                    <th>Qty</th>
                    <th>Checked Out</th>
                    <th>Returned</th>
                    <th>Duration</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($history as $l): ?>
                <tr>
                    <td>
                        <a href="/items/edit?id=<?= (int)$l['item_id'] ?>" class="table-link fw-medium">
                            <?= h($l['item_name']) ?>
                        </a>
                    </td>
                    <td>
                        <a href="/borrowers?id=<?= (int)$l['borrower_id'] ?>" class="table-link">
                            <?= h($l['borrower_name']) ?>
                        </a>
                    </td>
                    <td><?= (int)$l['quantity_loaned'] ?></td>
                    <td class="text-muted"><?= h(date('d M Y', strtotime($l['checked_out_at']))) ?></td>
                    <td class="text-muted"><?= h(date('d M Y', strtotime($l['returned_at']))) ?></td>
                    <td>
                        <span class="pill pill-success">
                            <?= max(0, (int)$l['duration_days']) ?>d
                        </span>
                    </td>
                    <td class="text-muted">
                        <?= $l['notes'] ? h(mb_strimwidth($l['notes'], 0, 60, '…')) : '—' ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?= pagination_html($pag, '/history') ?>
        <?php endif; ?>
    </div>
</main>

<?php require __DIR__ . '/../templates/footer.php'; ?>
