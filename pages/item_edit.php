<?php
/** @var array $tenant */
$page_title = 'Edit Item';
$pdo        = get_pdo();
$tid        = (int)$tenant['id'];
$user       = current_user();
$item_id    = (int)($_GET['id'] ?? 0);

// Load item
$s = $pdo->prepare('SELECT * FROM items WHERE id = ? AND tenant_id = ?');
$s->execute([$item_id, $tid]);
$item = $s->fetch();
if (!$item) { http_response_code(404); require __DIR__ . '/../templates/404.php'; exit; }

// Categories
$s = $pdo->prepare('SELECT * FROM categories WHERE tenant_id = ? ORDER BY name');
$s->execute([$tid]);
$categories = $s->fetchAll();

// Loan history for this item
$s = $pdo->prepare("
    SELECT l.*, b.name AS borrower_name, b.email AS borrower_email
    FROM loans l
    JOIN borrowers b ON b.id = l.borrower_id
    WHERE l.item_id = ? AND l.tenant_id = ?
    ORDER BY l.checked_out_at DESC
    LIMIT 20
");
$s->execute([$item_id, $tid]);
$loan_history = $s->fetchAll();

$errors = [];
$values = [
    'name'               => $item['name'],
    'category_id'        => $item['category_id'],
    'description'        => $item['description'],
    'quantity'           => $item['quantity'],
    'low_stock_threshold'=> $item['low_stock_threshold'],
    'serial_number'      => $item['serial_number'],
    'barcode'            => $item['barcode'],
];

// Quick-add category (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_category') {
    if (!csrf_verify()) { echo json_encode(['error' => 'Invalid token']); exit; }
    $cat_name   = trim($_POST['cat_name']   ?? '');
    $cat_colour = trim($_POST['cat_colour'] ?? '#378ADD');
    if ($cat_name === '') { echo json_encode(['error' => 'Name required']); exit; }
    $s = $pdo->prepare('INSERT INTO categories (tenant_id, name, colour) VALUES (?, ?, ?)');
    $s->execute([$tid, $cat_name, $cat_colour]);
    $cat_id = $pdo->lastInsertId();
    log_activity($tid, (int)$user['id'], 'category_added', 'Added category: ' . $cat_name);
    header('Content-Type: application/json');
    echo json_encode(['id' => $cat_id, 'name' => $cat_name, 'colour' => $cat_colour]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'add_category') {
    if (!csrf_verify()) { $errors[] = 'Invalid form submission.'; }

    $values = [
        'name'               => trim($_POST['name']                ?? ''),
        'category_id'        => (int)($_POST['category_id']        ?? 0),
        'description'        => trim($_POST['description']         ?? ''),
        'quantity'           => (int)($_POST['quantity']           ?? 0),
        'low_stock_threshold'=> (int)($_POST['low_stock_threshold']?? 5),
        'serial_number'      => trim($_POST['serial_number']       ?? ''),
        'barcode'            => trim($_POST['barcode']             ?? ''),
    ];

    if ($values['name'] === '') $errors[] = 'Item name is required.';

    if (empty($errors)) {
        // Handle photo upload
        $photo_path = $item['photo_path'];
        if (!empty($_FILES['photo']['tmp_name'])) {
            $path = upload_photo($_FILES['photo'], $tid, $item_id);
            if ($path) {
                // Delete old photo
                if ($photo_path && file_exists(__DIR__ . '/../uploads/' . $photo_path)) {
                    @unlink(__DIR__ . '/../uploads/' . $photo_path);
                }
                $photo_path = $path;
            } else {
                $errors[] = 'Photo upload failed — check file type (JPG/PNG/GIF/WebP) and size (max 5 MB).';
            }
        }

        if (empty($errors)) {
            $s = $pdo->prepare("
                UPDATE items SET
                    category_id = ?, name = ?, description = ?, quantity = ?,
                    low_stock_threshold = ?, serial_number = ?, barcode = ?, photo_path = ?
                WHERE id = ? AND tenant_id = ?
            ");
            $s->execute([
                $values['category_id'] ?: null,
                $values['name'],
                $values['description'],
                $values['quantity'],
                $values['low_stock_threshold'],
                $values['serial_number'] ?: null,
                $values['barcode']       ?: null,
                $photo_path,
                $item_id,
                $tid,
            ]);

            log_activity($tid, (int)$user['id'], 'item_edited', 'Edited item: ' . $values['name']);
            flash_set('success', 'Item updated.');
            redirect('/items/edit?id=' . $item_id);
        }
    }
}

require __DIR__ . '/../templates/header.php';
require __DIR__ . '/../templates/sidebar.php';
?>

<main class="main-content">
    <div class="topbar">
        <div class="topbar-title">
            <a href="/items" class="back-link">← Items</a>
            <h1>Edit Item</h1>
        </div>
        <div class="topbar-actions">
            <a href="/loans/checkout?item_id=<?= $item_id ?>" class="btn btn-primary">Check Out</a>
        </div>
    </div>

    <?= flash_html() ?>

    <?php if ($errors): ?>
        <div class="alert alert-error">
            <ul class="error-list"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <div class="card form-card">
        <form method="POST" action="/items/edit?id=<?= $item_id ?>" enctype="multipart/form-data" id="item-form">
            <?= csrf_field() ?>

            <div class="form-grid">
                <div class="form-group form-col-full">
                    <label class="form-label" for="name">Name <span class="required">*</span></label>
                    <input type="text" id="name" name="name" class="form-input" value="<?= h($values['name']) ?>" required autofocus>
                </div>

                <div class="form-group">
                    <label class="form-label" for="category_id">Category</label>
                    <div class="input-with-link">
                        <select id="category_id" name="category_id" class="form-input form-select">
                            <option value="">— None —</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>"
                                    data-colour="<?= h($cat['colour']) ?>"
                                    <?= (int)$values['category_id'] === (int)$cat['id'] ? 'selected' : '' ?>>
                                    <?= h($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="link-btn" id="add-cat-btn">+ Add new</button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="quantity">Quantity</label>
                    <input type="number" id="quantity" name="quantity" class="form-input" min="0" value="<?= h($values['quantity']) ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="low_stock_threshold">Low Stock Threshold</label>
                    <input type="number" id="low_stock_threshold" name="low_stock_threshold" class="form-input" min="0" value="<?= h($values['low_stock_threshold']) ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="serial_number">Serial Number</label>
                    <input type="text" id="serial_number" name="serial_number" class="form-input" value="<?= h($values['serial_number'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="barcode">Barcode</label>
                    <div class="input-with-btn">
                        <input type="text" id="barcode" name="barcode" class="form-input barcode-font" value="<?= h($values['barcode'] ?? '') ?>">
                        <button type="button" class="btn btn-secondary" id="gen-barcode">Generate</button>
                    </div>
                </div>

                <div class="form-group form-col-full">
                    <label class="form-label" for="description">Description</label>
                    <textarea id="description" name="description" class="form-input form-textarea" rows="3"><?= h($values['description'] ?? '') ?></textarea>
                </div>

                <div class="form-group form-col-full">
                    <label class="form-label" for="photo">Photo</label>
                    <?php if ($item['photo_path']): ?>
                        <div style="margin-bottom:0.75rem">
                            <img src="/uploads/<?= h($item['photo_path']) ?>" alt="" style="max-width:200px; max-height:200px; object-fit:cover; border-radius:6px; border:1px solid #E5E7EB;">
                        </div>
                    <?php endif; ?>
                    <input type="file" id="photo" name="photo" class="form-input" accept="image/jpeg,image/png,image/gif,image/webp">
                    <span class="form-hint">Upload a new photo to replace the existing one. JPG, PNG, GIF or WebP — max 5 MB.</span>
                    <img id="photo-preview" src="" alt="" style="display:none; margin-top:0.75rem; max-width:200px; border-radius:6px;">
                </div>
            </div>

            <div class="form-actions">
                <a href="/items" class="btn btn-ghost">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>

    <!-- Loan history for this item -->
    <div class="card" style="margin-top:1.5rem">
        <div class="card-header">
            <h2 class="card-title">Loan History for This Item</h2>
        </div>
        <?php if (empty($loan_history)): ?>
            <div class="empty-state"><p>No loans recorded for this item.</p></div>
        <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Borrower</th>
                    <th>Qty</th>
                    <th>Checked Out</th>
                    <th>Due Date</th>
                    <th>Returned</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($loan_history as $loan): ?>
                <tr>
                    <td>
                        <a href="/borrowers?id=<?= (int)$loan['borrower_id'] ?>" class="table-link">
                            <?= h($loan['borrower_name']) ?>
                        </a>
                    </td>
                    <td><?= (int)$loan['quantity_loaned'] ?></td>
                    <td><?= h(date('d M Y H:i', strtotime($loan['checked_out_at']))) ?></td>
                    <td><?= $loan['due_date'] ? h(date('d M Y', strtotime($loan['due_date']))) : '<span class="text-muted">—</span>' ?></td>
                    <td>
                        <?php if ($loan['returned_at']): ?>
                            <span class="pill pill-success"><?= h(date('d M Y', strtotime($loan['returned_at']))) ?></span>
                        <?php else: ?>
                            <span class="pill pill-warning">Active</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted"><?= $loan['notes'] ? h(mb_strimwidth($loan['notes'], 0, 60, '…')) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</main>

<!-- Quick-add category modal -->
<div id="add-cat-modal" class="modal-overlay" style="display:none">
    <div class="modal-box">
        <h3 class="modal-title">Add Category</h3>
        <div class="form-group">
            <label class="form-label">Name</label>
            <input type="text" id="new-cat-name" class="form-input" placeholder="Category name">
        </div>
        <div class="form-group">
            <label class="form-label">Colour</label>
            <input type="color" id="new-cat-colour" class="form-input" value="#378ADD" style="height:42px; padding:4px 8px;">
        </div>
        <div class="modal-actions">
            <button class="btn btn-secondary" id="add-cat-cancel">Cancel</button>
            <button class="btn btn-primary"   id="add-cat-save">Add Category</button>
        </div>
    </div>
</div>

<script>
document.getElementById('gen-barcode').addEventListener('click', function() {
    var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    var code = 'LV-';
    for (var i = 0; i < 10; i++) code += chars[Math.floor(Math.random() * chars.length)];
    document.getElementById('barcode').value = code;
});

document.getElementById('photo').addEventListener('change', function() {
    var prev = document.getElementById('photo-preview');
    if (this.files && this.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) { prev.src = e.target.result; prev.style.display = 'block'; };
        reader.readAsDataURL(this.files[0]);
    }
});

var catModal = document.getElementById('add-cat-modal');
document.getElementById('add-cat-btn').addEventListener('click', function() { catModal.style.display = 'flex'; });
document.getElementById('add-cat-cancel').addEventListener('click', function() { catModal.style.display = 'none'; });

document.getElementById('add-cat-save').addEventListener('click', function() {
    var name   = document.getElementById('new-cat-name').value.trim();
    var colour = document.getElementById('new-cat-colour').value;
    if (!name) { alert('Category name is required.'); return; }
    var form = new FormData();
    form.append('action',     'add_category');
    form.append('csrf_token', document.querySelector('[name=csrf_token]').value);
    form.append('cat_name',   name);
    form.append('cat_colour', colour);
    fetch(window.location.href, { method: 'POST', body: form })
        .then(r => r.json())
        .then(data => {
            if (data.error) { alert(data.error); return; }
            var sel = document.getElementById('category_id');
            var opt = document.createElement('option');
            opt.value = data.id; opt.text = data.name; opt.selected = true;
            sel.appendChild(opt);
            catModal.style.display = 'none';
            document.getElementById('new-cat-name').value = '';
        });
});
</script>

<?php require __DIR__ . '/../templates/footer.php'; ?>
