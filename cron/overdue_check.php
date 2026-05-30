<?php
// Overdue loan alert cron — call via:
// https://yourtenant.louventory.uk/cron/overdue_check.php?token=YOUR_CRON_TOKEN
// or set up in Hostinger hPanel as a URL cron.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/tenant.php';

// Validate secret token
$token = $_GET['token'] ?? '';
if (!hash_equals(CRON_TOKEN, $token)) {
    http_response_code(403);
    exit('Forbidden');
}

$pdo = get_pdo();

// Find all overdue loans where no alert was sent today
$stmt = $pdo->prepare("
    SELECT
        l.*,
        i.name       AS item_name,
        b.name       AS borrower_name,
        b.email      AS borrower_email,
        t.name       AS tenant_name,
        t.subdomain  AS tenant_subdomain
    FROM loans l
    JOIN items     i ON i.id = l.item_id
    JOIN borrowers b ON b.id = l.borrower_id
    JOIN tenants   t ON t.id = l.tenant_id AND t.active = 1
    WHERE
        l.returned_at IS NULL
        AND l.due_date IS NOT NULL
        AND l.due_date < CURDATE()
        AND NOT EXISTS (
            SELECT 1 FROM activity_log al
            WHERE al.tenant_id   = l.tenant_id
              AND al.action      = 'overdue_alert'
              AND al.description LIKE CONCAT('Overdue alert for loan #', l.id, '%')
              AND DATE(al.created_at) = CURDATE()
        )
");
$stmt->execute();
$overdue_loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sent = 0;
$errors = 0;

foreach ($overdue_loans as $loan) {
    $to           = $loan['borrower_email'];
    $subject      = 'Overdue reminder: ' . $loan['item_name'];
    $days_overdue = (int)floor((time() - strtotime($loan['due_date'])) / 86400);

    $body  = "Hi " . $loan['borrower_name'] . ",\n\n";
    $body .= "This is a reminder that the following item is overdue:\n\n";
    $body .= "Item:     " . $loan['item_name'] . "\n";
    $body .= "Due date: " . date('d M Y', strtotime($loan['due_date'])) . "\n";
    $body .= "Overdue:  " . $days_overdue . " day" . ($days_overdue !== 1 ? "s" : "") . "\n\n";
    $body .= "Please return this item as soon as possible or contact us if you need more time.\n\n";
    $body .= "Thanks,\n" . $loan['tenant_name'];

    $headers  = "From: noreply@" . APP_DOMAIN . "\r\n";
    $headers .= "Reply-To: noreply@" . APP_DOMAIN . "\r\n";
    $headers .= "X-Mailer: LouVentory-Cron\r\n";

    if ($to && filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $ok = mail($to, $subject, $body, $headers);
        if ($ok) {
            $pdo->prepare("
                INSERT INTO activity_log (tenant_id, user_id, action, description)
                VALUES (?, NULL, 'overdue_alert', ?)
            ")->execute([
                $loan['tenant_id'],
                'Overdue alert for loan #' . $loan['id'] . ' — ' . $loan['item_name'] . ' to ' . $loan['borrower_name']
            ]);
            $sent++;
        } else {
            $errors++;
        }
    } else {
        // No valid email — log so we don't re-process today
        $pdo->prepare("
            INSERT INTO activity_log (tenant_id, user_id, action, description)
            VALUES (?, NULL, 'overdue_alert', ?)
        ")->execute([
            $loan['tenant_id'],
            'Overdue alert for loan #' . $loan['id'] . ' — ' . $loan['item_name'] . ' (no email address)'
        ]);
    }
}

header('Content-Type: text/plain');
echo "Done. Overdue loans found: " . count($overdue_loans) . ". Emails sent: $sent. Errors: $errors.\n";
