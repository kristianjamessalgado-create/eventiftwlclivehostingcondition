<?php
/**
 * One-time DB diagnostic (delete or protect on production after use).
 * Open: /tools/check_db.php
 */
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../config/db.php';

echo "EVENTIFY DB check\n";
echo "=================\n";
echo 'Connected database: ' . ($db ?? '(unknown)') . "\n";
echo 'Host: ' . ($host ?? 'localhost') . "\n\n";

$tables = ['users', 'events', 'admin_settings'];
foreach ($tables as $table) {
    $res = $conn->query("SELECT COUNT(*) AS c FROM `{$table}`");
    if (!$res) {
        echo "{$table}: ERROR — " . $conn->error . "\n";
        continue;
    }
    $row = $res->fetch_assoc();
    echo "{$table}: " . (int)($row['c'] ?? 0) . " rows\n";
}

echo "\nActive admin/super_admin accounts:\n";
$res = $conn->query("SELECT id, email, role, status, failed_attempts FROM users WHERE role IN ('admin','super_admin') ORDER BY id");
if ($res) {
    while ($u = $res->fetch_assoc()) {
        echo sprintf(
            "  #%d %s (%s) status=%s failed=%d\n",
            (int)$u['id'],
            $u['email'],
            $u['role'],
            $u['status'],
            (int)$u['failed_attempts']
        );
    }
} else {
    echo '  (query failed: ' . $conn->error . ")\n";
}

echo "\nIf users/events counts are 0 here but phpMyAdmin shows data, DB_NAME or credentials in .env / config/db.local.php are wrong.\n";
