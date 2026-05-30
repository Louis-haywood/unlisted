<?php
$pdo = get_pdo();

// ── POST actions ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_SESSION['admin_csrf'])) $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['admin_csrf'], $token)) {
        flash_set('error', 'Invalid token.'); redirect('/admin/');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create_tenant') {
        $t_name      = trim($_POST['t_name']      ?? '');
        $t_sub       = strtolower(trim($_POST['t_subdomain'] ?? ''));
        $t_plan      = in_array($_POST['t_plan'] ?? '', ['free','pro']) ? $_POST['t_plan'] : 'free';
        $t_limit     = max(1, (int)($_POST['t_item_limit'] ?? 100));
        $u_name      = trim($_POST['u_name']      ?? '');
        $u_email     = trim($_POST['u_email']     ?? '');
        $u_password  = trim($_POST['u_password']  ?? '');

        $errs = [];
        if (!$t_name)    $errs[] = 'Tenant name required.';
        if (!$t_sub)     $errs[] = 'Subdomain required.';
        if (!preg_match('/^[a-z0-9\-]+$/', $t_sub)) $errs[] = 'Subdomain: lowercase letters, numbers, hyphens only.';
        if (!$u_name)    $errs[] = 'Admin user name required.';
        if (!$u_email)   $errs[] = 'Admin user email required.';
        if (strlen($u_password) < 8) $errs[] = 'Password must be at least 8 characters.';

        if (empty($errs)) {
            // Check subdomain unique
            $s = $pdo->prepare('SELECT id FROM tenants WHERE subdomain = ?');
            $s->execute([$t_sub]);
            if ($s->fetch()) $errs[] = 'Subdomain already taken.';
        }

        if (empty($errs)) {
            $pdo->beginTransaction();
            try {
                $pdo->prepare('INSERT INTO tenants (name, subdomain, plan, item_limit) VALUES (?, ?, ?, ?)')
                    ->execute([$t_name, $t_sub, $t_plan, $t_limit]);
                $tenant_id = (int)$pdo->lastInsertId();

                $pdo->prepare('INSERT INTO users (tenant_id, name, email, password, role) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$tenant_id, $u_name, $u_email, password_hash($u_password, PASSWORD_BCRYPT), 'admin']);

                $pdo->commit();
                flash_set('success', 'Tenant "' . $t_name . '" created at ' . $t_sub . '.' . APP_DOMAIN);
            } catch (Exception $e) {
                $pdo->rollBack();
                flash_set('error', 'DB error: ' . $e->getMessage());
            }
        } else {
            flash_set('error', implode(' ', $errs));
        }
        redirect('/admin/');
    }

    if ($action === 'toggle_tenant') {
        $tid = (int)($_POST['tenant_id'] ?? 0);
        $s = $pdo->prepare('UPDATE tenants SET active = NOT active WHERE id = ?');
        $s->execute([$tid]);
        flash_set('success', 'Tenant status toggled.');
        redirect('/admin/');
    }

    if ($action === 'update_tenant') {
        $tid     = (int)($_POST['tenant_id'] ?? 0);
        $plan    = in_array($_POST['t_plan'] ?? '', ['free','pro']) ? $_POST['t_plan'] : 'free';
        $limit   = max(1, (int)($_POST['t_item_limit'] ?? 100));
        $pdo->prepare('UPDATE tenants SET plan = ?, item_limit = ? WHERE id = ?')->execute([$plan, $limit, $tid]);
        flash_set('success', 'Tenant updated.');
        redirect('/admin/');
    }
}

