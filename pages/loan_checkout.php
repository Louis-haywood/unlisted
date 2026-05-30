<?php
$page_title = 'Check Out';
$pdo        = get_pdo();
$tid        = (int)$tenant['id'];
$user       = current_user();

// Initialise checkout session bucket
if (!isset($_SESSION['checkout'])) $_SESSION['checkout'] = [];

$step   = (int)($_POST['step'] ?? ($_GET['step'] ?? 1));
$errors = [];

// ── STEP 1 — Item search ──────────────────────────────────────────────────────
if ($step === 1 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { $errors[] = 'Invalid token.'; $step = 1; }
    else {
        $item_id = (int)($_POST['item_id'] ?? 0);
        if (!$item_id) {
            // Try searching by name or barcode
            $q = trim($_POST['item_search'] ?? '');
            if ($q !== '') {
                $s = $pdo->prepare('SELECT id FROM items WHERE tenant_id = ? AND (name LIKE ? OR barcode = ?) LIMIT 1');
                $s->execute([$tid, '%' . $q . '%', $q]);
                $found = $s->fetch();
                $item_id = $found ? (int)$found['id'] : 0;
            }
        }
        if (!$item_id) { $errors[] = 'Item not found.'; }
        else {
            $s = $pdo->prepare('SELECT * FROM items WHERE id = ? AND tenant_id = ?');
            $s->execute([$item_id, $tid]);
            $item = $s->fetch();
            if (!$item) { $errors[] = 'Item not found.'; }
            elseif ((int)$item['quantity'] <= 0) { $errors[] = 'No stock available for this item.'; }
            else {
                $_SESSION['checkout']['item_id'] = $item_id;
                $step = 2;
            }
        }
    }
}

// Pre-fill item from query string (e.g. from items list)
if ($step === 1 && isset($_GET['item_id'])) {
    $item_id_pre = (int)$_GET['item_id'];
    $s = $pdo->prepare('SELECT * FROM items WHERE id = ? AND tenant_id = ?');
    $s->execute([$item_id_pre, $tid]);
    $pre_item = $s->fetch();
    if ($pre_item && (int)$pre_item['quantity'] > 0) {
        $_SESSION['checkout']['item_id'] = $item_id_pre;
        $step = 2;
    }
}

// ── STEP 2 — Borrower selection ───────────────────────────────────────────────
if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { $errors[] = 'Invalid token.'; }
    else {
        $borrower_mode = $_POST['borrower_mode'] ?? 'existing';
        $borrower_id   = 0;

        if ($borrower_mode === 'new') {
            $b_name    = trim($_POST['b_name']    ?? '');
            $b_email   = trim($_POST['b_email']   ?? '');
            $b_phone   = trim($_POST['b_phone']   ?? '');
            $b_address = trim($_POST['b_address'] ?? '');
            if ($b_name === '') $errors[] = 'Borrower name is required.';
            if (empty($errors)) {
                $s = $pdo->prepare('INSERT INTO borrowers (tenant_id, name, email, phone, address) VALUES (?, ?, ?, ?, ?)');
                $s->execute([$tid, $b_name, $b_email, $b_phone, $b_address]);
                $borrower_id = (int)$pdo->lastInsertId();
                log_activity($tid, (int)$user['id'], 'borrower_added', 'Added borrower: ' . $b_name);
            }
        } else {
            $borrower_id = (int)($_POST['borrower_id'] ?? 0);
            if (!$borrower_id) $errors[] = 'Please select a borrower.';
            else {
                $s = $pdo->prepare('SELECT id FROM borrowers WHERE id = ? AND tenant_id = ?');
                $s->execute([$borrower_id, $tid]);
                if (!$s->fetch()) { $errors[] = 'Borrower not found.'; $borrower_id = 0; }
            }
        }

        if (empty($errors) && $borrower_id) {
            $_SESSION['checkout']['borrower_id'] = $borrower_id;
            $step = 3;
        }
    }
}

// ── STEP 3 — Loan details ─────────────────────────────────────────────────────
if ($step === 3 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { $errors[] = 'Invalid token.'; }
    else {
        $qty      = (int)($_POST['quantity_loaned'] ?? 1);
        $due_date = trim($_POST['due_date'] ?? '');
        $notes    = trim($_POST['notes']    ?? '');

        // Load item to check available quantity
        $item_id_s = (int)($_SESSION['checkout']['item_id'] ?? 0);
        $s = $pdo->prepare('SELECT quantity FROM items WHERE id = ? AND tenant_id = ?');
        $s->execute([$item_id_s, $tid]);
        $item_row = $s->fetch();

        if ($qty < 1) $errors[] = 'Quantity must be at least 1.';
        if ($item_row && $qty > (int)$item_row['quantity']) $errors[] = 'Only ' . $item_row['quantity'] . ' available.';

        if (empty($errors)) {
            $_SESSION['checkout']['quantity_loaned'] = $qty;
            $_SESSION['checkout']['due_date']        = $due_date ?: null;
            $_SESSION['checkout']['notes']           = $notes;
            $step = 4;
        }
    }
}

