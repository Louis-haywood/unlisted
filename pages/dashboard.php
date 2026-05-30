<?php
/** @var array $tenant */
$page_title = 'Dashboard';
$pdo        = get_pdo();
$tid        = (int)$tenant['id'];
$user       = current_user();

// ── Stat cards ────────────────────────────────────────────────────────────────
$s = $pdo->prepare('SELECT COUNT(*) FROM items WHERE tenant_id = ?');
$s->execute([$tid]);
$total_items = (int)$s->fetchColumn();

$s = $pdo->prepare('SELECT COUNT(DISTINCT item_id) FROM loans WHERE tenant_id = ? AND returned_at IS NULL');
$s->execute([$tid]);
$on_loan = (int)$s->fetchColumn();

$s = $pdo->prepare('SELECT COUNT(*) FROM items WHERE tenant_id = ? AND quantity <= low_stock_threshold AND quantity > 0');
$s->execute([$tid]);
$low_stock = (int)$s->fetchColumn();

$s = $pdo->prepare('SELECT COUNT(*) FROM borrowers WHERE tenant_id = ?');
$s->execute([$tid]);
$total_borrowers = (int)$s->fetchColumn();

// ── Recent items ──────────────────────────────────────────────────────────────
$s = $pdo->prepare("
    SELECT
        i.*,
        c.name   AS category_name,
        c.colour AS category_colour,
        COALESCE(la.active_loans,  0) AS active_loans,
        COALESCE(la.overdue_loans, 0) AS overdue_loans
    FROM items i
    LEFT JOIN categories c ON c.id = i.category_id AND c.tenant_id = i.tenant_id
    LEFT JOIN (
        SELECT
            item_id,
            COUNT(*) AS active_loans,
            SUM(CASE WHEN due_date IS NOT NULL AND due_date < CURDATE() THEN 1 ELSE 0 END) AS overdue_loans
        FROM loans
        WHERE tenant_id = ? AND returned_at IS NULL
        GROUP BY item_id
    ) la ON la.item_id = i.id
    WHERE i.tenant_id = ?
    ORDER BY i.created_at DESC
    LIMIT 10
");
$s->execute([$tid, $tid]);
$recent_items = $s->fetchAll();

// ── Recent activity ───────────────────────────────────────────────────────────
$s = $pdo->prepare("
    SELECT al.*, u.name AS user_name
    FROM activity_log al
    LEFT JOIN users u ON u.id = al.user_id
    WHERE al.tenant_id = ?
    ORDER BY al.created_at DESC
    LIMIT 10
");
$s->execute([$tid]);
$recent_activity = $s->fetchAll();

require __DIR__ . '/../templates/header.php';
require __DIR__ . '/../templates/sidebar.php';
?>

<main class="main-content">
    <div class="topbar">
        <div class="topbar-title">
            <h1>Dashboard</h1>
            <span class="topbar-sub">Welcome back, <?= h($user['name']) ?></span>
        </div>
        <div class="topbar-actions">
            <a href="/items/add"      class="btn btn-secondary">+ Add Item</a>
            <a href="/loans/checkout" class="btn btn-primary">Check Out</a>
        </div>
    </div>

    <?= flash_html() ?>

    <!-- Stat cards -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon stat-icon-blue">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
            </div>
            <div class="stat-body">
                <div class="stat-value"><?= $total_items ?></div>
                <div class="stat-label">Total Items</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-amber">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            </div>
            <div class="stat-body">
                <div class="stat-value"><?= $on_loan ?></div>
                <div class="stat-label">On Loan</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-red">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <div class="stat-body">
                <div class="stat-value"><?= $low_stock ?></div>
                <div class="stat-label">Low Stock</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-green">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div class="stat-body">
                <div class="stat-value"><?= $total_borrowers ?></div>
                <div class="stat-label">Total Borrowers</div>
            </div>
        </div>
    </div>

    <div class="two-col-layout">
        <!-- Recent items table -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Recent Items</h2>
                <a href="/items" class="card-link">View all</a>
            </div>
            <?php if (empty($recent_items)): ?>
                <div class="empty-state">
                    <p>No items yet. <a href="/items/add">Add your first item</a>.</p>
                </div>
            <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Barcode</th>
                        <th>Qty</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recent_items as $item): ?>
                    <?php $status = compute_item_status($item); ?>
                    <tr>
                        <td>
                            <a href="/items/edit?id=<?= (int)$item['id'] ?>" class="table-link">
                                <?= h($item['name']) ?>
                            </a>
                        </td>
                        <td>
                            <?php if ($item['category_name']): ?>
                                <span class="cat-badge" style="background:<?= h($item['category_colour']) ?>22; color:<?= h($item['category_colour']) ?>; border-color:<?= h($item['category_colour']) ?>44">
                                    <?= h($item['category_name']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($item['barcode']): ?>
                                <span class="barcode-text" data-copy="<?= h($item['barcode']) ?>" title="Click to copy"><?= h($item['barcode']) ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= (int)$item['quantity'] ?></td>
                        <td><?= status_pill($status) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Recent activity -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Recent Activity</h2>
            </div>
            <?php if (empty($recent_activity)): ?>
                <div class="empty-state"><p>No activity recorded yet.</p></div>
            <?php else: ?>
            <ul class="activity-feed">
                <?php foreach ($recent_activity as $entry): ?>
                <li class="activity-item">
                    <div class="activity-dot"></div>
                    <div class="activity-body">
                        <span class="activity-desc"><?= h($entry['description']) ?></span>
                        <span class="activity-meta">
                            <?= $entry['user_name'] ? h($entry['user_name']) . ' · ' : '' ?>
                            <?= h(time_ago($entry['created_at'])) ?>
                        </span>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require __DIR__ . '/../templates/footer.php'; ?>