// ── Fetch tenants with stats ─────────────────────────────────────────────────
$tenants = $pdo->query("
    SELECT
        t.*,
        (SELECT COUNT(*) FROM items     WHERE tenant_id = t.id)                               AS item_count,
        (SELECT COUNT(*) FROM users     WHERE tenant_id = t.id)                               AS user_count,
        (SELECT COUNT(*) FROM loans     WHERE tenant_id = t.id AND returned_at IS NULL)       AS active_loans
    FROM tenants t
    ORDER BY t.created_at DESC
")->fetchAll();

// Regenerate admin CSRF token for forms
if (empty($_SESSION['admin_csrf'])) $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
$admin_csrf = $_SESSION['admin_csrf'];

// Flash helpers (inline for admin — no session namespace shared with tenant pages)
$flash_success = $_SESSION['flash']['success'] ?? null; unset($_SESSION['flash']['success']);
$flash_error   = $_SESSION['flash']['error']   ?? null; unset($_SESSION['flash']['error']);

$edit_id = (int)($_GET['edit'] ?? 0);
$edit_tenant = null;
foreach ($tenants as $t) { if ((int)$t['id'] === $edit_id) { $edit_tenant = $t; break; } }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — LouVentory</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="app-layout">
    <!-- Admin sidebar -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <span class="brand-lou">Lou</span><span class="brand-ventory">Ventory</span>
        </div>
        <div class="sidebar-tenant">Superadmin</div>
        <nav class="sidebar-nav">
            <a href="/admin/" class="nav-item active">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                Tenants
            </a>
        </nav>
        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="avatar">A</div>
                <div class="user-info">
                    <span class="user-name">Admin</span>
                    <span class="user-role">Superadmin</span>
                </div>
            </div>
            <a href="/admin/logout" class="logout-link" title="Log out">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            </a>
        </div>
    </aside>

    <main class="main-content">
        <div class="topbar">
            <div class="topbar-title">
                <h1>Tenants</h1>
                <span class="topbar-sub"><?= count($tenants) ?> workspace<?= count($tenants) !== 1 ? 's' : '' ?></span>
            </div>
        </div>

        <?php if ($flash_success): ?><div class="alert alert-success"><?= h($flash_success) ?></div><?php endif; ?>
        <?php if ($flash_error):   ?><div class="alert alert-error"><?= h($flash_error) ?></div><?php endif; ?>

        <div class="two-col-layout" style="align-items:start">

            <!-- Tenant list -->
            <div class="card" style="overflow-x:auto">
                <?php if (empty($tenants)): ?>
                    <div class="empty-state"><p>No tenants yet. Create one using the form.</p></div>
                <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Subdomain</th>
                            <th>Plan</th>
                            <th>Items</th>
                            <th>Users</th>
                            <th>Active Loans</th>
                            <th>Created</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tenants as $t): ?>
                        <tr>
                            <td class="fw-medium"><?= h($t['name']) ?></td>
                            <td>
                                <a href="http://<?= h($t['subdomain']) ?>.<?= APP_DOMAIN ?>" target="_blank" class="table-link barcode-font">
                                    <?= h($t['subdomain']) ?>
                                </a>
                            </td>
                            <td>
                                <span class="pill <?= $t['plan'] === 'pro' ? 'pill-success' : 'pill-default' ?>">
                                    <?= h(ucfirst($t['plan'])) ?>
                                </span>
                            </td>
                            <td><?= (int)$t['item_count'] ?> / <?= (int)$t['item_limit'] ?></td>
                            <td><?= (int)$t['user_count'] ?></td>
                            <td><?= (int)$t['active_loans'] ?></td>
                            <td class="text-muted"><?= h(date('d M Y', strtotime($t['created_at']))) ?></td>
                            <td>
                                <?php if ($t['active']): ?>
                                    <span class="pill pill-success">Active</span>
                                <?php else: ?>
                                    <span class="pill pill-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <a href="/admin/?edit=<?= (int)$t['id'] ?>" class="btn btn-xs btn-secondary">Edit</a>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="csrf_token"  value="<?= h($admin_csrf) ?>">
                                        <input type="hidden" name="action"      value="toggle_tenant">
                                        <input type="hidden" name="tenant_id"   value="<?= (int)$t['id'] ?>">
                                        <button type="submit" class="btn btn-xs <?= $t['active'] ? 'btn-danger' : 'btn-success' ?>"
                                            data-confirm="<?= $t['active'] ? 'Deactivate' : 'Activate' ?> tenant '<?= h(addslashes($t['name'])) ?>'?">
                                            <?= $t['active'] ? 'Disable' : 'Enable' ?>
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

            <!-- Create / Edit form -->
            <div>
                <?php if ($edit_tenant): ?>
                <div class="card form-card">
                    <div class="card-header">
                        <h2 class="card-title">Edit Tenant: <?= h($edit_tenant['name']) ?></h2>
                        <a href="/admin/" class="card-link">Cancel</a>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= h($admin_csrf) ?>">
                        <input type="hidden" name="action"     value="update_tenant">
                        <input type="hidden" name="tenant_id"  value="<?= (int)$edit_tenant['id'] ?>">
                        <div class="form-group">
                            <label class="form-label">Plan</label>
                            <select name="t_plan" class="form-input form-select">
                                <option value="free" <?= $edit_tenant['plan'] === 'free' ? 'selected' : '' ?>>Free</option>
                                <option value="pro"  <?= $edit_tenant['plan'] === 'pro'  ? 'selected' : '' ?>>Pro</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Item Limit</label>
                            <input type="number" name="t_item_limit" class="form-input" min="1" value="<?= (int)$edit_tenant['item_limit'] ?>">
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <div class="card form-card" style="margin-top:<?= $edit_tenant ? '1.5rem' : '0' ?>">
                    <div class="card-header"><h2 class="card-title">Create Tenant</h2></div>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= h($admin_csrf) ?>">
                        <input type="hidden" name="action"     value="create_tenant">

                        <p class="form-section-label">Workspace</p>
                        <div class="form-group">
                            <label class="form-label" for="t_name">Company / Name</label>
                            <input type="text" id="t_name" name="t_name" class="form-input" required placeholder="Acme Ltd">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="t_subdomain">Subdomain</label>
                            <div class="input-addon-wrap">
                                <input type="text" id="t_subdomain" name="t_subdomain" class="form-input" required placeholder="acme" pattern="[a-z0-9\-]+" style="border-radius:6px 0 0 6px; border-right:none">
                                <span class="input-addon">.<?= APP_DOMAIN ?></span>
                            </div>
                            <span class="form-hint">Lowercase letters, numbers, hyphens only.</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="t_plan">Plan</label>
                            <select id="t_plan" name="t_plan" class="form-input form-select">
                                <option value="free">Free (100 items)</option>
                                <option value="pro">Pro</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="t_item_limit">Item Limit</label>
                            <input type="number" id="t_item_limit" name="t_item_limit" class="form-input" min="1" value="100">
                        </div>

                        <p class="form-section-label" style="margin-top:1.25rem">First Admin User</p>
                        <div class="form-group">
                            <label class="form-label" for="u_name">Name</label>
                            <input type="text" id="u_name" name="u_name" class="form-input" required placeholder="Jane Smith">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="u_email">Email</label>
                            <input type="email" id="u_email" name="u_email" class="form-input" required placeholder="jane@acme.com">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="u_password">Password</label>
                            <input type="password" id="u_password" name="u_password" class="form-input" required minlength="8" placeholder="Minimum 8 characters">
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Create Tenant</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<div id="confirm-modal" class="modal-overlay" style="display:none">
    <div class="modal-box">
        <h3 class="modal-title" id="modal-title">Confirm action</h3>
        <p class="modal-body"  id="modal-body">Are you sure?</p>
        <div class="modal-actions">
            <button class="btn btn-secondary" id="modal-cancel">Cancel</button>
            <button class="btn btn-danger"    id="modal-confirm">Confirm</button>
        </div>
    </div>
</div>
<script src="/assets/js/app.js"></script>
</body>
</html>
