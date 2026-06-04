<?php
/** @var array $tenant */
$page_title = 'Return Item';
$pdo        = get_pdo();
$tid        = (int)$tenant['id'];
$user       = current_user();

// ── AJAX — lookup active loan by barcode ─────────────────────────────────────
if (($_GET['action'] ?? '') === 'barcode_lookup') {
    header('Content-Type: application/json');
    $barcode = trim($_GET['barcode'] ?? '');
    if ($barcode === '') { echo json_encode(['error' => 'No barcode provided']); exit; }

    $s = $pdo->prepare('SELECT id, name, barcode FROM items WHERE tenant_id = ? AND barcode = ? LIMIT 1');
    $s->execute([$tid, $barcode]);
    $item = $s->fetch();
    if (!$item) { echo json_encode(['error' => 'No item found for barcode: ' . $barcode]); exit; }

    $s = $pdo->prepare("
        SELECT l.id AS loan_id, l.quantity_loaned, l.checked_out_at, l.due_date,
               b.name AS borrower_name
        FROM loans l
        JOIN borrowers b ON b.id = l.borrower_id
        WHERE l.item_id = ? AND l.tenant_id = ? AND l.returned_at IS NULL
        ORDER BY l.checked_out_at DESC
        LIMIT 1
    ");
    $s->execute([$item['id'], $tid]);
    $loan = $s->fetch();
    if (!$loan) { echo json_encode(['error' => '"' . $item['name'] . '" has no active loan.']); exit; }

    echo json_encode([
        'loan_id'       => (int)$loan['loan_id'],
        'item_name'     => $item['name'],
        'borrower_name' => $loan['borrower_name'],
        'quantity'      => (int)$loan['quantity_loaned'],
        'checked_out'   => date('d M Y', strtotime($loan['checked_out_at'])),
        'due_date'      => $loan['due_date'] ? date('d M Y', strtotime($loan['due_date'])) : null,
    ]);
    exit;
}

// ── POST — process the return ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { flash_set('error', 'Invalid token.'); redirect('/loans/return'); }

    $loan_id = (int)($_POST['loan_id'] ?? 0);
    if (!$loan_id) { flash_set('error', 'No loan specified.'); redirect('/loans/return'); }

    $s = $pdo->prepare("
        SELECT l.*, i.name AS item_name
        FROM loans l JOIN items i ON i.id = l.item_id
        WHERE l.id = ? AND l.tenant_id = ? AND l.returned_at IS NULL
    ");
    $s->execute([$loan_id, $tid]);
    $loan = $s->fetch();

    if (!$loan) { flash_set('error', 'Loan not found or already returned.'); redirect('/loans/return'); }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE loans SET returned_at = NOW() WHERE id = ? AND tenant_id = ?')
            ->execute([$loan_id, $tid]);
        $pdo->prepare('UPDATE items SET quantity = quantity + ? WHERE id = ? AND tenant_id = ?')
            ->execute([$loan['quantity_loaned'], $loan['item_id'], $tid]);
        $pdo->commit();
        log_activity($tid, (int)$user['id'], 'return',
            'Returned ' . $loan['quantity_loaned'] . 'x "' . $loan['item_name'] . '"');
        flash_set('success', '"' . $loan['item_name'] . '" marked as returned.');
    } catch (Exception $e) {
        $pdo->rollBack();
        flash_set('error', 'Failed to process return. Please try again.');
    }
    redirect('/loans');
}

require __DIR__ . '/../templates/header.php';
require __DIR__ . '/../templates/sidebar.php';
?>

