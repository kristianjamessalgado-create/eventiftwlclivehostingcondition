<?php

/**
 * Shared check-in security: geofence, device lock, RSVP requirement.
 */

function eventify_checkin_config_require_rsvp(): bool
{
    return !defined('EVENTIFY_CHECKIN_REQUIRE_RSVP') || EVENTIFY_CHECKIN_REQUIRE_RSVP;
}

function eventify_checkin_config_geo_when_pinned(): bool
{
    return !defined('EVENTIFY_CHECKIN_GEO_WHEN_PINNED') || EVENTIFY_CHECKIN_GEO_WHEN_PINNED;
}

function eventify_checkin_geo_radius_m(): float
{
    if (defined('EVENTIFY_CHECKIN_GEO_RADIUS_M')) {
        return max(50.0, (float) EVENTIFY_CHECKIN_GEO_RADIUS_M);
    }
    return 300.0;
}

function eventify_checkin_early_minutes(): int
{
    if (defined('EVENTIFY_CHECKIN_EARLY_MINUTES')) {
        return max(0, (int) EVENTIFY_CHECKIN_EARLY_MINUTES);
    }
    return 15;
}

function eventify_haversine_distance_m(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $earth = 6371000.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2)
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earth * $c;
}

/** @return array{lat: ?float, lng: ?float} */
function eventify_checkin_coords_from_row(array $row): array
{
    $lat = isset($row['latitude']) && $row['latitude'] !== '' && $row['latitude'] !== null
        ? (float) $row['latitude'] : null;
    $lng = isset($row['longitude']) && $row['longitude'] !== '' && $row['longitude'] !== null
        ? (float) $row['longitude'] : null;
    if ($lat === null || $lng === null) {
        return ['lat' => null, 'lng' => null];
    }
    return ['lat' => $lat, 'lng' => $lng];
}

/**
 * Campus location used when an event/activity has no map pin, so the distance
 * check never gets skipped. Default: Western Leyte College, Ormoc City (OpenStreetMap).
 *
 * @return array{lat: float, lng: float}
 */
function eventify_checkin_campus_coords(): array
{
    $lat = defined('EVENTIFY_CAMPUS_LAT') ? (float) EVENTIFY_CAMPUS_LAT : 11.0091853;
    $lng = defined('EVENTIFY_CAMPUS_LNG') ? (float) EVENTIFY_CAMPUS_LNG : 124.6098772;
    return ['lat' => $lat, 'lng' => $lng];
}

/**
 * Venue pin for a main event, or the campus location when the event has none.
 *
 * @param array<string, mixed> $event
 * @return array{lat: float, lng: float}
 */
function eventify_event_checkin_venue_coords(array $event): array
{
    $coords = eventify_checkin_coords_from_row($event);
    if ($coords['lat'] !== null && $coords['lng'] !== null) {
        return ['lat' => (float) $coords['lat'], 'lng' => (float) $coords['lng']];
    }
    return eventify_checkin_campus_coords();
}

/**
 * Old databases were created without events.latitude/longitude, which made the map pin
 * get dropped on save. Add the columns when missing (safe to call on every request).
 */
function eventify_events_ensure_geo_columns(mysqli $conn): bool
{
    static $done = null;
    if ($done !== null) {
        return $done;
    }
    try {
        $res = $conn->query("SHOW COLUMNS FROM events WHERE Field IN ('latitude','longitude')");
        $have = [];
        while ($res && ($row = $res->fetch_assoc())) {
            $have[] = (string) $row['Field'];
        }
        if (!in_array('latitude', $have, true)) {
            $conn->query('ALTER TABLE events ADD COLUMN latitude DECIMAL(10,7) NULL DEFAULT NULL AFTER location');
        }
        if (!in_array('longitude', $have, true)) {
            $conn->query('ALTER TABLE events ADD COLUMN longitude DECIMAL(10,7) NULL DEFAULT NULL AFTER latitude');
        }
        // Old databases made events.location VARCHAR(100), which cut off map addresses.
        $loc = $conn->query("SHOW COLUMNS FROM events LIKE 'location'");
        $locRow = $loc ? $loc->fetch_assoc() : null;
        if ($locRow && preg_match('/^varchar\((\d+)\)/i', (string) ($locRow['Type'] ?? ''), $m) && (int) $m[1] < 255) {
            $conn->query('ALTER TABLE events MODIFY location VARCHAR(255) DEFAULT NULL');
        }
        $done = true;
    } catch (Throwable $e) {
        $done = false;
    }
    return $done;
}

/** @param array<string, mixed> $event */
function eventify_event_checkin_geo_required(array $event): bool
{
    // Always on when enabled: events without a pin fall back to the campus location.
    return eventify_checkin_config_geo_when_pinned();
}

/** @param array<string, mixed> $event */
function eventify_event_checkin_rsvp_required(array $event): bool
{
    if (array_key_exists('checkin_require_rsvp', $event) && $event['checkin_require_rsvp'] !== null && $event['checkin_require_rsvp'] !== '') {
        return (int) $event['checkin_require_rsvp'] === 1;
    }
    return eventify_checkin_config_require_rsvp();
}

function eventify_activity_checkin_require_session_rsvp(): bool
{
    return !defined('EVENTIFY_ACTIVITY_CHECKIN_REQUIRE_SESSION_RSVP') || EVENTIFY_ACTIVITY_CHECKIN_REQUIRE_SESSION_RSVP;
}

/**
 * @param array<string, mixed> $session
 */
function eventify_session_checkin_requires_rsvp(array $session): bool
{
    return eventify_activity_checkin_require_session_rsvp()
        && eventify_session_requires_activity_rsvp($session);
}

function eventify_student_has_session_rsvp(mysqli $conn, int $userId, int $sessionId): bool
{
    if ($userId < 1 || $sessionId < 1) {
        return false;
    }
    if (!function_exists('eventify_session_rsvps_table_exists')) {
        require_once __DIR__ . '/event_day_sessions.php';
    }
    if (!eventify_session_rsvps_table_exists($conn)) {
        return true;
    }
    $stmt = $conn->prepare('SELECT id FROM event_day_session_rsvps WHERE session_id = ? AND user_id = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ii', $sessionId, $userId);
    $stmt->execute();
    $ok = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $ok;
}

