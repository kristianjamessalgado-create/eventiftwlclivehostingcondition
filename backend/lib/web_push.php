<?php

require_once __DIR__ . '/../../config/env.php';

/**
 * Web Push (VAPID) for student phone notifications.
 */
function eventify_web_push_autoload(): bool
{
    static $loaded = null;
    if ($loaded !== null) {
        return $loaded;
    }
    $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
    if (!is_readable($autoload)) {
        $loaded = false;
        return false;
    }
    require_once $autoload;
    $loaded = true;
    return true;
}

function eventify_web_push_ensure_tables(mysqli $conn): void
{
    $conn->query("
        CREATE TABLE IF NOT EXISTS push_subscriptions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            endpoint_hash CHAR(64) NOT NULL,
            endpoint TEXT NOT NULL,
            p256dh VARCHAR(255) NOT NULL,
            auth VARCHAR(255) NOT NULL,
            user_agent VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_push_endpoint_hash (endpoint_hash),
            KEY idx_push_user (user_id),
            CONSTRAINT fk_push_sub_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $col = $conn->query("SHOW COLUMNS FROM student_settings LIKE 'notif_channel_push'");
    if ($col && $col->num_rows === 0) {
        $conn->query("ALTER TABLE student_settings ADD COLUMN notif_channel_push TINYINT(1) NOT NULL DEFAULT 1 AFTER notif_channel_email");
    }
    if ($col) {
        $col->free();
    }
}

function eventify_web_push_vapid_config(): ?array
{
    eventify_load_env_file();
    $public = trim((string) (eventify_env('VAPID_PUBLIC_KEY') ?? ''));
    $private = trim((string) (eventify_env('VAPID_PRIVATE_KEY') ?? ''));
    $subject = trim((string) (eventify_env('VAPID_SUBJECT') ?? ''));
    if ($public === '' || $private === '') {
        $local = dirname(__DIR__, 2) . '/config/vapid.local.php';
        if (is_readable($local)) {
            $cfg = include $local;
            if (is_array($cfg)) {
                $public = trim((string) ($cfg['publicKey'] ?? $cfg['VAPID_PUBLIC_KEY'] ?? ''));
                $private = trim((string) ($cfg['privateKey'] ?? $cfg['VAPID_PRIVATE_KEY'] ?? ''));
                $subject = trim((string) ($cfg['subject'] ?? $cfg['VAPID_SUBJECT'] ?? $subject));
            }
        }
    }
    if ($public === '' || $private === '') {
        return null;
    }
    if ($subject === '') {
        $subject = 'mailto:support@eventifywlc.com';
    }

    $public = preg_replace('/\s+/', '', $public) ?? $public;
    $private = preg_replace('/\s+/', '', $private) ?? $private;

    if (eventify_web_push_autoload()) {
        try {
            $validated = Minishlink\WebPush\VAPID::validate([
                'subject' => $subject,
                'publicKey' => $public,
                'privateKey' => $private,
            ]);
            if (!eventify_web_push_keys_can_sign($validated)) {
                return null;
            }
            return [
                'subject' => $validated['subject'],
                'publicKey' => $public,
                'privateKey' => $private,
            ];
        } catch (Throwable $e) {
            return null;
        }
    }

    return [
        'subject' => $subject,
        'publicKey' => $public,
        'privateKey' => $private,
    ];
}

function eventify_web_push_is_configured(): bool
{
    return eventify_web_push_vapid_config() !== null;
}

/**
 * True when the VAPID pair can produce a signed JWT (length-only validate is not enough).
 *
 * @param array{subject: string, publicKey: string, privateKey: string} $validated Binary keys from VAPID::validate()
 */
function eventify_web_push_keys_can_sign(array $validated): bool
{
    if (!eventify_web_push_autoload()) {
        return false;
    }
    try {
        Minishlink\WebPush\VAPID::getVapidHeaders(
            'https://fcm.googleapis.com',
            (string) $validated['subject'],
            (string) $validated['publicKey'],
            (string) $validated['privateKey'],
            Minishlink\WebPush\ContentEncoding::aesgcm
        );
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function eventify_web_push_public_key(): string
{
    $cfg = eventify_web_push_vapid_config();
    return $cfg ? (string) $cfg['publicKey'] : '';
}

/**
 * @return array{configured: bool, public_key_length: int, public_key_valid: bool, vendor_loaded: bool, env_file_readable: bool, env_has_public_key: bool}
 */
function eventify_web_push_diagnostics(): array
{
    eventify_load_env_file();
    $envPath = dirname(__DIR__, 2) . '/.env';
    $rawPublic = trim((string) ($_ENV['VAPID_PUBLIC_KEY'] ?? eventify_env('VAPID_PUBLIC_KEY') ?? ''));
    $rawPublic = preg_replace('/\s+/', '', $rawPublic) ?? $rawPublic;
    $vendorLoaded = eventify_web_push_autoload();
    $cfg = eventify_web_push_vapid_config();
    $publicKey = $cfg ? (string) $cfg['publicKey'] : '';
    $signingOk = false;
    $valid = false;
    if ($rawPublic !== '' && $vendorLoaded) {
        try {
            $validated = Minishlink\WebPush\VAPID::validate([
                'subject' => trim((string) ($_ENV['VAPID_SUBJECT'] ?? eventify_env('VAPID_SUBJECT') ?? 'mailto:support@eventifywlc.com')),
                'publicKey' => $rawPublic,
                'privateKey' => preg_replace('/\s+/', '', trim((string) ($_ENV['VAPID_PRIVATE_KEY'] ?? eventify_env('VAPID_PRIVATE_KEY') ?? ''))) ?? '',
            ]);
            $signingOk = eventify_web_push_keys_can_sign($validated);
            $valid = $signingOk;
        } catch (Throwable $e) {
            $valid = false;
        }
    }
    return [
        'configured' => $cfg !== null,
        'public_key_length' => strlen($publicKey),
        'public_key_valid' => $valid,
        'signing_ok' => $signingOk,
        'vendor_loaded' => $vendorLoaded,
        'env_file_readable' => is_readable($envPath),
        'env_has_public_key' => $rawPublic !== '',
    ];
}

function eventify_web_push_endpoint_hash(string $endpoint): string
{
    return hash('sha256', $endpoint);
}

function eventify_web_push_save_subscription(mysqli $conn, int $userId, array $subscription, ?string $userAgent = null): bool
{
    if ($userId < 1) {
        return false;
    }
    $endpoint = trim((string) ($subscription['endpoint'] ?? ''));
    $keys = $subscription['keys'] ?? [];
    $p256dh = trim((string) ($keys['p256dh'] ?? ''));
    $auth = trim((string) ($keys['auth'] ?? ''));
    if ($endpoint === '' || $p256dh === '' || $auth === '') {
        return false;
    }

    eventify_web_push_ensure_tables($conn);
    $hash = eventify_web_push_endpoint_hash($endpoint);
    $ua = $userAgent !== null ? substr($userAgent, 0, 255) : null;

    $stmt = $conn->prepare("
        INSERT INTO push_subscriptions (user_id, endpoint_hash, endpoint, p256dh, auth, user_agent)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            user_id = VALUES(user_id),
            endpoint = VALUES(endpoint),
            p256dh = VALUES(p256dh),
            auth = VALUES(auth),
            user_agent = VALUES(user_agent),
            updated_at = CURRENT_TIMESTAMP
    ");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('isssss', $userId, $hash, $endpoint, $p256dh, $auth, $ua);
    $ok = $stmt->execute();
    $stmt->close();
    return (bool) $ok;
}

function eventify_web_push_remove_subscription(mysqli $conn, int $userId, string $endpoint): bool
{
    if ($userId < 1 || trim($endpoint) === '') {
        return false;
    }
    eventify_web_push_ensure_tables($conn);
    $hash = eventify_web_push_endpoint_hash($endpoint);
    $stmt = $conn->prepare('DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint_hash = ?');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('is', $userId, $hash);
    $ok = $stmt->execute();
    $stmt->close();
    return (bool) $ok;
}

function eventify_web_push_load_student_settings(mysqli $conn, int $userId): array
{
    $defaults = [
        'notif_channel_push' => 1,
        'rsvp_updates' => 1,
        'announcement_notifications' => 1,
        'event_reminders' => 1,
    ];
    try {
        eventify_web_push_ensure_tables($conn);
        $stmt = $conn->prepare('
            SELECT notif_channel_push, rsvp_updates, announcement_notifications, event_reminders
            FROM student_settings WHERE user_id = ? LIMIT 1
        ');
        if (!$stmt) {
            return $defaults;
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if ($row) {
            return array_merge($defaults, $row);
        }
    } catch (Throwable $e) {
        /* defaults */
    }
    return $defaults;
}

function eventify_push_type_allowed_for_student(array $settings, string $notifType): bool
{
    if (empty($settings['notif_channel_push'])) {
        return false;
    }
    $type = strtolower(trim($notifType));
    $rsvpTypes = ['rsvp_confirmed', 'rsvp_cancelled'];
    $announceTypes = [
        'event_update',
        'event_updated_pending',
        'staff_message',
        'activity_update',
        'event_admin_corrected',
    ];
    $reminderTypes = ['event_reminder', 'activity_reminder'];
    if (in_array($type, $rsvpTypes, true)) {
        return !empty($settings['rsvp_updates']);
    }
    if (in_array($type, $announceTypes, true)) {
        return !empty($settings['announcement_notifications']);
    }
    if (in_array($type, $reminderTypes, true)) {
        return !empty($settings['event_reminders']);
    }
    return true;
}

function eventify_push_url_for_notification(?int $eventId): string
{
    $base = rtrim(BASE_URL, '/');
    if ($eventId !== null && $eventId > 0) {
        return $base . '/backend/auth/dashboard_student.php?event_id=' . $eventId;
    }
    return $base . '/backend/auth/dashboard_student.php';
}

function eventify_web_push_count_subscriptions(mysqli $conn, int $userId): int
{
    if ($userId < 1) {
        return 0;
    }
    eventify_web_push_ensure_tables($conn);
    $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM push_subscriptions WHERE user_id = ?');
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return (int) ($row['c'] ?? 0);
}

/**
 * @return array{ok: bool, sent: int, failed: int, errors: list<string>, subscription_count: int}
 */
function eventify_web_push_send_to_user(
    mysqli $conn,
    int $userId,
    string $type,
    string $title,
    string $message,
    ?int $eventId = null
): array {
    $result = [
        'ok' => false,
        'sent' => 0,
        'failed' => 0,
        'errors' => [],
        'subscription_count' => 0,
    ];

    if ($userId < 1) {
        $result['errors'][] = 'invalid_user';
        return $result;
    }
    if (!eventify_web_push_is_configured()) {
        $result['errors'][] = 'vapid_not_configured';
        return $result;
    }
    if (!eventify_web_push_autoload()) {
        $result['errors'][] = 'vendor_autoload_missing';
        return $result;
    }

    try {
        $roleStmt = $conn->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
        if (!$roleStmt) {
            $result['errors'][] = 'role_query_failed';
            return $result;
        }
        $roleStmt->bind_param('i', $userId);
        $roleStmt->execute();
        $roleRes = $roleStmt->get_result();
        $roleRow = $roleRes ? $roleRes->fetch_assoc() : null;
        $roleStmt->close();
        if (($roleRow['role'] ?? '') !== 'student') {
            $result['errors'][] = 'not_a_student';
            return $result;
        }

        $settings = eventify_web_push_load_student_settings($conn, $userId);
        if (!eventify_push_type_allowed_for_student($settings, $type)) {
            $result['errors'][] = 'blocked_by_student_settings';
            return $result;
        }

        eventify_web_push_ensure_tables($conn);
        $stmt = $conn->prepare('SELECT endpoint, p256dh, auth FROM push_subscriptions WHERE user_id = ?');
        if (!$stmt) {
            $result['errors'][] = 'subscription_query_failed';
            return $result;
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $subs = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        $result['subscription_count'] = count($subs);
        if ($subs === []) {
            $result['errors'][] = 'no_device_subscription';
            return $result;
        }

        $vapid = eventify_web_push_vapid_config();
        if (!$vapid) {
            $result['errors'][] = 'vapid_not_configured';
            return $result;
        }

        $webPush = new Minishlink\WebPush\WebPush(['VAPID' => $vapid]);
        $payload = json_encode([
            'title' => $title,
            'body' => $message,
            'url' => eventify_push_url_for_notification($eventId),
            'event_id' => $eventId,
            'type' => $type,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $staleHashes = [];
        foreach ($subs as $sub) {
            $endpoint = (string) ($sub['endpoint'] ?? '');
            if ($endpoint === '') {
                continue;
            }
            $subscription = Minishlink\WebPush\Subscription::create([
                'endpoint' => $endpoint,
                'keys' => [
                    'p256dh' => (string) ($sub['p256dh'] ?? ''),
                    'auth' => (string) ($sub['auth'] ?? ''),
                ],
            ]);
            $report = $webPush->sendOneNotification($subscription, $payload === false ? '{}' : $payload);
            if ($report->isSuccess()) {
                $result['sent']++;
            } else {
                $result['failed']++;
                $reason = $report->getReason() ?: 'send_failed';
                $code = (int) $report->getResponse()?->getStatusCode();
                if ($code > 0) {
                    $reason .= ' (HTTP ' . $code . ')';
                }
                $result['errors'][] = $reason;
                if ($code === 404 || $code === 410) {
                    $staleHashes[] = eventify_web_push_endpoint_hash($endpoint);
                }
            }
        }

        if ($staleHashes !== []) {
            $del = $conn->prepare('DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint_hash = ?');
            if ($del) {
                foreach ($staleHashes as $hash) {
                    $del->bind_param('is', $userId, $hash);
                    $del->execute();
                }
                $del->close();
            }
        }

        $result['ok'] = $result['sent'] > 0;
        if (!$result['ok'] && $result['errors'] === []) {
            $result['errors'][] = 'send_failed';
        }
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        if (stripos($msg, 'ECSignature') !== false || stripos($msg, 'private key') !== false) {
            $result['errors'][] = 'Invalid VAPID keys in .env — generate a new matching public/private pair and update the server.';
        } else {
            $result['errors'][] = $msg;
        }
    }

    return $result;
}

function eventify_push_notify_user(
    mysqli $conn,
    int $userId,
    string $type,
    string $title,
    string $message,
    ?int $eventId = null
): void {
    eventify_web_push_send_to_user($conn, $userId, $type, $title, $message, $eventId);
}
