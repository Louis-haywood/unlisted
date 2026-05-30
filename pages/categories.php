<?php
$page_title = 'Categories';
$pdo        = get_pdo();
$tid        = (int)$tenant['id'];
$user       = current_user();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { flash_set('error', 'Invalid token.'); redirect('/categories'); }

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name   = trim($_POST['name']   ?? '');
        $colour = trim($_POST['colour'] ?? '#378ADD');
        if ($name === '') { flash_set('error', 'Category name is required.'); redirect('/categories'); }
        $s = $pdo->prepare('INSERT INTO categories (tenant_id, name, colour) VALUES (?, ?, ?)');
        $s->execute([$tid, $name, $colour]);
        log_activity($tid, (int)$user['id'], 'category_added', 'Added category: ' . $name);
        flash_set('success', 'Category "' . $name . '" added.');
        redirect('/categories');
    }

    if ($action === 'edit') {
        $cat_id = (int)($_POST['cat_id'] ?? 0);
        $name   = trim($_POST['name']    ?? '');
        $colour = trim($_POST['colour']  ?? '#378ADD');
        if ($name === '') { flash_set('error', 'Category name is required.'); redirect('/categories'); }
        $s = $pdo->prepare('UPDATE categories SET name = ?, colour = ? WHERE id = ? AND tenant_id = ?');
        $s->execute([$name, $colour, $cat_id, $tid]);
        log_activity($tid, (int)$user['id'], 'category_edited', 'Edited category: ' . $name);
        flash_set('success', 'Category updated.');
        redirect('/categories');
    }

    if ($action === 'delete') {
        $cat_id = (int)($_POST['cat_id'] ?? 0);
        $s = $pdo->prepare('SELECT name FROM categories WHERE id = ? AND tenant_id = ?');
        $s->execute([$cat_id, $tid]);
        $cat = $s->fetch();
        if ($cat) {
            $pdo->prepare('DELETE FROM categories WHERE id = ? AND tenant_id = ?')->execute([$cat_id, $tid]);
            log_activity($tid, (int)$user['id'], 'category_deleted', 'Deleted category: ' . $cat['name']);
            flash_set('success', 'Category deleted.');
        }
        redirect('/categories');
    }
}

// Fetch categories with item counts
$s = $pdo->prepare("
    SELECT c.*, COUNT(i.id) AS item_count
    FROM categories c
    LEFT JOIN items i ON i.category_id = c.id AND i.tenant_id = c.tenant_id
    WHERE c.tenant_id = ?
    GROUP BY c.id
    ORDER BY c.name
");
$s->execute([$tid]);
$categories = $s->fetchAll();

// Load single category for edit form
$edit_cat = null;
$edit_id  = (int)($_GET['edit'] ?? 0);
if ($edit_id) {
    foreach ($categories as $cat) {
        if ((int)$cat['id'] === $edit_id) { $edit_cat = $cat; break; }
    }
}

require __DIR__ . '/../templates/header.php';
require __DIR__ . '/../templates/sidebar.php';
?>

<main class="main-content">
    <div class="topbar">
        <div class="topbar-title">
            <h1>Categories</h1>
            <span class="topbar-sub"><?= count($categories) ?> categor<?= count($categories) !== 1 ? 'ies' : 'y' ?></span>
        </div>
    </div>

    <?= flash_html() ?>

    <div class="two-col-layout" style="align-items:start">

        <!-- Categories list -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">All Categories</h2>
            </div>
            <?php if (empty($categories)): ?>
                <div class="empty-state"><p>No categories yet. Add one using the form.</p></div>
            <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:32px"></th>
                        <th>Name</th>
                        <th>Items</th>
                        <th style="width:120px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td>
                            <span class="colour-swatch" style="background:<?= h($cat['colour']) ?>"></span>
                        </td>
                        <td class="fw-medium"><?= h($cat['name']) ?></td>
                        <td class="text-muted"><?= (int)$cat['item_count'] ?></td>
                        <td>
                            <div class="action-btns">
                                <a href="/categories?edit=<?= (int)$cat['id'] ?>" class="btn btn-xs btn-secondary">Edit</a>
                                <form method="POST" action="/categories" style="display:inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action"  value="delete">
                                    <input type="hidden" name="cat_id" value="<?= (int)$cat['id'] ?>">
                                    <button type="submit" class="btn btn-xs btn-danger"
                                        data-confirm="Delete category '<?= h(addslashes($cat['name'])) ?>'? Items in this category will become uncategorised.">
                                        Del
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Add / Edit form -->
        <div class="card form-card">
            <?php if ($edit_cat): ?>
                <div class="card-header">
                    <h2 class="card-title">Edit Category</h2>
                    <a href="/categories" class="card-link">Cancel</a>
                </div>
                <form method="POST" action="/categories">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action"  value="edit">
                    <input type="hidden" name="cat_id" value="<?= (int)$edit_cat['id'] ?>">
                    <div class="form-group">
                        <label class="form-label" for="edit-name">Name</label>
                        <input type="text" id="edit-name" name="name" class="form-input" value="<?= h($edit_cat['name']) ?>" required autofocus>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit-colour">Colour</label>
                        <div style="display:flex; gap:0.5rem; align-items:center">
                            <input type="color" id="edit-colour" name="colour" class="form-input" value="<?= h($edit_cat['colour']) ?>" style="height:42px; width:80px; padding:4px 8px; flex-shrink:0;">
                            <input type="text"  id="edit-colour-text" class="form-input" value="<?= h($edit_cat['colour']) ?>" placeholder="#378ADD" pattern="^#[0-9A-Fa-f]{6}$" style="font-family:monospace">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="card-header">
                    <h2 class="card-title">Add Category</h2>
                </div>
                <form method="POST" action="/categories">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add">
                    <div class="form-group">
                        <label class="form-label" for="add-name">Name</label>
                        <input type="text" id="add-name" name="name" class="form-input" placeholder="e.g. Electronics" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add-colour">Colour</label>
                        <div style="display:flex; gap:0.5rem; align-items:center">
                            <input type="color" id="add-colour" name="colour" class="form-input" value="#378ADD" style="height:42px; width:80px; padding:4px 8px; flex-shrink:0;">
                            <input type="text"  id="add-colour-text" class="form-input" value="#378ADD" placeholder="#378ADD" pattern="^#[0-9A-Fa-f]{6}$" style="font-family:monospace">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Add Category</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

    </div>
</main>

<script>
// Sync colour picker ↔ text input
function syncColour(pickerId, textId) {
    var picker = document.getElementById(pickerId);
    var text   = document.getElementById(textId);
    if (!picker || !text) return;
    picker.addEventListener('input', function() { text.value = picker.value; });
    text.addEventListener('input', function() {
        if (/^#[0-9A-Fa-f]{6}$/.test(text.value)) picker.value = text.value;
    });
}
syncColour('add-colour', 'add-colour-text');
syncColour('edit-colour', 'edit-colour-text');
</script>

<?php require __DIR__ . '/../templates/footer.php'; ?>
