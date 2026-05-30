<?php
$page_title = 'Items';
$pdo        = get_pdo();
$tid        = (int)$tenant['id'];

// ── Filters ───────────────────────────────────────────────────────────────────
$search      = trim($_GET['search']   ?? '');
$category_id = (int)($_GET['category'] ?? 0);
$current_page = max(1, (int)($_GET['page'] ?? 1));
$per_page     = 25;

// Build WHERE clause
$where  = 'i.tenant_id = ?';
$params = [$tid, $tid]; // first for subquery, second for outer

$extra_where  = '';
$extra_params = [];

if ($search !== '') {
    $extra_where   .= ' AND (i.name LIKE ? OR i.barcode LIKE ? OR i.serial_number LIKE ?)';
    $extra_params[] = '%' . $search . '%';
    $extra_params[] = '%' . $search . '%';
    $extra_params[] = '%' . $search . '%';
}
if ($category_id > 0) {
    $extra_where   .= ' AND i.category_id = ?';
    $extra_params[] = $category_id;
}

// Count total
$count_sql = "
    SELECT COUNT(*) FROM items i WHERE i.tenant_id = ?
    " . ($extra_where ? $extra_where : '');
$s = $pdo->prepare($count_sql);
$s->execute(array_merge([$tid], $extra_params));
$total = (int)$s->fetchColumn();
$pag   = paginate($total, $per_page, $current_page);

// Fetch items
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
    " . $extra_where . "
    ORDER BY i.created_at DESC
    LIMIT ? OFFSET ?
");
$s->execute(array_merge([$tid, $tid], $extra_params, [$per_page, $pag['offset']]));
$items = $s->fetchAll();

// Categories for filter dropdown
$s = $pdo->prepare('SELECT * FROM categories WHERE tenant_id = ? ORDER BY name');
$s->execute([$tid]);
$categories = $s->fetchAll();

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!csrf_verify()) { flash_set('error', 'Invalid token.'); redirect('/items'); }
    $del_id = (int)($_POST['item_id'] ?? 0);
    // Get item name first for log
    $s = $pdo->prepare('SELECT name FROM items WHERE id = ? AND tenant_id = ?');
    $s->execute([$del_id, $tid]);
    $del_item = $s->fetch();
    if ($del_item) {
        $pdo->prepare('DELETE FROM items WHERE id = ? AND tenant_id = ?')->execute([$del_id, $tid]);
        log_activity($tid, $user['id'] ?? null, 'item_deleted', 'Deleted item: ' . $del_item['name']);
        flash_set('success', 'Item deleted.');
    }
    redirect('/items');
}

$user = current_user();
require __DIR__ . '/../templates/header.php';
require __DIR__ . '/../templates/sidebar.php';
?>

<main class="main-content">
    <div class="topbar">
        <div class="topbar-title">
            <h1>Items</h1>
            <span class="topbar-sub"><?= number_format($total) ?> item<?= $total !== 1 ? 's' : '' ?></span>
        </div>
        <div class="topbar-actions">
            <a href="/items/add" class="btn btn-primary">+ Add Item</a>
        </div>
    </div>

    <?= flash_html() ?>

    <!-- Filters -->
    <div class="card filter-bar">
        <form method="GET" action="/items" class="filter-form">
            <div class="filter-search">
                <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input
                    type="text"
                    name="search"
                    class="form-input search-input"
                    placeholder="Search name, barcode, serial…"
                    value="<?= h($search) ?>"
                    id="barcode-search"
                    autocomplete="off"
                >
            </div>
            <select name="category" class="form-input form-select">
                <option value="0">All categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>" <?= $category_id === (int)$cat['id'] ? 'selected' : '' ?>>
                        <?= h($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-secondary">Filter</button>
            <?php if ($search || $category_id): ?>
                <a href="/items" class="btn btn-ghost">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Items table -->
    <div class="card">
        <?php if (empty($items)): ?>
            <div class="empty-state">
                <?php if ($search || $category_id): ?>
                    <p>No items match your filters. <a href="/items">Clear filters</a>.</p>
                <?php else: ?>
                    <p>No items yet. <a href="/items/add">Add your first item</a>.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th style="width:48px"></th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Barcode</th>
                    <th>Serial No.</th>
                    <th>Qty</th>
                    <th>Status</th>
                    <th style="width:140px">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <?php $status = compute_item_status($item); ?>
                <tr>
                    <td>
                        <?php if ($item['photo_path']): ?>
                            <img src="/uploads/<?= h($item['photo_path']) ?>" class="item-thumb" alt="">
                        <?php else: ?>
                            <div class="item-thumb-placeholder"></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="/items/edit?id=<?= (int)$item['id'] ?>" class="table-link fw-medium">
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
                            <span class="barcode-text" data-copy="<?= h($item['barcode']) ?>" title="Click to copy">
                                <?= h($item['barcode']) ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted"><?= $item['serial_number'] ? h($item['serial_number']) : '—' ?></td>
                    <td class="fw-medium"><?= (int)$item['quantity'] ?></td>
                    <td><?= status_pill($status) ?></td>
                    <td>
                        <div class="action-btns">
                            <a href="/items/edit?id=<?= (int)$item['id'] ?>" class="btn btn-xs btn-secondary">Edit</a>
                            <a href="/loans/checkout?item_id=<?= (int)$item['id'] ?>" class="btn btn-xs btn-primary">Out</a>
                            <form method="POST" action="/items" style="display:inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action"  value="delete">
                                <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-danger"
                                    data-confirm="Delete '<?= h(addslashes($item['name'])) ?>'? This cannot be undone.">
                                    Del
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php
        $base = '/items?' . http_build_query(array_filter(['search' => $search, 'category' => $category_id ?: null]));
        echo pagination_html($pag, $base);
        ?>
        <?php endif; ?>
    </div>
</main>

<script>
// Barcode scanner detection on search field
(function() {
    const input = document.getElementById('barcode-search');
    if (!input) return;
    let chars = [], lastTime = 0;
    input.addEventListener('keydown', function(e) {
        const now = Date.now();
        if (e.key === 'Enter') {
            if (chars.length >= 4) input.form.submit();
            chars = [];
            return;
        }
        if (now - lastTime < 50 && e.key.length === 1) {
            chars.push(e.key);
        } else {
            chars = [e.key];
        }
        lastTime = now;
    });
})();
</script>

<?php require __DIR__ . '/../templates/footer.php'; ?>