// ── STEP 4 — Confirm and process ─────────────────────────────────────────────
if ($step === 4 && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['confirm'])) {
    if (!csrf_verify()) { $errors[] = 'Invalid token.'; }
    else {
        $co = $_SESSION['checkout'];
        $item_id_co     = (int)($co['item_id']        ?? 0);
        $borrower_id_co = (int)($co['borrower_id']    ?? 0);
        $qty_co         = (int)($co['quantity_loaned'] ?? 1);
        $due_date_co    = $co['due_date'] ?? null;
        $notes_co       = $co['notes']   ?? null;

        if (!$item_id_co || !$borrower_id_co) { $errors[] = 'Session data missing. Please start over.'; $step = 1; }
        else {
            // Double-check stock
            $s = $pdo->prepare('SELECT quantity, name FROM items WHERE id = ? AND tenant_id = ?');
            $s->execute([$item_id_co, $tid]);
            $item_co = $s->fetch();
            if (!$item_co || (int)$item_co['quantity'] < $qty_co) {
                $errors[] = 'Insufficient stock.'; $step = 1;
            } else {
                // Create loan
                $pdo->beginTransaction();
                try {
                    $s = $pdo->prepare("
                        INSERT INTO loans (tenant_id, item_id, borrower_id, quantity_loaned, due_date, notes)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $s->execute([$tid, $item_id_co, $borrower_id_co, $qty_co, $due_date_co, $notes_co]);

                    // Decrement quantity
                    $pdo->prepare('UPDATE items SET quantity = quantity - ? WHERE id = ? AND tenant_id = ?')
                        ->execute([$qty_co, $item_id_co, $tid]);

                    $pdo->commit();

                    // Log
                    $s = $pdo->prepare('SELECT name FROM borrowers WHERE id = ?');
                    $s->execute([$borrower_id_co]);
                    $brow = $s->fetch();
                    log_activity($tid, (int)$user['id'], 'checkout',
                        'Checked out ' . $qty_co . 'x "' . $item_co['name'] . '" to ' . ($brow['name'] ?? 'borrower'));

                    unset($_SESSION['checkout']);
                    flash_set('success', 'Checked out ' . $qty_co . 'x "' . $item_co['name'] . '" successfully.');
                    redirect('/loans');
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $errors[] = 'Database error. Please try again.';
                }
            }
        }
    }
}

// ── Load data for current step display ────────────────────────────────────────
$checkout_item     = null;
$checkout_borrower = null;

$item_id_sess = (int)($_SESSION['checkout']['item_id'] ?? 0);
if ($item_id_sess) {
    $s = $pdo->prepare('SELECT i.*, c.name AS category_name FROM items i LEFT JOIN categories c ON c.id = i.category_id WHERE i.id = ? AND i.tenant_id = ?');
    $s->execute([$item_id_sess, $tid]);
    $checkout_item = $s->fetch();
}

$borrower_id_sess = (int)($_SESSION['checkout']['borrower_id'] ?? 0);
if ($borrower_id_sess) {
    $s = $pdo->prepare('SELECT * FROM borrowers WHERE id = ? AND tenant_id = ?');
    $s->execute([$borrower_id_sess, $tid]);
    $checkout_borrower = $s->fetch();
}

// Borrowers list for step 2 dropdown
$borrowers_list = [];
if ($step === 2) {
    $s = $pdo->prepare('SELECT id, name, email FROM borrowers WHERE tenant_id = ? ORDER BY name');
    $s->execute([$tid]);
    $borrowers_list = $s->fetchAll();
}

require __DIR__ . '/../templates/header.php';
require __DIR__ . '/../templates/sidebar.php';
?>

<main class="main-content">
    <div class="topbar">
        <div class="topbar-title">
            <h1>Check Out</h1>
        </div>
        <?php if ($step > 1): ?>
        <div class="topbar-actions">
            <a href="/loans/checkout" class="btn btn-ghost">Start Over</a>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-error">
            <ul class="error-list"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <!-- Step indicator -->
    <div class="step-indicator">
        <?php $steps = ['Find Item', 'Select Borrower', 'Loan Details', 'Confirm']; ?>
        <?php foreach ($steps as $i => $label): ?>
            <?php $n = $i + 1; $cls = $n < $step ? 'done' : ($n === $step ? 'active' : ''); ?>
            <div class="step <?= $cls ?>">
                <div class="step-num"><?= $n < $step ? '✓' : $n ?></div>
                <div class="step-label"><?= $label ?></div>
            </div>
            <?php if ($i < count($steps) - 1): ?><div class="step-line"></div><?php endif; ?>
        <?php endforeach; ?>
    </div>

    <div class="card form-card" style="max-width:640px; margin:0 auto">

    <?php if ($step === 1): ?>
        <!-- Step 1: Item search -->
        <h2 class="card-section-title">Find an Item</h2>
        <form method="POST" action="/loans/checkout">
            <?= csrf_field() ?>
            <input type="hidden" name="step" value="1">
            <div class="form-group">
                <label class="form-label" for="item-search-input">Search by name or scan barcode</label>
                <input
                    type="text"
                    id="item-search-input"
                    name="item_search"
                    class="form-input"
                    placeholder="Item name or barcode…"
                    autofocus
                    autocomplete="off"
                >
                <span class="form-hint">Barcode scanners are supported — scan and it will submit automatically.</span>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Find Item →</button>
            </div>
        </form>

    <?php elseif ($step === 2 && $checkout_item): ?>
        <!-- Step 2: Borrower selection -->
        <div class="checkout-item-summary">
            <div class="checkout-item-info">
                <?php if ($checkout_item['photo_path']): ?>
                    <img src="/uploads/<?= h($checkout_item['photo_path']) ?>" class="checkout-item-thumb" alt="">
                <?php endif; ?>
                <div>
                    <strong><?= h($checkout_item['name']) ?></strong>
                    <?php if ($checkout_item['category_name']): ?>
                        <span class="text-muted"> · <?= h($checkout_item['category_name']) ?></span>
                    <?php endif; ?>
                    <br><span class="text-muted"><?= (int)$checkout_item['quantity'] ?> in stock</span>
                </div>
            </div>
        </div>

        <h2 class="card-section-title" style="margin-top:1.5rem">Select Borrower</h2>
        <form method="POST" action="/loans/checkout" id="borrower-form">
            <?= csrf_field() ?>
            <input type="hidden" name="step" value="2">

            <div class="form-group">
                <div class="radio-tabs" id="borrower-mode-tabs">
                    <label class="radio-tab active" id="tab-existing">
                        <input type="radio" name="borrower_mode" value="existing" checked> Existing borrower
                    </label>
                    <label class="radio-tab" id="tab-new">
                        <input type="radio" name="borrower_mode" value="new"> New borrower
                    </label>
                </div>
            </div>

            <div id="existing-section">
                <?php if (empty($borrowers_list)): ?>
                    <p class="text-muted" style="margin-bottom:1rem">No borrowers yet — create one using the "New borrower" tab.</p>
                <?php else: ?>
                <div class="form-group">
                    <label class="form-label" for="borrower_id">Borrower</label>
                    <select name="borrower_id" id="borrower_id" class="form-input form-select">
                        <option value="">— Select —</option>
                        <?php foreach ($borrowers_list as $b): ?>
                            <option value="<?= (int)$b['id'] ?>"><?= h($b['name']) ?><?= $b['email'] ? ' (' . h($b['email']) . ')' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>

            <div id="new-section" style="display:none">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="b_name">Name <span class="required">*</span></label>
                        <input type="text" id="b_name" name="b_name" class="form-input" placeholder="Full name">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="b_email">Email</label>
                        <input type="email" id="b_email" name="b_email" class="form-input" placeholder="email@example.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="b_phone">Phone</label>
                        <input type="tel" id="b_phone" name="b_phone" class="form-input" placeholder="+44 7000 000000">
                    </div>
                    <div class="form-group form-col-full">
                        <label class="form-label" for="b_address">Address</label>
                        <textarea id="b_address" name="b_address" class="form-input form-textarea" rows="2" placeholder="Optional"></textarea>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Next →</button>
            </div>
        </form>

    <?php elseif ($step === 3 && $checkout_item): ?>
        <!-- Step 3: Loan details -->
        <div class="checkout-item-summary">
            <div class="checkout-item-info">
                <?php if ($checkout_item['photo_path']): ?>
                    <img src="/uploads/<?= h($checkout_item['photo_path']) ?>" class="checkout-item-thumb" alt="">
                <?php endif; ?>
                <div>
                    <strong><?= h($checkout_item['name']) ?></strong>
                    <br><span class="text-muted"><?= (int)$checkout_item['quantity'] ?> in stock</span>
                </div>
            </div>
            <?php if ($checkout_borrower): ?>
            <div class="checkout-borrower-info">
                Going to: <strong><?= h($checkout_borrower['name']) ?></strong>
            </div>
            <?php endif; ?>
        </div>

        <h2 class="card-section-title" style="margin-top:1.5rem">Loan Details</h2>
        <form method="POST" action="/loans/checkout">
            <?= csrf_field() ?>
            <input type="hidden" name="step" value="3">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="quantity_loaned">Quantity to loan <span class="required">*</span></label>
                    <input type="number" id="quantity_loaned" name="quantity_loaned" class="form-input"
                        min="1" max="<?= (int)$checkout_item['quantity'] ?>" value="1" required>
                    <span class="form-hint">Max: <?= (int)$checkout_item['quantity'] ?></span>
                </div>
                <div class="form-group">
                    <label class="form-label" for="due_date">Due date (optional)</label>
                    <input type="date" id="due_date" name="due_date" class="form-input" min="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group form-col-full">
                    <label class="form-label" for="notes">Notes (optional)</label>
                    <textarea id="notes" name="notes" class="form-input form-textarea" rows="3" placeholder="Any additional notes…"></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Review →</button>
            </div>
        </form>

    <?php elseif ($step === 4 && $checkout_item && $checkout_borrower): ?>
        <!-- Step 4: Confirmation -->
        <h2 class="card-section-title">Confirm Checkout</h2>
        <div class="confirm-summary">
            <div class="confirm-row">
                <span class="confirm-label">Item</span>
                <span class="confirm-value"><?= h($checkout_item['name']) ?></span>
            </div>
            <div class="confirm-row">
                <span class="confirm-label">Borrower</span>
                <span class="confirm-value"><?= h($checkout_borrower['name']) ?></span>
            </div>
            <div class="confirm-row">
                <span class="confirm-label">Quantity</span>
                <span class="confirm-value"><?= (int)$_SESSION['checkout']['quantity_loaned'] ?></span>
            </div>
            <div class="confirm-row">
                <span class="confirm-label">Due Date</span>
                <span class="confirm-value">
                    <?= $_SESSION['checkout']['due_date'] ? h(date('d M Y', strtotime($_SESSION['checkout']['due_date']))) : '— No due date —' ?>
                </span>
            </div>
            <?php if ($_SESSION['checkout']['notes']): ?>
            <div class="confirm-row">
                <span class="confirm-label">Notes</span>
                <span class="confirm-value"><?= h($_SESSION['checkout']['notes']) ?></span>
            </div>
            <?php endif; ?>
        </div>
        <form method="POST" action="/loans/checkout">
            <?= csrf_field() ?>
            <input type="hidden" name="step"    value="4">
            <input type="hidden" name="confirm" value="1">
            <div class="form-actions">
                <a href="/loans/checkout?step=3" class="btn btn-ghost">Back</a>
                <button type="submit" class="btn btn-primary">Confirm Check Out</button>
            </div>
        </form>

    <?php else: ?>
        <p class="text-muted">Something went wrong. <a href="/loans/checkout">Start over</a>.</p>
    <?php endif; ?>

    </div>
</main>

<script>
// Barcode scanner detection on step 1 search field
(function() {
    var input = document.getElementById('item-search-input');
    if (!input) return;
    var chars = [], lastTime = 0;
    input.addEventListener('keydown', function(e) {
        var now = Date.now();
        if (e.key === 'Enter') {
            if (chars.length >= 4) input.form.submit();
            chars = []; return;
        }
        if (now - lastTime < 50 && e.key.length === 1) chars.push(e.key);
        else chars = [e.key];
        lastTime = now;
    });
})();

// Borrower mode tabs (step 2)
(function() {
    var radios = document.querySelectorAll('[name=borrower_mode]');
    if (!radios.length) return;
    var existing = document.getElementById('existing-section');
    var newSec   = document.getElementById('new-section');
    var tabs     = document.querySelectorAll('.radio-tab');

    radios.forEach(function(r) {
        r.addEventListener('change', function() {
            var isNew = this.value === 'new';
            if (existing) existing.style.display = isNew ? 'none' : 'block';
            if (newSec)   newSec.style.display   = isNew ? 'block' : 'none';
            tabs.forEach(function(t) { t.classList.remove('active'); });
            this.parentElement.classList.add('active');
        });
    });
})();
</script>

<?php require __DIR__ . '/../templates/footer.php'; ?>
