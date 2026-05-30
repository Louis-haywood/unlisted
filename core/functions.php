<?php

// ── Output helpers ────────────────────────────────────────────────────────────

function h(mixed $val): string {
    return htmlspecialchars((string)$val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never {
    header('Location: ' . $path);
    exit;
}

// ── CSRF ──────────────────────────────────────────────────────────────────────

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function csrf_verify(): bool {
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token)) return false;
    return hash_equals(csrf_token(), $token);
}

// ── Flash messages ────────────────────────────────────────────────────────────

function flash_set(string $key, string $message): void {
    $_SESSION['flash'][$key] = $message;
}

function flash_get(string $key): ?string {
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function flash_html(): string {
    $html  = '';
    $types = ['success' => 'alert-success', 'error' => 'alert-error', 'info' => 'alert-info'];
    foreach ($types as $key => $cls) {
        $msg = flash_get($key);
        if ($msg !== null) {
            $html .= '<div class="alert ' . $cls . '">' . h($msg) . '</div>';
        }
    }
    return $html;
}

// ── Activity log ──────────────────────────────────────────────────────────────

function log_activity(int $tenant_id, ?int $user_id, string $action, string $description): void {
    $pdo  = get_pdo();
    $stmt = $pdo->prepare(
        'INSERT INTO activity_log (tenant_id, user_id, action, description) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$tenant_id, $user_id, $action, $description]);
}

// ── Barcode generation ────────────────────────────────────────────────────────

function generate_barcode(): string {
    return 'LV-' . strtoupper(bin2hex(random_bytes(5)));
}

// ── Item status ───────────────────────────────────────────────────────────────

function compute_item_status(array $item): string {
    $active  = (int)($item['active_loans']  ?? 0);
    $overdue = (int)($item['overdue_loans'] ?? 0);
    $qty     = (int)$item['quantity'];

    if ($overdue > 0)                                         return 'overdue';
    if ($active  > 0)                                         return 'on_loan';
    if ($qty <= 0)                                            return 'out_of_stock';
    if ($qty <= (int)$item['low_stock_threshold'])            return 'low_stock';
    return 'available';
}

function status_pill(string $status): string {
    $map = [
        'available'    => ['Available',    'pill-success'],
        'on_loan'      => ['On Loan',      'pill-warning'],
        'overdue'      => ['Overdue',      'pill-danger'],
        'low_stock'    => ['Low Stock',    'pill-warning'],
        'out_of_stock' => ['Out of Stock', 'pill-danger'],
    ];
    [$label, $cls] = $map[$status] ?? ['Unknown', 'pill-default'];
    return '<span class="pill ' . $cls . '">' . $label . '</span>';
}

// ── Time helpers ──────────────────────────────────────────────────────────────

function time_ago(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)   return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('d M Y', strtotime($datetime));
}

function days_between(string $from, string $to): int {
    $d1 = new DateTime($from);
    $d2 = new DateTime($to);
    return (int)$d1->diff($d2)->days;
}

// ── Pagination ────────────────────────────────────────────────────────────────

function paginate(int $total, int $per_page, int $current_page): array {
    $total_pages = max(1, (int)ceil($total / $per_page));
    $current_page = max(1, min($current_page, $total_pages));
    return [
        'total'        => $total,
        'per_page'     => $per_page,
        'current_page' => $current_page,
        'total_pages'  => $total_pages,
        'offset'       => ($current_page - 1) * $per_page,
    ];
}

function pagination_html(array $pag, string $base_url): string {
    if ($pag['total_pages'] <= 1) return '';
    $html = '<div class="pagination">';
    for ($i = 1; $i <= $pag['total_pages']; $i++) {
        $active = $i === $pag['current_page'] ? ' active' : '';
        $sep    = strpos($base_url, '?') !== false ? '&' : '?';
        $html  .= '<a href="' . h($base_url . $sep . 'page=' . $i) . '" class="page-link' . $active . '">' . $i . '</a>';
    }
    $html .= '</div>';
    return $html;
}

// ── Upload helpers ────────────────────────────────────────────────────────────

function upload_photo(array $file, int $tenant_id, int $item_id): string|false {
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowed, true)) return false;
    if ($file['size'] > UPLOAD_MAX_SIZE)          return false;

    $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $dir     = __DIR__ . '/../uploads/' . $tenant_id . '/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $filename = $item_id . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $dir . $filename)) {
        return $tenant_id . '/' . $filename;
    }
    return false;
}

// ── Items query helper ────────────────────────────────────────────────────────

function items_base_query(int $tenant_id, string $extra_where = '', array $extra_params = []): array {
    $pdo = get_pdo();

    $where  = 'i.tenant_id = ?';
    $params = [$tenant_id];

    if ($extra_where) {
        $where .= ' AND ' . $extra_where;
        $params = array_merge($params, $extra_params);
    }

    $sql = "
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
        WHERE {$where}
        ORDER BY i.created_at DESC
    ";

    // The subquery needs tenant_id too
    $all_params = array_merge([$tenant_id], $params);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($all_params);
    return $stmt->fetchAll();
}
