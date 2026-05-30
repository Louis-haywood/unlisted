<?php
$page_title = 'Add Item';
$pdo        = get_pdo();
$tid        = (int)$tenant['id'];
$user       = current_user();

// Check item limit
$s = $pdo->prepare('SELECT COUNT(*) FROM items WHERE tenant_id = ?');
$s->execute([$tid]);
$item_count = (int)$s->fetchColumn();

if ($item_count >= (int)$tenant['item_limit']) {
    flash_set('error', 'Item limit reached (' . $tenant['item_limit'] . '). Upgrade your plan to add more.');
    redirect('/items');
}

// Categories
$s = $pdo->prepare('SELECT * FROM categories WHERE tenant_id = ? ORDER BY name');
$s->execute([$tid]);
$categories = $s->fetchAll();

$errors = [];
$values = [
    'name'               => '',
    'category_id'        => '',
    'description'        => '',
    'quantity'           => '0',
    'low_stock_threshold'=> '5',
    'serial_number'      => '',
    'barcode'            => '',
];

// Handle quick-add category (AJAX)
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
        $s = $pdo->prepare("
            INSERT INTO items
                (tenant_id, category_id, name, description, quantity, low_stock_threshold, serial_number, barcode)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $s->execute([
            $tid,
            $values['category_id'] ?: null,
            $values['name'],
            $values['description'],
            $values['quantity'],
            $values['low_stock_threshold'],
            $values['serial_number'] ?: null,
            $values['barcode']       ?: null,
        ]);
        $item_id = (int)$pdo->lastInsertId();

        // Handle photo upload
        if (!empty($_FILES['photo']['tmp_name'])) {
            $path = upload_photo($_FILES['photo'], $tid, $item_id);
            if ($path) {
                $pdo->prepare('UPDATE items SET photo_path = ? WHERE id = ?')->execute([$path, $item_id]);
            } else {
                flash_set('error', 'Item saved but photo upload failed (check file type/size).');
            }
        }

        log_activity($tid, (int)$user['id'], 'item_added', 'Added item: ' . $values['name']);
        flash_set('success', 'Item "' . $values['name'] . '" added successfully.');
        redirect('/items');
    }
}

require __DIR__ . '/../templates/header.php';
require __DIR__ . '/../templates/sidebar.php';
?>

<main class="main-content">
    <div class="topbar">
        <div class="topbar-title">
            <a href="/items" class="back-link">← Items</a>
            <h1>Add Item</h1>
        </div>
    </div>

    <?= flash_html() ?>

    <?php if ($errors): ?>
        <div class="alert alert-error">
            <ul class="error-list"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <div class="card form-card">
        <form method="POST" action="/items/add" enctype="multipart/form-data" id="item-form">
            <?= csrf_field() ?>

            <div class="form-grid">
                <!-- Name -->
                <div class="form-group form-col-full">
                    <label class="form-label" for="name">Name <span class="required">*</span></label>
                    <input type="text" id="name" name="name" class="form-input" value="<?= h($values['name']) ?>" required autofocus>
                </div>

                <!-- Category -->
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

                <!-- Quantity -->
                <div class="form-group">
                    <label class="form-label" for="quantity">Quantity</label>
                    <input type="number" id="quantity" name="quantity" class="form-input" min="0" value="<?= h($values['quantity']) ?>">
                </div>

                <!-- Low stock threshold -->
                <div class="form-group">
                    <label class="form-label" for="low_stock_threshold">Low Stock Threshold</label>
                    <input type="number" id="low_stock_threshold" name="low_stock_threshold" class="form-input" min="0" value="<?= h($values['low_stock_threshold']) ?>">
                </div>

                <!-- Serial number -->
                <div class="form-group">
                    <label class="form-label" for="serial_number">Serial Number</label>
                    <input type="text" id="serial_number" name="serial_number" class="form-input" value="<?= h($values['serial_number']) ?>">
                </div>

                <!-- Barcode -->
                <div class="form-group">
                    <label class="form-label" for="barcode">Barcode</label>
                    <div class="input-with-btn">
                        <input type="text" id="barcode" name="barcode" class="form-input barcode-font" value="<?= h($values['barcode']) ?>" placeholder="e.g. LV-A1B2C3D4E5">
                        <button type="button" class="btn btn-secondary" id="gen-barcode">Generate</button>
                    </div>
                </div>

                <!-- Description -->
                <div class="form-group form-col-full">
                    <label class="form-label" for="description">Description</label>
                    <textarea id="description" name="description" class="form-input form-textarea" rows="3"><?= h($values['description']) ?></textarea>
                </div>

                <!-- Photo -->
                <div class="form-group form-col-full">
                    <label class="form-label" for="photo">Photo</label>
                    <input type="file" id="photo" name="photo" class="form-input" accept="image/jpeg,image/png,image/gif,image/webp">
                    <span class="form-hint">JPG, PNG, GIF or WebP — max 5 MB</span>
                    <img id="photo-preview" src="" alt="" style="display:none; margin-top:0.75rem; max-width:200px; border-radius:6px;">
                </div>
            </div>

            <div class="form-actions">
                <a href="/items" class="btn btn-ghost">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Item</button>
            </div>
        </form>
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
// Barcode generate
document.getElementById('gen-barcode').addEventListener('click', function() {
    var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    var code = 'LV-';
    for (var i = 0; i < 10; i++) code += chars[Math.floor(Math.random() * chars.length)];
    document.getElementById('barcode').value = code;
});

// Photo preview
document.getElementById('photo').addEventListener('change', function() {
    var prev = document.getElementById('photo-preview');
    if (this.files && this.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) { prev.src = e.target.result; prev.style.display = 'block'; };
        reader.readAsDataURL(this.files[0]);
    }
});

// Quick-add category modal
var catModal = document.getElementById('add-cat-modal');
document.getElementById('add-cat-btn').addEventListener('click', function() { catModal.style.display = 'flex'; });
document.getElementById('add-cat-cancel').addEventListener('click', function() { catModal.style.display = 'none'; });

document.getElementById('add-cat-save').addEventListener('click', function() {
    var name   = document.getElementById('new-cat-name').value.trim();
    var colour = document.getElementById('new-cat-colour').value;
    if (!name) { alert('Category name is required.'); return; }

    var form = new FormData();
    form.append('action', 'add_category');
    form.append('csrf_token', document.querySelector('[name=csrf_token]').value);
    form.append('cat_name',   name);
    form.append('cat_colour', colour);

    fetch('/items/add', { method: 'POST', body: form })
        .then(r => r.json())
        .then(data => {
            if (data.error) { alert(data.error); return; }
            var sel = document.getElementById('category_id');
            var opt = document.createElement('option');
            opt.value  = data.id;
            opt.text   = data.name;
            opt.dataset.colour = data.colour;
            opt.selected = true;
            sel.appendChild(opt);
            catModal.style.display = 'none';
            document.getElementById('new-cat-name').value = '';
        })
        .catch(() => alert('Failed to add category.'));
});
</script>

<?php require __DIR__ . '/../templates/footer.php'; ?>
