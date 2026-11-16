<?php

/**
 * Admin ↔ Organizer direct messages (staff_messages table).
 */

function eventify_staff_messages_ensure_table(mysqli $conn): bool
{
    static $done = false;
    if ($done) {
        return true;
    }
    try {
        $conn->query("
            CREATE TABLE IF NOT EXISTS staff_messages (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                sender_id INT NOT NULL,
                recipient_id INT NOT NULL,
                body VARCHAR(8000) NOT NULL,
                attachment_path VARCHAR(512) NULL DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                read_at DATETIME NULL DEFAULT NULL,
                KEY idx_pair_time (sender_id, recipient_id, created_at),
                KEY idx_inbox (recipient_id, read_at, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $col = $conn->query("SHOW COLUMNS FROM staff_messages LIKE 'attachment_path'");
        if ($col && $col->num_rows === 0) {
            $conn->query("ALTER TABLE staff_messages ADD COLUMN attachment_path VARCHAR(512) NULL DEFAULT NULL AFTER body");
        }
        if ($col) {
            $col->free();
        }
        $done = true;
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/** Roles that can use staff messaging. */
function eventify_staff_messaging_roles(): array
{
    return ['admin', 'organizer', 'super_admin'];
}

/**
 * Who each role may message:
 *  - admin       <-> organizer
 *  - admin       <-> super_admin
 *
 * @return list<string>
 */
function eventify_staff_messaging_peer_roles(string $role): array
{
    switch (strtolower($role)) {
        case 'admin':
            return ['organizer', 'super_admin'];
        case 'organizer':
        case 'super_admin':
            return ['admin'];
    }
    return [];
}

/** Human label for a staff role. */
function eventify_staff_role_label(string $role): string
{
    $labels = ['admin' => 'Admin', 'organizer' => 'Organizer', 'super_admin' => 'Super Admin'];
    return $labels[strtolower($role)] ?? ucfirst($role);
}

/**
 * @return 'admin'|'organizer'|'super_admin'|null
 */
function eventify_staff_user_role(mysqli $conn, int $userId): ?string
{
    if ($userId < 1) {
        return null;
    }
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    $r = strtolower((string)($row['role'] ?? ''));
    if (in_array($r, eventify_staff_messaging_roles(), true)) {
        return $r;
    }
    return null;
}

/**
 * True for admin <-> organizer and admin <-> super_admin pairs.
 */
function eventify_staff_messaging_pair_allowed(mysqli $conn, int $userA, int $userB): bool
{
    if ($userA < 1 || $userB < 1 || $userA === $userB) {
        return false;
    }
    $ra = eventify_staff_user_role($conn, $userA);
    $rb = eventify_staff_user_role($conn, $userB);
    if ($ra === null || $rb === null) {
        return false;
    }
    return in_array($rb, eventify_staff_messaging_peer_roles($ra), true);
}

/**
 * Load messenger inbox context for admin or organizer UI.
 *
 * @return array{
 *     peers_list: list<array<string, mixed>>,
 *     my_name: string,
 *     messaging_error: ?string,
 *     peer_label: string,
 *     initial_with: int,
 *     unread_total: int
 * }
 */
function eventify_staff_messenger_load_context(mysqli $conn, int $uid, string $role, int $initialWith = 0): array
{
    $role = strtolower($role);
    $peerRoles = eventify_staff_messaging_peer_roles($role);
    $peerLabel = ($role === 'admin') ? 'Organizers & Super Admin' : 'Admins';
    $empty = [
        'peers_list' => [],
        'my_name' => '',
        'messaging_error' => null,
        'peer_label' => $peerLabel,
        'initial_with' => 0,
        'unread_total' => 0,
    ];

    if ($uid < 1 || $peerRoles === []) {
        $empty['messaging_error'] = 'Sign in to use messages.';
        return $empty;
    }

    if (!eventify_staff_messages_ensure_table($conn)) {
        $empty['messaging_error'] = 'Messaging is temporarily unavailable.';
        return $empty;
    }

    $myName = '';
    $st = $conn->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
    if ($st) {
        $st->bind_param('i', $uid);
        $st->execute();
        $r = $st->get_result();
        if ($r && ($row = $r->fetch_assoc())) {
            $myName = (string) ($row['name'] ?? '');
        }
        $st->close();
    }

    $peers = [];
    $roleMarks = implode(',', array_fill(0, count($peerRoles), '?'));
    $pq = $conn->prepare("SELECT id, name, email, role, profile_picture FROM users WHERE role IN ($roleMarks) AND id <> ? ORDER BY name ASC LIMIT 500");
    if (!$pq) {
        // Older schema without profile_picture.
        $pq = $conn->prepare("SELECT id, name, email, role FROM users WHERE role IN ($roleMarks) AND id <> ? ORDER BY name ASC LIMIT 500");
    }
    if ($pq) {
        $pqTypes = str_repeat('s', count($peerRoles)) . 'i';
        $pqParams = array_merge($peerRoles, [$uid]);
        $pq->bind_param($pqTypes, ...$pqParams);
        $pq->execute();
        $pr = $pq->get_result();
        if ($pr) {
            while ($row = $pr->fetch_assoc()) {
                $peers[(int) $row['id']] = [
                    'id' => (int) $row['id'],
                    'name' => (string) ($row['name'] ?? ''),
                    'email' => (string) ($row['email'] ?? ''),
                    'role' => (string) ($row['role'] ?? ''),
                    'role_label' => eventify_staff_role_label((string) ($row['role'] ?? '')),
                    'avatar' => !empty($row['profile_picture']) ? (string) $row['profile_picture'] : null,
                    'last_body' => null,
                    'last_at' => null,
                    'last_sender_id' => null,
                    'unread_count' => 0,
                ];
            }
        }
        $pq->close();
    }

    $lastByPeer = [];
    $lq = $conn->prepare("
        SELECT x.peer_id, m.body, m.created_at, m.sender_id AS last_sender_id
        FROM (
            SELECT IF(sender_id = ?, recipient_id, sender_id) AS peer_id, MAX(id) AS max_id
            FROM staff_messages
            WHERE sender_id = ? OR recipient_id = ?
            GROUP BY peer_id
        ) x
        INNER JOIN staff_messages m ON m.id = x.max_id
    ");
    if ($lq) {
        $lq->bind_param('iii', $uid, $uid, $uid);
        $lq->execute();
        $lr = $lq->get_result();
        if ($lr) {
            while ($row = $lr->fetch_assoc()) {
                $pid = (int) ($row['peer_id'] ?? 0);
                if ($pid > 0) {
                    $lastByPeer[$pid] = $row;
                }
            }
        }
        $lq->close();
    }

    $unreadTotal = 0;
    $uq = $conn->prepare('SELECT sender_id, COUNT(*) AS c FROM staff_messages WHERE recipient_id = ? AND read_at IS NULL GROUP BY sender_id');
    if ($uq) {
        $uq->bind_param('i', $uid);
        $uq->execute();
        $ur = $uq->get_result();
        if ($ur) {
            while ($row = $ur->fetch_assoc()) {
                $sid = (int) ($row['sender_id'] ?? 0);
                $count = (int) ($row['c'] ?? 0);
                if (isset($peers[$sid])) {
                    $peers[$sid]['unread_count'] = $count;
                    $unreadTotal += $count;
                }
            }
        }
        $uq->close();
    }

    foreach ($lastByPeer as $pid => $row) {
        if (!isset($peers[$pid])) {
            continue;
        }
        $peers[$pid]['last_body'] = (string) ($row['body'] ?? '');
        $peers[$pid]['last_at'] = $row['created_at'] ?? null;
        $peers[$pid]['last_sender_id'] = isset($row['last_sender_id']) ? (int) $row['last_sender_id'] : null;
    }

    $peersList = array_values($peers);
    usort($peersList, static function ($a, $b) {
        $ta = $a['last_at'] ? strtotime((string) $a['last_at']) : 0;
        $tb = $b['last_at'] ? strtotime((string) $b['last_at']) : 0;
        if ($ta === $tb) {
            return strcasecmp($a['name'], $b['name']);
        }
        return $tb <=> $ta;
    });

    $initialWith = max(0, $initialWith);
    if ($initialWith > 0 && !isset($peers[$initialWith])) {
        $initialWith = 0;
    }

    return [
        'peers_list' => $peersList,
        'my_name' => $myName,
        'messaging_error' => null,
        'peer_label' => $peerLabel,
        'initial_with' => $initialWith,
        'unread_total' => $unreadTotal,
    ];
}