function eventify_student_has_event_registration(mysqli $conn, int $userId, int $eventId): bool
{
    if ($userId < 1 || $eventId < 1) {
        return false;
    }
    $stmt = $conn->prepare('SELECT id FROM registrations WHERE user_id = ? AND event_id = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ii', $userId, $eventId);
    $stmt->execute();
    $ok = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $ok;
}

/**
 * Whether a student may join hub activities (RSVP / check-in).
 * RSVP mode: registrations row. Paid ticket: valid ticket (syncs registration). Open: no main signup needed.
 *
 * @param array<string, mixed>|null $event
 */
function eventify_student_has_main_event_access(mysqli $conn, int $userId, int $eventId, ?array $event = null): bool
{
    if ($userId < 1 || $eventId < 1) {
        return false;
    }
    if ($event === null) {
        $st = $conn->prepare('SELECT registration_mode FROM events WHERE id = ? LIMIT 1');
        if (!$st) {
            return false;
        }
        $st->bind_param('i', $eventId);
        $st->execute();
        $event = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$event) {
            return false;
        }
    }
    require_once __DIR__ . '/event_ticketing.php';
    $mode = eventify_event_registration_mode($event);
    if ($mode === 'open') {
        return true;
    }
    if (eventify_student_has_event_registration($conn, $userId, $eventId)) {
        return true;
    }
    if ($mode === 'paid_ticket' && eventify_ticketing_ensure_schema($conn)) {
        $t = $conn->prepare("SELECT id FROM event_tickets WHERE user_id = ? AND event_id = ? AND status = 'valid' LIMIT 1");
        if ($t) {
            $t->bind_param('ii', $userId, $eventId);
            $t->execute();
            $hasTicket = (bool) $t->get_result()->fetch_assoc();
            $t->close();
            if ($hasTicket) {
                eventify_ensure_registration_for_ticket_holder($conn, $userId, $eventId);
                return true;
            }
        }
    }
    return false;
}

/**
 * @param array<string, mixed> $post
 * @return array{ok: bool, error?: string, geo_lat: ?float, geo_lng: ?float, geo_accuracy: ?float, device_hash: string}
 */
function eventify_checkin_parse_security_post(array $post, bool $geoRequired): array
{
    $device_hash = trim((string) ($post['device_hash'] ?? ''));
    $geo_lat_raw = trim((string) ($post['geo_lat'] ?? ''));
    $geo_lng_raw = trim((string) ($post['geo_lng'] ?? ''));
    $geo_accuracy_raw = trim((string) ($post['geo_accuracy'] ?? ''));
    $geo_ts_raw = trim((string) ($post['geo_ts'] ?? ''));

    $geo_lat = is_numeric($geo_lat_raw) ? (float) $geo_lat_raw : null;
    $geo_lng = is_numeric($geo_lng_raw) ? (float) $geo_lng_raw : null;
    $geo_accuracy = is_numeric($geo_accuracy_raw) ? (float) $geo_accuracy_raw : null;
    $geo_ts = ctype_digit($geo_ts_raw) ? (int) $geo_ts_raw : 0;
    $server_now_ms = (int) round(microtime(true) * 1000);

    if ($device_hash === '' || strlen($device_hash) < 16) {
        return ['ok' => false, 'error' => 'Device verification failed. Please use a modern browser and try again.', 'geo_lat' => $geo_lat, 'geo_lng' => $geo_lng, 'geo_accuracy' => $geo_accuracy, 'device_hash' => ''];
    }
    if ($geoRequired && ($geo_lat === null || $geo_lng === null)) {
        return ['ok' => false, 'error' => 'Attendance not recorded: your location is needed to confirm you are at the venue. Allow location access for this site, then scan the QR again.', 'geo_lat' => $geo_lat, 'geo_lng' => $geo_lng, 'geo_accuracy' => $geo_accuracy, 'device_hash' => $device_hash];
    }
    if ($geoRequired && ($geo_ts <= 0 || abs($server_now_ms - $geo_ts) > 120000)) {
        return ['ok' => false, 'error' => 'Your location reading is too old. Reload this page so your phone checks your location again, then tap Confirm.', 'geo_lat' => $geo_lat, 'geo_lng' => $geo_lng, 'geo_accuracy' => $geo_accuracy, 'device_hash' => $device_hash];
    }
    if ($geoRequired && $geo_accuracy !== null && $geo_accuracy > 2000) {
        return ['ok' => false, 'error' => 'Your phone could not pinpoint your location. Turn on Wi-Fi and GPS, move near a window or outside, then try again.', 'geo_lat' => $geo_lat, 'geo_lng' => $geo_lng, 'geo_accuracy' => $geo_accuracy, 'device_hash' => $device_hash];
    }

    return ['ok' => true, 'geo_lat' => $geo_lat, 'geo_lng' => $geo_lng, 'geo_accuracy' => $geo_accuracy, 'device_hash' => $device_hash];
}

/**
 * @return array{ok: bool, error?: string}
 */
function eventify_checkin_validate_geofence(?float $geo_lat, ?float $geo_lng, ?float $venue_lat, ?float $venue_lng, bool $geoRequired): array
{
    if (!$geoRequired || $venue_lat === null || $venue_lng === null) {
        return ['ok' => true];
    }
    if ($geo_lat === null || $geo_lng === null) {
        return ['ok' => false, 'error' => 'Attendance not recorded: your location is needed to confirm you are at the venue. Allow location access for this site, then scan the QR again.'];
    }
    $distance_m = eventify_haversine_distance_m($geo_lat, $geo_lng, $venue_lat, $venue_lng);
    $radius_m = eventify_checkin_geo_radius_m();
    if ($distance_m > $radius_m) {
        $fmt = static function (float $m): string {
            return $m >= 1000 ? number_format($m / 1000, 1) . ' km' : (string) (int) round($m) . ' m';
        };
        return ['ok' => false, 'error' => 'Attendance not recorded: you are about ' . $fmt($distance_m)
            . ' away from the venue. You can only confirm attendance within ' . $fmt($radius_m)
            . ' of the event location. Go to the venue and scan the QR again.'];
    }
    return ['ok' => true];
}

