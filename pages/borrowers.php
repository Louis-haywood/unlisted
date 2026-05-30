<?php
/** @var array $tenant */
$page_title = 'Borrowers';
$pdo        = get_pdo();
$tid        = (int)$tenant['id'];
$user       = current_user();

// Single borrower profile view
$view_id = (int)($_GET['id'] ?? 0);

if ($view_id) {
    $s = $pdo->prepare('SELECT * FROM borrowers WHERE id = ? AND tenant_id = ?');
    $s->execute([$view_id, $tid]);
    $borrower = $s->fetch();
    if (!$borrower) { http_response_code(404); require __DIR__ . '/../templates/404.php'; exit; }

    // All loans for this borrower
    $s = $pdo->prepare("
        SELECT l.*, i.name AS item_name, i.barcode AS item_barcode
        FROM loans l
        JOIN items i ON i.id = l.item_id
        WHERE l.borrower_id = ? AND l.tenant_id = ?
        ORDER BY l.checked_out_at DESC
    ");
    $s->execute([$view_id, $tid]);
    $borrower_loans = $s->fetchAll();

    $active_count = 0;
    foreach ($borrower_loans as $l) { if ($l['returned_at'] === null) $active_count++; }

    require __DIR__ . '/../templates/header.php';
    require __DIR__ . '/../templates/sidebar.php';
    ?>

<main class="main-content">
    <div class="topbar">
        <div class="topbar-title">
            <a href="/borrowers" class="back-link">← Borrowers</a>
            <h1><?= h($borrower['name']) ?></h1>
        </div>
        <div class="topbar-actions">
            <a href="/loans/checkout" class="btn btn-primary">New Checkout</a>
        </div>
    </div>

    <div class="two-col-layout" style="align-items:start">
        <div class="card form-card">
            <div class="card-header"><h2 class="card-title">Contact Details</h2></div>
            <dl class="detail-list">
                <dt>Name</dt><dd><?= h($borrower['name']) ?></dd>
                <dt>Email</dt><dd><?= $borrower['email'] ? '<a href="mailto:' . h($borrower['email']) . '">' . h($borrower['email']) . '</a>' : '<span class="text-muted">—</span>' ?></dd>
                <dt>Phone</dt><dd><?= $borrower['phone'] ? h($borrower['phone']) : '<span class="text-muted">—</span>' ?></dd>
                <dt>Address</dt><dd><?= $borrower['address'] ? nl2br(h($borrower['address'])) : '<span class="text-muted">—</span>' ?></dd>
                <dt>Active Loans</dt><dd><?= $active_count ?></dd>
                <dt>Member Since</dt><dd><?= h(date('d M Y', strtotime($borrower['created_at']))) ?></dd>
            </dl>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Loan History</h2></div>
            <?php if (empty($borrower_loans)): ?>
                <div class="empty-state"><p>No loans recorded for this borrower.</p></div>
            <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Checked Out</th>
                        <th>Due</th>
                        <th>Returned</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($borrower_loans as $l): ?>
                    <tr>
                        <td>
                            <a href="/items/edit?id=<?= (int)$l['item_id'] ?>" class="table-link"><?= h($l['item_name']) ?></a>
                            <?php if ($l['item_barcode']): ?>
                                <br><span class="barcode-text" data-copy="<?= h($l['item_barcode']) ?>" style="font-size:0.75rem"><?= h($l['item_barcode']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= (int)$l['quantity_loaned'] ?></td>
                        <td class="text-muted"><?= h(date('d M Y', strtotime($l['checked_out_at']))) ?></td>
                        <td class="text-muted"><?= $l['due_date'] ? h(date('d M Y', strtotime($l['due_date']))) : '—' ?></td>
                        <td>
                            <?php if ($l['returned_at']): ?>
                                <span class="pill pill-success"><?= h(date('d M Y', strtotime($l['returned_at']))) ?></span>
                            <?php else: ?>
                                <?php $overdue = $l['due_date'] && $l['due_date'] < date('Y-m-d'); ?>
                                <span class="pill <?= $overdue ? 'pill-danger' : 'pill-warning' ?>"><?= $overdue ? 'Overdue' : 'Active' ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</main>

    <?php require __DIR__ . '/../templates/footer.php';
    exit;
}

// ── List all borrowers ────────────────────────────────────────────────────────

// Handle add borrower
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    if (!csrf_verify()) { flash_set('error', 'Invalid token.'); redirect('/borrowers'); }
    $name    = trim($_POST['name']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $phone   = trim($_POST['phone']   ?? '');
    $address = trim($_POST['address'] ?? '');
    if ($name === '') { flash_set('error', 'Name is required.'); redirect('/borrowers'); }
    $s = $pdo->prepare('INSERT INTO borrowers (tenant_id, name, email, phone, address) VALUES (?, ?, ?, ?, ?)');
    $s->execute([$tid, $name, $email, $phone, $address]);
    log_activity($tid, (int)$user['id'], 'borrower_added', 'Added borrower: ' . $name);
    flash_set('success', 'Borrower "' . $name . '" added.');
    redirect('/borrowers');
}

// Search
$search = trim($_GET['search'] ?? '');
$current_page = max(1, (int)($_GET['page'] ?? 1));
$per_page     = 20;

$extra_where  = '';
$extra_params = [];
if ($search !== '') {
    $extra_where   = 'AND (b.name LIKE ? OR b.email LIKE ? OR b.phone LIKE ?)';
    $extra_params[] = '%' . $search . '%';
    $extra_params[] = '%' . $search . '%';
    $extra_params[] = '%' . $search . '%';
}

$s = $pdo->prepare("SELECT COUNT(*) FROM borrowers b WHERE b.tenant_id = ? $extra_where");
$s->execute(array_merge([$tid], $extra_params));
$total = (int)$s->fetchColumn();
$pag   = paginate($total, $per_page, $current_page);

$s = $pdo->prepare("
    SELECT b.*,
        COUNT(l.id)                                                                      AS total_loans,
        SUM(CASE WHEN l.returned_at IS NULL THEN 1 ELSE 0 END)                          AS active_loans
    FROM borrowers b
    LEFT JOIN loans l ON l.borrower_id = b.id AND l.tenant_id = b.tenant_id
    WHERE b.tenant_id = ? $extra_where
    GROUP BY b.id
    ORDER BY b.name
    LIMIT ? OFFSET ?
");
$s->execute(array_merge([$tid], $extra_params, [$per_page, $pag['offset']]));
$borrowers = $s->fetchAll();

require __DIR__ . '/../templates/header.php';
require __DIR__ . '/../templates/sidebar.php';
?>

<main class="main-content">
    <div class="topbar">
        <div class="topbar-title">
            <h1>Borrowers</h1>
            <span class="topbar-sub"><?= number_format($total) ?> borrower<?= $total !== 1 ? 's' : '' ?></span>
        </div>
    </div>

    <?= flash_html() ?>

    <div class="two-col-layout" style="align-items:start">

        <!-- Borrowers list -->
        <div>
            <div class="card filter-bar" style="margin-bottom:1rem">
                <form method="GET" action="/borrowers" class="filter-form">
                    <div class="filter-search">
                        <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" name="search" class="form-input search-input" placeholder="Search name, email, phone…" value="<?= h($search) ?>">
                    </div>
                    <button type="submit" class="btn btn-secondary">Search</button>
                    <?php if ($search): ?><a href="/borrowers" class="btn btn-ghost">Clear</a><?php endif; ?>
                </form>
            </div>

            <div class="card">
                <?php if (empty($borrowers)): ?>
                    <div class="empty-state">
                        <?php if ($search): ?>
                            <p>No borrowers match your search. <a href="/borrowers">Clear search</a>.</p>
                        <?php else: ?>
                            <p>No borrowers yet. Add one using the form.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Active Loans</th>
                            <th>Total Loans</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($borrowers as $b): ?>
                        <tr>
                            <td>
                                <a href="/borrowers?id=<?= (int)$b['id'] ?>" class="table-link fw-medium">
                                    <?= h($b['name']) ?>
                                </a>
                            </td>
                            <td class="text-muted"><?= $b['email'] ? h($b['email']) : '—' ?></td>
                            <td class="text-muted"><?= $b['phone'] ? h($b['phone']) : '—' ?></td>
                            <td>
                                <?php $al = (int)$b['active_loans']; ?>
                                <?php if ($al > 0): ?>
                                    <span class="pill pill-warning"><?= $al ?></span>
                                <?php else: ?>
                                    <span class="text-muted">0</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted"><?= (int)$b['total_loans'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?= pagination_html($pag, '/borrowers' . ($search ? '?search=' . urlencode($search) : '')) ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Add borrower form -->
        <div class="card form-card">
            <div class="card-header"><h2 class="card-title">Add Borrower</h2></div>
            <form method="POST" action="/borrowers">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label class="form-label" for="bname">Name <span class="required">*</span></label>
                    <input type="text" id="bname" name="name" class="form-input" placeholder="Full name" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="bemail">Email</label>
                    <input type="email" id="bemail" name="email" class="form-input" placeholder="email@example.com">
                </div>
                <div class="form-group">
                    <label class="form-label" for="bphone">Phone</label>
                    <input type="tel" id="bphone" name="phone" class="form-input" placeholder="+44 7000 000000">
                </div>
                <div class="form-group">
                    <label class="form-label" for="baddress">Address</label>
                    <textarea id="baddress" name="address" class="form-input form-textarea" rows="3" placeholder="Optional"></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Add Borrower</button>
                </div>
            </form>
        </div>

    </div>
</main>

<?php require __DIR__ . '/../templates/footer.php'; ?>
