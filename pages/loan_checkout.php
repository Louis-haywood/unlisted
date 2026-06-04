<?php
/** @var array $tenant */
$page_title = 'Check Out';
$pdo        = get_pdo();
$tid        = (int)$tenant['id'];
$user       = current_user();

// Initialise checkout session bucket
if (!isset($_SESSION['checkout'])) $_SESSION['checkout'] = [];

$step        = (int)($_POST['step'] ?? ($_GET['step'] ?? 1));
$posted_step = $step; // remember what was actually submitted — $step may change during processing
$errors      = [];

// ── AJAX — Barcode lookup for express checkout ────────────────────────────────
if (($_GET['action'] ?? '') === 'barcode_lookup') {
    header('Content-Type: application/json');
    $barcode = trim($_GET['barcode'] ?? '');
    if ($barcode === '') { echo json_encode(['error' => 'No barcode provided']); exit; }
    $s = $pdo->prepare('SELECT id, name, quantity, barcode FROM items WHERE tenant_id = ? AND barcode = ? LIMIT 1');
    $s->execute([$tid, $barcode]);
    $row = $s->fetch();
    if (!$row) { echo json_encode(['error' => 'Item not found for barcode: ' . $barcode]); exit; }
    if ((int)$row['quantity'] <= 0) { echo json_encode(['error' => 'No stock available for "' . $row['name'] . '"']); exit; }
    echo json_encode(['id' => (int)$row['id'], 'name' => $row['name'], 'quantity' => (int)$row['quantity']]);
    exit;
}

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
if ($posted_step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
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
if ($posted_step === 3 && $_SERVER['REQUEST_METHOD'] === 'POST') {
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
if ($posted_step === 4 && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['confirm'])) {
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
            <?php if ($step > 1): ?>
                <a href="/loans/checkout" class="back-link">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px"><polyline points="15 18 9 12 15 6"/></svg>Start Over
                </a>
            <?php endif; ?>
            <h1>Check Out</h1>
        </div>
        <div class="topbar-actions" style="align-items:center">
            <span style="font-size:0.8rem; color:var(--text-muted)">Step <?= $step ?> of 4</span>
        </div>
    </div>

    <!-- Progress bar -->
    <div style="height:3px; background:var(--border)">
        <div style="height:3px; background:var(--blue); width:<?= ($step / 4 * 100) ?>%; transition:width 0.3s ease"></div>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-error" style="margin:1rem 1rem 0">
            <?php foreach ($errors as $e): ?><?= h($e) ?><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="co-wrap">

    <?php if ($step === 1): ?>

        <div class="co-section">
            <p class="co-label">Scan the item barcode</p>
            <button type="button" class="btn btn-primary co-scan-btn" id="express-scan-btn">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                Scan Barcode
            </button>
            <div id="express-error" class="co-error" style="display:none"></div>
            <div id="express-result" class="co-found" style="display:none">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--blue);flex-shrink:0"><polyline points="20 6 9 17 4 12"/></svg>
                <div style="flex:1; min-width:0">
                    <div style="font-weight:600" id="express-item-name"></div>
                    <div style="font-size:0.8rem; color:var(--text-muted)" id="express-item-stock"></div>
                </div>
                <button type="button" class="btn btn-ghost btn-xs" id="express-rescan">Rescan</button>
            </div>
            <form method="POST" action="/loans/checkout" id="express-form" style="display:none">
                <?= csrf_field() ?>
                <input type="hidden" name="step" value="1">
                <input type="hidden" name="item_id" id="express-item-id">
            </form>
        </div>

        <div class="co-divider">or search by name</div>

        <div class="co-section">
            <form method="POST" action="/loans/checkout">
                <?= csrf_field() ?>
                <input type="hidden" name="step" value="1">
                <div style="display:flex; gap:0.5rem">
                    <input type="text" id="item-search-input" name="item_search" class="form-input" placeholder="Item name or barcode…" autocomplete="off" style="flex:1">
                    <button type="submit" class="btn btn-secondary">Search</button>
                </div>
            </form>
        </div>

    <?php elseif ($step === 2 && $checkout_item): ?>

        <!-- Item banner -->
        <div class="co-item-banner">
            <?php if ($checkout_item['photo_path']): ?>
                <img src="/uploads/<?= h($checkout_item['photo_path']) ?>" class="co-item-photo" alt="">
            <?php else: ?>
                <div class="co-item-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                </div>
            <?php endif; ?>
            <div>
                <div class="co-item-name"><?= h($checkout_item['name']) ?></div>
                <div class="co-item-meta">
                    <?php if ($checkout_item['category_name']): ?><?= h($checkout_item['category_name']) ?> · <?php endif; ?>
                    <?= (int)$checkout_item['quantity'] ?> in stock
                </div>
            </div>
        </div>

        <div class="co-section">
            <p class="co-label">Who is borrowing this?</p>
            <form method="POST" action="/loans/checkout" id="borrower-form">
                <?= csrf_field() ?>
                <input type="hidden" name="step" value="2">

                <div class="co-tabs" id="borrower-mode-tabs">
                    <button type="button" class="co-tab active" data-target="existing-section" data-value="existing">Existing</button>
                    <button type="button" class="co-tab" data-target="new-section" data-value="new">New borrower</button>
                </div>
                <input type="hidden" name="borrower_mode" id="borrower-mode-value" value="existing">

                <div id="existing-section">
                    <?php if (empty($borrowers_list)): ?>
                        <p class="text-muted" style="margin:1rem 0 0.5rem">No borrowers yet — switch to "New borrower".</p>
                    <?php else: ?>
                        <select name="borrower_id" id="borrower_id" class="form-input form-select" style="margin-top:0.75rem">
                            <option value="">— Select borrower —</option>
                            <?php foreach ($borrowers_list as $b): ?>
                                <option value="<?= (int)$b['id'] ?>"><?= h($b['name']) ?><?= $b['email'] ? ' — ' . h($b['email']) : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>

                <div id="new-section" style="display:none; margin-top:0.75rem">
                    <div class="form-group">
                        <label class="form-label" for="b_name">Name <span class="required">*</span></label>
                        <input type="text" id="b_name" name="b_name" class="form-input" placeholder="Full name" autocomplete="name">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="b_email">Email</label>
                        <input type="email" id="b_email" name="b_email" class="form-input" placeholder="email@example.com" autocomplete="email">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="b_phone">Phone</label>
                        <input type="tel" id="b_phone" name="b_phone" class="form-input" placeholder="+44 7000 000000" autocomplete="tel">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary co-submit-btn">Continue</button>
            </form>
        </div>

    <?php elseif ($step === 3 && $checkout_item): ?>

        <!-- Item + borrower banner -->
        <div class="co-item-banner">
            <?php if ($checkout_item['photo_path']): ?>
                <img src="/uploads/<?= h($checkout_item['photo_path']) ?>" class="co-item-photo" alt="">
            <?php else: ?>
                <div class="co-item-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                </div>
            <?php endif; ?>
            <div>
                <div class="co-item-name"><?= h($checkout_item['name']) ?></div>
                <div class="co-item-meta">
                    <?php if ($checkout_borrower): ?>Borrower: <strong><?= h($checkout_borrower['name']) ?></strong><?php endif; ?>
                </div>
            </div>
        </div>

        <div class="co-section">
            <p class="co-label">Loan details</p>
            <form method="POST" action="/loans/checkout">
                <?= csrf_field() ?>
                <input type="hidden" name="step" value="3">
                <div class="form-group">
                    <label class="form-label" for="quantity_loaned">Quantity</label>
                    <input type="number" id="quantity_loaned" name="quantity_loaned" class="form-input"
                        min="1" max="<?= (int)$checkout_item['quantity'] ?>" value="1" required>
                    <span class="form-hint">Max available: <?= (int)$checkout_item['quantity'] ?></span>
                </div>
                <div class="form-group">
                    <label class="form-label" for="due_date">Due date <span style="font-weight:400; color:var(--text-muted)">(optional)</span></label>
                    <input type="date" id="due_date" name="due_date" class="form-input" min="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="notes">Notes <span style="font-weight:400; color:var(--text-muted)">(optional)</span></label>
                    <textarea id="notes" name="notes" class="form-input form-textarea" rows="2" placeholder="Any notes…"></textarea>
                </div>
                <button type="submit" class="btn btn-primary co-submit-btn">Review</button>
            </form>
        </div>

    <?php elseif ($step === 4 && $checkout_item && $checkout_borrower): ?>

        <!-- Confirmation -->
        <div class="co-item-banner">
            <?php if ($checkout_item['photo_path']): ?>
                <img src="/uploads/<?= h($checkout_item['photo_path']) ?>" class="co-item-photo" alt="">
            <?php else: ?>
                <div class="co-item-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                </div>
            <?php endif; ?>
            <div>
                <div class="co-item-name"><?= h($checkout_item['name']) ?></div>
                <div class="co-item-meta">to <strong><?= h($checkout_borrower['name']) ?></strong></div>
            </div>
        </div>

        <div class="co-section">
            <p class="co-label">Confirm details</p>
            <div class="co-summary">
                <div class="co-summary-row">
                    <span>Quantity</span>
                    <strong><?= (int)$_SESSION['checkout']['quantity_loaned'] ?></strong>
                </div>
                <div class="co-summary-row">
                    <span>Due date</span>
                    <strong><?= $_SESSION['checkout']['due_date'] ? h(date('d M Y', strtotime($_SESSION['checkout']['due_date']))) : 'No due date' ?></strong>
                </div>
                <?php if ($_SESSION['checkout']['notes']): ?>
                <div class="co-summary-row">
                    <span>Notes</span>
                    <strong><?= h($_SESSION['checkout']['notes']) ?></strong>
                </div>
                <?php endif; ?>
            </div>
            <form method="POST" action="/loans/checkout">
                <?= csrf_field() ?>
                <input type="hidden" name="step"    value="4">
                <input type="hidden" name="confirm" value="1">
                <button type="submit" class="btn btn-primary co-submit-btn">Confirm Check Out</button>
            </form>
            <a href="/loans/checkout?step=3" class="co-back-link">Edit details</a>
        </div>

    <?php else: ?>
        <div class="co-section">
            <p class="text-muted">Something went wrong. <a href="/loans/checkout">Start over</a>.</p>
        </div>
    <?php endif; ?>

    </div>
</main>

<!-- Barcode scanner modal -->
<div id="express-scanner-modal" class="modal-overlay" style="display:none">
    <div class="modal-box" style="max-width:380px; width:100%">
        <h3 class="modal-title">Scan Item Barcode</h3>
        <div id="express-scanner-container" style="width:100%; border-radius:8px; overflow:hidden; background:#000; min-height:200px"></div>
        <p id="express-scanner-status" style="text-align:center; margin-top:0.75rem; font-size:0.85rem; color:#6B7280">Starting...</p>
        <div class="modal-actions">
            <button class="btn btn-secondary" id="express-scanner-cancel">Cancel</button>
        </div>
    </div>
</div>

<script src="/assets/js/scanner.js?v=5"></script>
<script>
(function() {
    var scanBtn   = document.getElementById('express-scan-btn');
    var rescanBtn = document.getElementById('express-rescan');
    var cancelBtn = document.getElementById('express-scanner-cancel');
    var modal     = document.getElementById('express-scanner-modal');
    var statusEl  = document.getElementById('express-scanner-status');
    var resultBox = document.getElementById('express-result');
    var errorBox  = document.getElementById('express-error');
    var itemName  = document.getElementById('express-item-name');
    var itemStock = document.getElementById('express-item-stock');
    var itemIdIn  = document.getElementById('express-item-id');
    var form      = document.getElementById('express-form');
    if (!scanBtn) return;

    var scanner = null;

    function closeScanner() {
        if (scanner) { scanner.stop(); scanner = null; }
        modal.style.display = 'none';
        statusEl.textContent = 'Starting...';
    }

    function openScanner() {
        errorBox.style.display = 'none';
        resultBox.style.display = 'none';
        modal.style.display = 'flex';
        scanner = new LVScanner('express-scanner-container',
            function(barcode) {
                closeScanner();
                fetch('/loans/checkout?action=barcode_lookup&barcode=' + encodeURIComponent(barcode))
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.error) {
                            errorBox.textContent = data.error;
                            errorBox.style.display = 'block';
                            return;
                        }
                        itemIdIn.value = data.id;
                        itemName.textContent = data.name;
                        itemStock.textContent = data.quantity + ' in stock';
                        resultBox.style.display = 'block';
                        setTimeout(function() { form.submit(); }, 800);
                    })
                    .catch(function() {
                        errorBox.textContent = 'Network error — please try again.';
                        errorBox.style.display = 'block';
                    });
            },
            function(msg) { statusEl.textContent = msg; }
        );
        scanner.start();
    }

    scanBtn.addEventListener('click', openScanner);
    if (rescanBtn) rescanBtn.addEventListener('click', openScanner);
    cancelBtn.addEventListener('click', closeScanner);
})();
</script>

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
    var tabs = document.querySelectorAll('.co-tab');
    if (!tabs.length) return;
    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            tabs.forEach(function(t) { t.classList.remove('active'); });
            this.classList.add('active');
            document.getElementById('existing-section').style.display = this.dataset.value === 'existing' ? 'block' : 'none';
            document.getElementById('new-section').style.display      = this.dataset.value === 'new'      ? 'block' : 'none';
            document.getElementById('borrower-mode-value').value = this.dataset.value;
        });
    });
})();
</script>

<?php require __DIR__ . '/../templates/footer.php'; ?>