function eventify_checkin_ensure_main_device_locks_table(mysqli $conn): void
{
    $conn->query("
        CREATE TABLE IF NOT EXISTS event_checkin_device_locks (
          id INT AUTO_INCREMENT PRIMARY KEY,
          event_id INT NOT NULL,
          user_id INT NOT NULL,
          device_hash VARCHAR(128) NOT NULL,
          first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          last_lat DECIMAL(10,7) NULL DEFAULT NULL,
          last_lng DECIMAL(10,7) NULL DEFAULT NULL,
          last_accuracy FLOAT NULL DEFAULT NULL,
          last_geo_at DATETIME NULL DEFAULT NULL,
          UNIQUE KEY uniq_event_device (event_id, device_hash),
          KEY idx_event_user (event_id, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

function eventify_checkin_ensure_session_device_locks_table(mysqli $conn): void
{
    $conn->query("
        CREATE TABLE IF NOT EXISTS event_session_checkin_device_locks (
          id INT AUTO_INCREMENT PRIMARY KEY,
          session_id INT NOT NULL,
          user_id INT NOT NULL,
          device_hash VARCHAR(128) NOT NULL,
          first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          last_lat DECIMAL(10,7) NULL DEFAULT NULL,
          last_lng DECIMAL(10,7) NULL DEFAULT NULL,
          last_accuracy FLOAT NULL DEFAULT NULL,
          last_geo_at DATETIME NULL DEFAULT NULL,
          UNIQUE KEY uniq_session_device (session_id, device_hash),
          KEY idx_session_user (session_id, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

function eventify_checkin_device_lock_today(): string
{
    $tzName = defined('EVENTIFY_APP_TIMEZONE') ? (string) EVENTIFY_APP_TIMEZONE : 'Asia/Manila';
    try {
        return (new DateTimeImmutable('now', new DateTimeZone($tzName)))->format('Y-m-d');
    } catch (Throwable $e) {
        return date('Y-m-d');
    }
}

function eventify_checkin_device_lock_is_stale(?array $lockRow, string $today): bool
{
    if (!$lockRow) {
        return false;
    }
    $seen = trim((string) ($lockRow['last_seen_at'] ?? ''));
    if ($seen === '') {
        $seen = trim((string) ($lockRow['first_seen_at'] ?? ''));
    }
    $lockDay = substr($seen, 0, 10);
    return $lockDay !== '' && $lockDay < $today;
}

/**
 * @return array{ok: bool, error?: string}
 */
function eventify_checkin_apply_main_device_lock(
    mysqli $conn,
    int $eventId,
    int $userId,
    string $deviceHash,
    ?float $geoLat,
    ?float $geoLng,
    ?float $geoAccuracy
): array {
    eventify_checkin_ensure_main_device_locks_table($conn);
    $today = eventify_checkin_device_lock_today();
    $lockSel = $conn->prepare('SELECT user_id, first_seen_at, last_seen_at FROM event_checkin_device_locks WHERE event_id = ? AND device_hash = ? LIMIT 1 FOR UPDATE');
    if (!$lockSel) {
        return ['ok' => false, 'error' => 'Security lock check failed.'];
    }
    $lockSel->bind_param('is', $eventId, $deviceHash);
    $lockSel->execute();
    $lockRow = $lockSel->get_result()->fetch_assoc();
    $lockSel->close();

    $stale = eventify_checkin_device_lock_is_stale($lockRow, $today);
    if ($lockRow && !$stale && (int) $lockRow['user_id'] !== $userId) {
        return ['ok' => false, 'error' => 'This device already checked in another account for this event today.'];
    }

    if ($lockRow) {
        $updLock = $conn->prepare('UPDATE event_checkin_device_locks SET user_id = ?, last_seen_at = NOW(), last_lat = ?, last_lng = ?, last_accuracy = ?, last_geo_at = NOW() WHERE event_id = ? AND device_hash = ?');
        if (!$updLock) {
            return ['ok' => false, 'error' => 'Could not update device lock.'];
        }
        $updLock->bind_param('idddis', $userId, $geoLat, $geoLng, $geoAccuracy, $eventId, $deviceHash);
        $updLock->execute();
        $updLock->close();
    } else {
        $insLock = $conn->prepare('INSERT INTO event_checkin_device_locks (event_id, user_id, device_hash, last_lat, last_lng, last_accuracy, last_geo_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
        if (!$insLock) {
            return ['ok' => false, 'error' => 'Could not create device lock.'];
        }
        $insLock->bind_param('iisddd', $eventId, $userId, $deviceHash, $geoLat, $geoLng, $geoAccuracy);
        $insLock->execute();
        $insLock->close();
    }
    return ['ok' => true];
}

/**
 * @return array{ok: bool, error?: string}
 */
function eventify_checkin_apply_session_device_lock(
    mysqli $conn,
    int $sessionId,
    int $userId,
    string $deviceHash,
    ?float $geoLat,
    ?float $geoLng,
    ?float $geoAccuracy
): array {
    eventify_checkin_ensure_session_device_locks_table($conn);
    $today = eventify_checkin_device_lock_today();
    $lockSel = $conn->prepare('SELECT user_id, first_seen_at, last_seen_at FROM event_session_checkin_device_locks WHERE session_id = ? AND device_hash = ? LIMIT 1 FOR UPDATE');
    if (!$lockSel) {
        return ['ok' => false, 'error' => 'Security lock check failed.'];
    }
    $lockSel->bind_param('is', $sessionId, $deviceHash);
    $lockSel->execute();
    $lockRow = $lockSel->get_result()->fetch_assoc();
    $lockSel->close();

    $stale = eventify_checkin_device_lock_is_stale($lockRow, $today);
    if ($lockRow && !$stale && (int) $lockRow['user_id'] !== $userId) {
        return ['ok' => false, 'error' => 'This device already checked in another account for this activity today.'];
    }

    if ($lockRow) {
        $updLock = $conn->prepare('UPDATE event_session_checkin_device_locks SET user_id = ?, last_seen_at = NOW(), last_lat = ?, last_lng = ?, last_accuracy = ?, last_geo_at = NOW() WHERE session_id = ? AND device_hash = ?');
        if (!$updLock) {
            return ['ok' => false, 'error' => 'Could not update device lock.'];
        }
        $updLock->bind_param('idddis', $userId, $geoLat, $geoLng, $geoAccuracy, $sessionId, $deviceHash);
        $updLock->execute();
        $updLock->close();
    } else {
        $insLock = $conn->prepare('INSERT INTO event_session_checkin_device_locks (session_id, user_id, device_hash, last_lat, last_lng, last_accuracy, last_geo_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
        if (!$insLock) {
            return ['ok' => false, 'error' => 'Could not create device lock.'];
        }
        $insLock->bind_param('iisddd', $sessionId, $userId, $deviceHash, $geoLat, $geoLng, $geoAccuracy);
        $insLock->execute();
        $insLock->close();
    }
    return ['ok' => true];
}

/**
 * @param array<string, mixed> $session Row with latitude/longitude; optional event_latitude/event_longitude
 */
function eventify_session_checkin_geo_required(array $session): bool
{
    // Always on when enabled: activity pin → main event pin → campus location.
    return eventify_checkin_config_geo_when_pinned();
}

/** @return array{lat: float, lng: float} Activity pin, else main event pin, else campus. */
function eventify_session_checkin_venue_coords(array $session): array
{
    $coords = eventify_checkin_coords_from_row($session);
    if ($coords['lat'] !== null && $coords['lng'] !== null) {
        return ['lat' => (float) $coords['lat'], 'lng' => (float) $coords['lng']];
    }
    return eventify_event_checkin_venue_coords([
        'latitude' => $session['event_latitude'] ?? null,
        'longitude' => $session['event_longitude'] ?? null,
    ]);
}

/**
 * Whether the student still needs main-event signup (RSVP or ticket) before QR check-in.
 *
 * @param array<string, mixed> $event
 * @return array{needed: bool, message: string, action: string}
 */
function eventify_checkin_main_signup_gap(mysqli $conn, array $event, int $userId): array
{
    $eventId = (int) ($event['id'] ?? 0);
    if ($eventId < 1 || $userId < 1) {
        return ['needed' => false, 'message' => '', 'action' => ''];
    }
    if (!eventify_event_checkin_rsvp_required($event)) {
        return ['needed' => false, 'message' => '', 'action' => ''];
    }
    if (eventify_student_has_main_event_access($conn, $userId, $eventId, $event)) {
        return ['needed' => false, 'message' => '', 'action' => ''];
    }

    require_once __DIR__ . '/event_ticketing.php';
    $mode = eventify_event_registration_mode($event);
    if ($mode === 'paid_ticket') {
        return [
            'needed' => true,
            'message' => 'Ticket required. Purchase a ticket first, then return here to check in.',
            'action' => 'ticket',
        ];
    }
    if ($mode === 'open') {
        return ['needed' => false, 'message' => '', 'action' => ''];
    }

    return [
        'needed' => true,
        'message' => 'RSVP required. Register for this event on your dashboard first, then return here to check in.',
        'action' => 'rsvp',
    ];
}

function eventify_attendance_session_has_checkout_column(mysqli $conn): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = false;
    try {
        $col = $conn->query("SHOW COLUMNS FROM event_day_session_attendance WHERE Field = 'checked_out_at'");
        if ($col && $col->num_rows >= 1) {
            $cache = true;
        }
    } catch (Throwable $e) {
        // keep false
    }
    return $cache;
}

/** @return array{time_in: ?string, time_out: ?string, present: bool} */
function eventify_load_main_event_attendance(mysqli $conn, int $userId, int $eventId): array
{
    $empty = ['time_in' => null, 'time_out' => null, 'present' => false];
    if ($userId < 1 || $eventId < 1) {
        return $empty;
    }
    $stmt = $conn->prepare("SELECT time_in, time_out, status FROM registrations WHERE user_id = ? AND event_id = ? LIMIT 1");
    if (!$stmt) {
        return $empty;
    }
    $stmt->bind_param('ii', $userId, $eventId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return $empty;
    }
    return [
        'time_in' => !empty($row['time_in']) ? (string) $row['time_in'] : null,
        'time_out' => !empty($row['time_out']) ? (string) $row['time_out'] : null,
        'present' => strtolower((string) ($row['status'] ?? '')) === 'present' || !empty($row['time_in']),
    ];
}

/** @return array{checked_in_at: ?string, checked_out_at: ?string, present: bool} */
function eventify_load_session_attendance(mysqli $conn, int $userId, int $sessionId): array
{
    $empty = ['checked_in_at' => null, 'checked_out_at' => null, 'present' => false];
    if ($userId < 1 || $sessionId < 1) {
        return $empty;
    }
    try {
        $chk = $conn->query("SHOW TABLES LIKE 'event_day_session_attendance'");
        if (!$chk || $chk->num_rows < 1) {
            return $empty;
        }
    } catch (Throwable $e) {
        return $empty;
    }
    $hasOut = eventify_attendance_session_has_checkout_column($conn);
    $sql = $hasOut
        ? 'SELECT checked_in_at, checked_out_at FROM event_day_session_attendance WHERE user_id = ? AND session_id = ? LIMIT 1'
        : 'SELECT checked_in_at FROM event_day_session_attendance WHERE user_id = ? AND session_id = ? LIMIT 1';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $empty;
    }
    $stmt->bind_param('ii', $userId, $sessionId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return $empty;
    }
    return [
        'checked_in_at' => !empty($row['checked_in_at']) ? (string) $row['checked_in_at'] : null,
        'checked_out_at' => $hasOut && !empty($row['checked_out_at']) ? (string) $row['checked_out_at'] : null,
        'present' => !empty($row['checked_in_at']),
    ];
}

/** @return array{open: string, close: string} */
function eventify_entity_checkout_bounds(array $entity, ?string $dayYmd = null): array
{
    $end = trim((string) ($entity['end_time'] ?? ''));
    if (!empty($entity['end_time_na'])) {
        $end = '';
    }
    $checkoutEnd = trim((string) ($entity['checkout_end_time'] ?? ''));
    return [
        'open' => $end,
        'close' => $checkoutEnd !== '' ? $checkoutEnd : '23:59',
    ];
}

function eventify_entity_checkout_window_open(array $entity, ?DateTimeInterface $now = null, ?string $dayYmd = null): bool
{
    if (!function_exists('eventify_calendar_app_timezone')) {
        require_once __DIR__ . '/event_calendar.php';
    }
    $tz = eventify_calendar_app_timezone();
    $now = $now instanceof DateTimeInterface
        ? DateTimeImmutable::createFromInterface($now)->setTimezone($tz)
        : new DateTimeImmutable('now', $tz);
    $dayYmd = $dayYmd ?? $now->format('Y-m-d');

    $scheduleDays = $entity['schedule_days'] ?? null;
    if (is_array($scheduleDays) && $scheduleDays !== []) {
        foreach ($scheduleDays as $day) {
            if (!is_array($day)) {
                continue;
            }
            $ymd = substr(trim((string) ($day['schedule_date'] ?? '')), 0, 10);
            if ($ymd === $dayYmd) {
                $entity = array_merge($entity, $day);
                break;
            }
        }
    } elseif (!empty($entity['date'])) {
        $startYmd = substr(trim((string) $entity['date']), 0, 10);
        $endYmd = function_exists('eventify_event_resolve_end_date')
            ? eventify_event_resolve_end_date($entity)
            : $startYmd;
        if ($dayYmd < $startYmd || ($endYmd !== '' && $dayYmd > $endYmd)) {
            return false;
        }
    }

    if (!function_exists('eventify_session_datetime')) {
        require_once __DIR__ . '/event_day_sessions.php';
    }
    $bounds = eventify_entity_checkout_bounds($entity, $dayYmd);
    $openTime = $bounds['open'];
    $closeTime = $bounds['close'];
    if ($openTime === '') {
        return true;
    }
    $openDt = eventify_session_datetime($dayYmd, $openTime);
    if ($openDt instanceof DateTimeImmutable && $now < $openDt) {
        return false;
    }
    if ($closeTime !== '') {
        $closeDt = eventify_session_datetime($dayYmd, $closeTime);
        if ($closeDt instanceof DateTimeImmutable && $now > $closeDt) {
            return false;
        }
    }
    return true;
}

/**
 * @return array{phase: string, label: string, can_submit: bool, message: string}
 *   phase: unavailable|time_in|time_out|complete
 */
function eventify_main_event_scan_state(array $event, array $attendance, ?DateTimeInterface $now = null): array
{
    if (!eventify_entity_uses_time_in_out($event)) {
        if (!function_exists('eventify_event_allows_checkin')) {
            require_once __DIR__ . '/event_calendar.php';
        }
        if ($attendance['present']) {
            return ['phase' => 'complete', 'label' => 'Checked in', 'can_submit' => false, 'message' => 'You already confirmed attendance for this event.'];
        }
        if (!eventify_event_allows_checkin($event, $now)) {
            $reason = eventify_event_checkin_unavailable_reason($event, $now) ?? 'Check-in is not available right now.';
            return ['phase' => 'unavailable', 'label' => 'Check-in', 'can_submit' => false, 'message' => $reason];
        }
        return ['phase' => 'time_in', 'label' => 'Confirm attendance', 'can_submit' => true, 'message' => 'Tap confirm to record your attendance.'];
    }

    if ($attendance['time_in'] && $attendance['time_out']) {
        return ['phase' => 'complete', 'label' => 'Complete', 'can_submit' => false, 'message' => 'Your time in and time out are recorded.'];
    }
    if (!$attendance['time_in']) {
        if (!function_exists('eventify_event_allows_checkin')) {
            require_once __DIR__ . '/event_calendar.php';
        }
        if (!eventify_event_allows_checkin($event, $now)) {
            $reason = eventify_event_checkin_unavailable_reason($event, $now) ?? 'Time-in is not available right now.';
            return ['phase' => 'unavailable', 'label' => 'Time in', 'can_submit' => false, 'message' => $reason];
        }
        return ['phase' => 'time_in', 'label' => 'Confirm time in', 'can_submit' => true, 'message' => 'Scan to record when you arrive. Scan again later to time out.'];
    }
    if (!eventify_entity_checkout_window_open($event, $now)) {
        return [
            'phase' => 'unavailable',
            'label' => 'Time out',
            'can_submit' => false,
            'message' => 'You are timed in. Time-out opens when the event ends (or at the configured time-out window).',
        ];
    }
    return ['phase' => 'time_out', 'label' => 'Confirm time out', 'can_submit' => true, 'message' => 'You are timed in. Tap confirm to record your departure.'];
}

/**
 * @return array{phase: string, label: string, can_submit: bool, message: string}
 */
function eventify_activity_scan_state(array $session, array $attendance, ?DateTimeInterface $now = null): array
{
    if (!function_exists('eventify_session_allows_checkin')) {
        require_once __DIR__ . '/event_day_sessions.php';
    }
    if (!eventify_entity_uses_time_in_out($session)) {
        if ($attendance['present']) {
            return ['phase' => 'complete', 'label' => 'Checked in', 'can_submit' => false, 'message' => 'You already checked in to this activity.'];
        }
        if (!eventify_session_allows_checkin($session, $now)) {
            $details = eventify_session_checkin_student_details($session, $now);
            return ['phase' => 'unavailable', 'label' => 'Check-in', 'can_submit' => false, 'message' => $details['reason'] ?? 'Check-in is not available right now.'];
        }
        return ['phase' => 'time_in', 'label' => 'Confirm check-in', 'can_submit' => true, 'message' => 'Tap confirm to record your attendance.'];
    }

    if ($attendance['checked_in_at'] && $attendance['checked_out_at']) {
        return ['phase' => 'complete', 'label' => 'Complete', 'can_submit' => false, 'message' => 'Your time in and time out are recorded for this activity.'];
    }
    if (!$attendance['checked_in_at']) {
        if (!eventify_session_allows_checkin($session, $now)) {
            $details = eventify_session_checkin_student_details($session, $now);
            return ['phase' => 'unavailable', 'label' => 'Time in', 'can_submit' => false, 'message' => $details['reason'] ?? 'Time-in is not available right now.'];
        }
        return ['phase' => 'time_in', 'label' => 'Confirm time in', 'can_submit' => true, 'message' => 'Scan to record when you arrive. Scan again later to time out.'];
    }
    $dayYmd = substr(trim((string) ($session['schedule_date'] ?? '')), 0, 10);
    if (!eventify_entity_checkout_window_open($session, $now, $dayYmd)) {
        return [
            'phase' => 'unavailable',
            'label' => 'Time out',
            'can_submit' => false,
            'message' => 'You are timed in. Time-out opens when the activity ends (or at the configured time-out window).',
        ];
    }
    return ['phase' => 'time_out', 'label' => 'Confirm time out', 'can_submit' => true, 'message' => 'You are timed in. Tap confirm to record your departure.'];
}

/**
 * Process main event check-in POST (device lock + present status).
 *
 * @param array<string, mixed> $event
 * @param array<string, mixed> $post
 * @return array{ok: bool, error?: string}
 */
function eventify_process_main_event_checkin(mysqli $conn, array $event, int $userId, array $post): array
{
    $eventId = (int) ($event['id'] ?? 0);
    if ($eventId < 1) {
        return ['ok' => false, 'error' => 'Invalid event.'];
    }

    if (is_file(__DIR__ . '/../../config/student_sections.php')) {
        require_once __DIR__ . '/../../config/student_sections.php';
    }
    if (function_exists('eventify_student_event_audience_gate')) {
        $aud = eventify_student_event_audience_gate($conn, $userId, $event);
        if (!$aud['ok']) {
            return ['ok' => false, 'error' => $aud['error'] ?? 'This event is not available for your account.'];
        }
    }

    $attendance = eventify_load_main_event_attendance($conn, $userId, $eventId);
    $scanState = eventify_main_event_scan_state($event, $attendance);
    if (!$scanState['can_submit']) {
        return ['ok' => false, 'error' => $scanState['message']];
    }
    $isTimeOut = $scanState['phase'] === 'time_out';

    if (!$isTimeOut) {
        $signupGap = eventify_checkin_main_signup_gap($conn, $event, $userId);
        if ($signupGap['needed']) {
            return ['ok' => false, 'error' => $signupGap['message']];
        }
    }

    $geoRequired = eventify_event_checkin_geo_required($event);
    $parsed = eventify_checkin_parse_security_post($post, $geoRequired);
    if (!$parsed['ok']) {
        return ['ok' => false, 'error' => $parsed['error'] ?? 'Check-in failed.'];
    }

    $venue = eventify_event_checkin_venue_coords($event);
    $geoVal = eventify_checkin_validate_geofence(
        $parsed['geo_lat'],
        $parsed['geo_lng'],
        $venue['lat'],
        $venue['lng'],
        $geoRequired
    );
    if (!$geoVal['ok']) {
        return $geoVal;
    }

    try {
        $conn->begin_transaction();
        $lock = eventify_checkin_apply_main_device_lock(
            $conn,
            $eventId,
            $userId,
            $parsed['device_hash'],
            $parsed['geo_lat'],
            $parsed['geo_lng'],
            $parsed['geo_accuracy']
        );
        if (!$lock['ok']) {
            throw new RuntimeException($lock['error'] ?? 'Device lock failed.');
        }

        if ($isTimeOut) {
            $stmt = $conn->prepare("UPDATE registrations SET time_out = NOW() WHERE user_id = ? AND event_id = ? AND time_in IS NOT NULL AND time_out IS NULL");
        } else {
            $stmt = $conn->prepare("INSERT INTO registrations (user_id, event_id, status, time_in) VALUES (?, ?, 'present', NOW()) ON DUPLICATE KEY UPDATE status = 'present', time_in = IF(time_in IS NULL, NOW(), time_in)");
        }
        if (!$stmt) {
            throw new RuntimeException('Could not record attendance.');
        }
        $stmt->bind_param('ii', $userId, $eventId);
        $stmt->execute();
        if ($stmt->affected_rows < 1 && $isTimeOut) {
            $stmt->close();
            throw new RuntimeException('Could not record time-out. You may have already timed out.');
        }
        $stmt->close();
        $conn->commit();
        return [
            'ok' => true,
            'action' => $isTimeOut ? 'time_out' : 'time_in',
        ];
    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $e2) {
        }
        return ['ok' => false, 'error' => $e->getMessage() !== 'Could not record attendance.' ? $e->getMessage() : 'Could not record attendance. Please try again.'];
    }
}

/**
 * @param array<string, mixed> $session
 * @return array{ok: bool, error?: string}
 */
function eventify_process_activity_checkin(mysqli $conn, array $session, int $userId, array $post): array
{
    $sessionId = (int) ($session['id'] ?? 0);
    $eventId = (int) ($session['event_id'] ?? 0);
    if ($sessionId < 1 || $eventId < 1) {
        return ['ok' => false, 'error' => 'Invalid activity.'];
    }

    if (is_file(__DIR__ . '/../../config/student_sections.php')) {
        require_once __DIR__ . '/../../config/student_sections.php';
    }
    if (function_exists('eventify_student_event_audience_gate')) {
        $parentEvent = [
            'id' => $eventId,
            'department' => $session['event_department'] ?? $session['department'] ?? null,
            'target_sections' => $session['event_target_sections'] ?? null,
        ];
        $aud = eventify_student_event_audience_gate($conn, $userId, $parentEvent);
        if (!$aud['ok']) {
            return ['ok' => false, 'error' => $aud['error'] ?? 'This event is not available for your account.'];
        }
        require_once __DIR__ . '/event_day_sessions.php';
        $sessionGate = [
            'department' => $session['department'] ?? null,
            'target_sections' => $session['target_sections'] ?? null,
        ];
        $student = eventify_load_student_audience_profile($conn, $userId);
        if (!eventify_student_may_access_session($sessionGate, $parentEvent, $student)) {
            return ['ok' => false, 'error' => 'This activity is not available for your department or class section.'];
        }
    }

    if (!eventify_student_has_main_event_access($conn, $userId, $eventId)) {
        return ['ok' => false, 'error' => 'Register for the main event (or buy a ticket) before checking in to this activity.'];
    }

    require_once __DIR__ . '/event_day_sessions.php';
    if (eventify_session_requires_ticket($session) && !eventify_student_has_session_ticket($conn, $userId, $session)) {
        return ['ok' => false, 'error' => 'Buy a ticket for this paid activity before checking in.'];
    }

    if (eventify_session_checkin_requires_rsvp($session) && !eventify_student_has_session_rsvp($conn, $userId, $sessionId)) {
        return ['ok' => false, 'error' => 'RSVP for this activity in the Activities hub first, then check in at the venue.'];
    }

    $attendance = eventify_load_session_attendance($conn, $userId, $sessionId);
    $scanState = eventify_activity_scan_state($session, $attendance);
    if (!$scanState['can_submit']) {
        return ['ok' => false, 'error' => $scanState['message']];
    }
    $isTimeOut = $scanState['phase'] === 'time_out';

    $geoRequired = eventify_session_checkin_geo_required($session);
    $parsed = eventify_checkin_parse_security_post($post, $geoRequired);
    if (!$parsed['ok']) {
        return ['ok' => false, 'error' => $parsed['error'] ?? 'Check-in failed.'];
    }

    $venue = eventify_session_checkin_venue_coords($session);
    $geoVal = eventify_checkin_validate_geofence(
        $parsed['geo_lat'],
        $parsed['geo_lng'],
        $venue['lat'],
        $venue['lng'],
        $geoRequired
    );
    if (!$geoVal['ok']) {
        return $geoVal;
    }

    try {
        $conn->begin_transaction();
        $lock = eventify_checkin_apply_session_device_lock(
            $conn,
            $sessionId,
            $userId,
            $parsed['device_hash'],
            $parsed['geo_lat'],
            $parsed['geo_lng'],
            $parsed['geo_accuracy']
        );
        if (!$lock['ok']) {
            throw new RuntimeException($lock['error'] ?? 'Device lock failed.');
        }

        eventify_events_ensure_attendance_mode($conn);
        $hasOutCol = eventify_attendance_session_has_checkout_column($conn);
        if ($isTimeOut && $hasOutCol) {
            $ins = $conn->prepare('UPDATE event_day_session_attendance SET checked_out_at = CURRENT_TIMESTAMP WHERE session_id = ? AND user_id = ? AND checked_in_at IS NOT NULL AND checked_out_at IS NULL');
        } else {
            $ins = $conn->prepare('INSERT INTO event_day_session_attendance (session_id, user_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE checked_in_at = IF(checked_in_at IS NULL, CURRENT_TIMESTAMP, checked_in_at)');
        }
        if (!$ins) {
            throw new RuntimeException('Could not record check-in.');
        }
        $ins->bind_param('ii', $sessionId, $userId);
        $ins->execute();
        if ($ins->affected_rows < 1 && $isTimeOut) {
            $ins->close();
            throw new RuntimeException('Could not record time-out. You may have already timed out.');
        }
        $ins->close();
        $conn->commit();
        return [
            'ok' => true,
            'action' => $isTimeOut ? 'time_out' : 'time_in',
        ];
    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $e2) {
        }
        return ['ok' => false, 'error' => $e->getMessage() !== 'Could not record check-in.' ? $e->getMessage() : 'Could not record check-in. Please try again.'];
    }
}

/**
 * Process paid ticket check-in with device lock + optional geofence.
 *
 * @param array<string, mixed> $ticket Row from eventify_load_ticket_by_checkin_token
 * @param array<string, mixed> $post
 * @return array{ok: bool, error?: string}
 */
function eventify_process_ticket_checkin(mysqli $conn, array $ticket, int $userId, array $post): array
{
    $ticketId = (int) ($ticket['id'] ?? 0);
    $eventId = (int) ($ticket['event_id'] ?? 0);
    if ($ticketId < 1 || $eventId < 1) {
        return ['ok' => false, 'error' => 'Invalid ticket.'];
    }
    if ($userId !== (int) ($ticket['user_id'] ?? 0)) {
        return ['ok' => false, 'error' => 'This ticket belongs to another student.'];
    }

    $event = [
        'id' => $eventId,
        'latitude' => $ticket['latitude'] ?? $ticket['event_latitude'] ?? null,
        'longitude' => $ticket['longitude'] ?? $ticket['event_longitude'] ?? null,
        'checkin_require_geo' => $ticket['checkin_require_geo'] ?? null,
    ];
    $geoRequired = eventify_event_checkin_geo_required($event);
    $parsed = eventify_checkin_parse_security_post($post, $geoRequired);
    if (!$parsed['ok']) {
        return ['ok' => false, 'error' => $parsed['error'] ?? 'Check-in failed.'];
    }

    $venue = eventify_event_checkin_venue_coords($event);
    $geoVal = eventify_checkin_validate_geofence(
        $parsed['geo_lat'],
        $parsed['geo_lng'],
        $venue['lat'],
        $venue['lng'],
        $geoRequired
    );
    if (!$geoVal['ok']) {
        return $geoVal;
    }

    try {
        $conn->begin_transaction();
        $lock = eventify_checkin_apply_main_device_lock(
            $conn,
            $eventId,
            $userId,
            $parsed['device_hash'],
            $parsed['geo_lat'],
            $parsed['geo_lng'],
            $parsed['geo_accuracy']
        );
        if (!$lock['ok']) {
            throw new RuntimeException($lock['error'] ?? 'Device lock failed.');
        }

        if (!function_exists('eventify_mark_ticket_used')) {
            require_once __DIR__ . '/event_ticketing.php';
        }
        $result = eventify_mark_ticket_used($conn, $ticketId, $userId);
        if (!$result['ok']) {
            throw new RuntimeException($result['error'] ?? 'Check-in failed.');
        }
        $conn->commit();
        return ['ok' => true];
    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $e2) {
        }
        return ['ok' => false, 'error' => $e->getMessage() !== 'Check-in failed.' ? $e->getMessage() : 'Check-in failed. Please try again.'];
    }
}

/**
 * Count main-event and activity check-ins for a student.
 *
 * @return array{events: int, activities: int, total: int}
 */
function eventify_student_attendance_counts(mysqli $conn, int $userId): array
{
    if ($userId < 1) {
        return ['events' => 0, 'activities' => 0, 'total' => 0];
    }
    $events = 0;
    $activities = 0;
    $st = $conn->prepare("SELECT COUNT(*) AS c FROM registrations WHERE user_id = ? AND status = 'present' AND time_in IS NOT NULL");
    if ($st) {
        $st->bind_param('i', $userId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $events = (int) ($row['c'] ?? 0);
        $st->close();
    }
    $chk = $conn->query("SHOW TABLES LIKE 'event_day_session_attendance'");
    if ($chk && $chk->num_rows > 0) {
        $st = $conn->prepare('SELECT COUNT(*) AS c FROM event_day_session_attendance WHERE user_id = ?');
        if ($st) {
            $st->bind_param('i', $userId);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $activities = (int) ($row['c'] ?? 0);
            $st->close();
        }
    }
    return [
        'events' => $events,
        'activities' => $activities,
        'total' => $events + $activities,
    ];
}

/**
 * Load unified attendance history (main event QR + activity QR check-ins).
 *
 * @return array{items: list<array<string, mixed>>, counts: array{events: int, activities: int, total: int}}
 */
function eventify_load_student_attendance_history(mysqli $conn, int $userId, string $filter = 'all', int $limit = 150): array
{
    $counts = eventify_student_attendance_counts($conn, $userId);
    if ($userId < 1) {
        return ['items' => [], 'counts' => $counts];
    }

    $filter = strtolower(trim($filter));
    if (!in_array($filter, ['all', 'events', 'activities'], true)) {
        $filter = 'all';
    }
    $limit = max(1, min(500, $limit));

    $items = [];

    if (!function_exists('eventify_attendance_proof_ensure_columns')) {
        $proofLib = __DIR__ . '/attendance_proof.php';
        if (is_file($proofLib)) {
            require_once $proofLib;
        }
    }
    if (function_exists('eventify_attendance_proof_ensure_columns')) {
        eventify_attendance_proof_ensure_columns($conn);
    }
    $eventHasProof = function_exists('eventify_attendance_proof_column_exists')
        && eventify_attendance_proof_column_exists($conn, 'registrations');
    $actHasProof = function_exists('eventify_attendance_proof_column_exists')
        && eventify_attendance_proof_column_exists($conn, 'event_day_session_attendance');

    if ($filter === 'all' || $filter === 'events') {
        $eventProofSql = $eventHasProof ? ', r.proof_photo' : '';
        $st = $conn->prepare(
            "SELECT r.id AS record_id, r.time_in AS checked_in_at, r.event_id{$eventProofSql},
                    e.title AS event_title, e.date AS event_date, e.location AS event_location, e.status AS event_status
             FROM registrations r
             INNER JOIN events e ON e.id = r.event_id
             WHERE r.user_id = ? AND r.status = 'present' AND r.time_in IS NOT NULL
             ORDER BY r.time_in DESC
             LIMIT ?"
        );
        if ($st) {
            $st->bind_param('ii', $userId, $limit);
            $st->execute();
            $res = $st->get_result();
            while ($row = $res->fetch_assoc()) {
                $items[] = [
                    'kind' => 'event',
                    'record_id' => (int) ($row['record_id'] ?? 0),
                    'title' => (string) ($row['event_title'] ?? 'Event'),
                    'event_title' => (string) ($row['event_title'] ?? 'Event'),
                    'event_id' => (int) ($row['event_id'] ?? 0),
                    'session_id' => 0,
                    'location' => (string) ($row['event_location'] ?? ''),
                    'schedule_date' => substr((string) ($row['event_date'] ?? ''), 0, 10),
                    'checked_in_at' => (string) ($row['checked_in_at'] ?? ''),
                    'event_status' => (string) ($row['event_status'] ?? ''),
                    'proof_photo' => (string) ($row['proof_photo'] ?? ''),
                ];
            }
            $st->close();
        }
    }

    if ($filter === 'all' || $filter === 'activities') {
        if (!function_exists('eventify_event_day_sessions_ensure_enhanced')) {
            require_once __DIR__ . '/event_day_sessions.php';
        }
        eventify_event_day_sessions_ensure_enhanced($conn);
        $actProofSql = $actHasProof ? ', a.proof_photo' : '';
        $st = $conn->prepare(
            "SELECT a.id AS record_id, a.checked_in_at{$actProofSql}, s.id AS session_id, s.title AS activity_title,
                    s.schedule_date, s.start_time, s.end_time, s.location AS activity_location,
                    e.id AS event_id, e.title AS event_title, e.status AS event_status
             FROM event_day_session_attendance a
             INNER JOIN event_day_sessions s ON s.id = a.session_id
             INNER JOIN events e ON e.id = s.event_id
             WHERE a.user_id = ?
             ORDER BY a.checked_in_at DESC
             LIMIT ?"
        );
        if ($st) {
            $st->bind_param('ii', $userId, $limit);
            $st->execute();
            $res = $st->get_result();
            while ($row = $res->fetch_assoc()) {
                $items[] = [
                    'kind' => 'activity',
                    'record_id' => (int) ($row['record_id'] ?? 0),
                    'title' => (string) ($row['activity_title'] ?? 'Activity'),
                    'event_title' => (string) ($row['event_title'] ?? ''),
                    'event_id' => (int) ($row['event_id'] ?? 0),
                    'session_id' => (int) ($row['session_id'] ?? 0),
                    'location' => (string) ($row['activity_location'] ?? ''),
                    'schedule_date' => substr((string) ($row['schedule_date'] ?? ''), 0, 10),
                    'start_time' => (string) ($row['start_time'] ?? ''),
                    'end_time' => (string) ($row['end_time'] ?? ''),
                    'checked_in_at' => (string) ($row['checked_in_at'] ?? ''),
                    'event_status' => (string) ($row['event_status'] ?? ''),
                    'proof_photo' => (string) ($row['proof_photo'] ?? ''),
                ];
            }
            $st->close();
        }
    }

    usort($items, static function (array $a, array $b): int {
        return strcmp((string) ($b['checked_in_at'] ?? ''), (string) ($a['checked_in_at'] ?? ''));
    });
    if ($filter === 'all') {
        $items = array_slice($items, 0, $limit);
    }

    return ['items' => $items, 'counts' => $counts];
}