<main class="main-content">
    <div class="topbar">
        <div class="topbar-title">
            <a href="/loans" class="back-link"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px"><polyline points="15 18 9 12 15 6"/></svg>Active Loans</a>
            <h1>Return Item</h1>
        </div>
    </div>

    <?= flash_html() ?>

    <div class="card form-card" style="max-width:520px; margin:0 auto">
        <h2 class="card-section-title">Scan Item to Return</h2>
        <p class="text-muted" style="margin-bottom:1.25rem; font-size:0.875rem">Point the camera at the item's barcode to find its active loan.</p>

        <button type="button" class="btn btn-primary" id="return-scan-btn" style="width:100%; padding:0.875rem; font-size:1rem; margin-bottom:1rem">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
            Scan Barcode
        </button>

        <!-- Scanner modal -->
        <div id="return-scanner-modal" class="modal-overlay" style="display:none">
            <div class="modal-box" style="max-width:380px; width:100%">
                <h3 class="modal-title">Scan Item Barcode</h3>
                <div id="return-scanner-container" style="width:100%; border-radius:8px; overflow:hidden; background:#000; min-height:200px"></div>
                <p id="return-scanner-status" style="text-align:center; margin-top:0.75rem; font-size:0.85rem; color:#6B7280">Starting...</p>
                <div class="modal-actions">
                    <button class="btn btn-secondary" id="return-scanner-cancel">Cancel</button>
                </div>
            </div>
        </div>

        <!-- Error -->
        <div id="return-error" style="display:none; color:#dc2626; font-size:0.875rem; margin-bottom:1rem"></div>

        <!-- Loan details after scan -->
        <div id="return-result" style="display:none">
            <div style="background:var(--bg-subtle,#f9fafb); border:1px solid var(--border); border-radius:8px; padding:1rem; margin-bottom:1.25rem">
                <div style="font-weight:600; font-size:1rem; margin-bottom:0.5rem" id="return-item-name"></div>
                <div class="text-muted" style="font-size:0.875rem">
                    Borrowed by <strong id="return-borrower"></strong><br>
                    <span id="return-qty"></span> · Checked out <span id="return-date"></span>
                    <span id="return-due-wrap"> · Due <span id="return-due"></span></span>
                </div>
            </div>
            <form method="POST" action="/loans/return" id="return-form">
                <?= csrf_field() ?>
                <input type="hidden" name="loan_id" id="return-loan-id">
                <div style="display:flex; gap:0.75rem">
                    <button type="button" class="btn btn-ghost" id="return-rescan">Scan again</button>
                    <button type="submit" class="btn btn-primary" style="flex:1">Confirm Return</button>
                </div>
            </form>
        </div>
    </div>
</main>

<script src="/assets/js/scanner.js"></script>
<script>
(function() {
    var scanBtn   = document.getElementById('return-scan-btn');
    var rescanBtn = document.getElementById('return-rescan');
    var cancelBtn = document.getElementById('return-scanner-cancel');
    var modal     = document.getElementById('return-scanner-modal');
    var statusEl  = document.getElementById('return-scanner-status');
    var errorBox  = document.getElementById('return-error');
    var resultBox = document.getElementById('return-result');
    var scanner   = null;

    function closeScanner() {
        if (scanner) { scanner.stop(); scanner = null; }
        modal.style.display = 'none';
        statusEl.textContent = 'Starting...';
    }

    function openScanner() {
        errorBox.style.display = 'none';
        resultBox.style.display = 'none';
        modal.style.display = 'flex';
        scanner = new LVScanner('return-scanner-container',
            function(barcode) {
                closeScanner();
                fetch('/loans/return?action=barcode_lookup&barcode=' + encodeURIComponent(barcode))
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.error) {
                            errorBox.textContent = data.error;
                            errorBox.style.display = 'block';
                            return;
                        }
                        document.getElementById('return-loan-id').textContent  = data.loan_id;
                        document.getElementById('return-loan-id').value        = data.loan_id;
                        document.getElementById('return-item-name').textContent = data.item_name;
                        document.getElementById('return-borrower').textContent  = data.borrower_name;
                        document.getElementById('return-qty').textContent       = data.quantity + ' item(s)';
                        document.getElementById('return-date').textContent      = data.checked_out;
                        var dueWrap = document.getElementById('return-due-wrap');
                        if (data.due_date) {
                            document.getElementById('return-due').textContent = data.due_date;
                            dueWrap.style.display = '';
                        } else {
                            dueWrap.style.display = 'none';
                        }
                        resultBox.style.display = 'block';
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

<?php require __DIR__ . '/../templates/footer.php'; ?>
