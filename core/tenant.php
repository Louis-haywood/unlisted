<?php
require_once __DIR__ . '/../config/db.php';

function get_pdo(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function detect_subdomain(): ?string {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $host = preg_replace('/:\d+$/', '', $host); // strip port

    $domain = APP_DOMAIN;
    if (str_ends_with($host, '.' . $domain)) {
        return substr($host, 0, strlen($host) - strlen('.' . $domain));
    }
    return null;
}

function load_tenant(string $subdomain): ?array {
    $pdo  = get_pdo();
    $stmt = $pdo->prepare('SELECT * FROM tenants WHERE subdomain = ? AND active = 1 LIMIT 1');
    $stmt->execute([$subdomain]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function load_tenant_by_id(int $id): ?array {
    $pdo  = get_pdo();
    $stmt = $pdo->prepare('SELECT * FROM tenants WHERE id = ? AND active = 1 LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function load_tenant_by_custom_domain(string $host): ?array {
    $pdo  = get_pdo();
    $stmt = $pdo->prepare('SELECT * FROM tenants WHERE custom_domain = ? AND active = 1 LIMIT 1');
    $stmt->execute([$host]);
    $row = $stmt->fetch();
    return $row ?: null;
}
