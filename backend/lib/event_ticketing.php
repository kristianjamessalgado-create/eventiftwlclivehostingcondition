<?php

/**
 * Paid event ticketing (e.g. pageant) — ticket types, orders, digital passes.
 */

require_once __DIR__ . '/web_push.php';

function eventify_ticketing_tables_exist(mysqli $conn): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = false;
    try {
        $res = $conn->query("SHOW TABLES LIKE 'event_tickets'");
        if ($res && $res->num_rows >= 1) {
            $cache = true;
        }
    } catch (Throwable $e) {
        $cache = false;
    }
    return $cache;
}

function eventify_ticketing_ensure_schema(mysqli $conn): bool
{
    if (eventify_ticketing_tables_exist($conn)) {
        eventify_ticketing_ensure_registration_mode_column($conn);
        return true;
    }
    $sql = @file_get_contents(__DIR__ . '/../../migrations/add_paid_ticketing.sql');
    if ($sql === false || $sql === '') {
        return false;
    }
    try {
        $parts = preg_split('/;\s*\n/', $sql);
        foreach ($parts as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || stripos($stmt, '--') === 0) {
                continue;
            }
            @$conn->query($stmt);
        }
        if ($conn->more_results()) {
            while ($conn->more_results() && $conn->next_result()) {
            }
        }
    } catch (Throwable $e) {
        return false;
    }
    eventify_ticketing_ensure_registration_mode_column($conn);
    return eventify_ticketing_tables_exist($conn);
}

function eventify_ticketing_ensure_registration_mode_column(mysqli $conn): void
{
    try {
        $col = $conn->query("SHOW COLUMNS FROM events WHERE Field = 'registration_mode'");
        if ($col && $col->num_rows < 1) {
            @$conn->query("ALTER TABLE events ADD COLUMN registration_mode VARCHAR(20) NOT NULL DEFAULT 'rsvp'");
        }
    } catch (Throwable $e) {
        /* ignore */
    }
}

/** @return 'rsvp'|'paid_ticket'|'open' */
function eventify_event_registration_mode(array $event): string
{
    $mode = strtolower(trim((string) ($event['registration_mode'] ?? 'rsvp')));
    if (in_array($mode, ['rsvp', 'paid_ticket', 'open'], true)) {
        return $mode;
    }
    return 'rsvp';
}

/** @return 'rsvp'|'paid_ticket'|'open' */
function eventify_parse_registration_mode_from_request(array $post): string
{
    return eventify_event_registration_mode(['registration_mode' => $post['registration_mode'] ?? 'open']);
}

/**
 * UI label + Bootstrap badge classes for registration mode (organizer/student/admin/multimedia).
 *
 * @return array{mode: string, label: string, badge_class: string}
 */
function eventify_registration_mode_ui(array $event): array
{
    $mode = eventify_event_registration_mode($event);
    if ($mode === 'paid_ticket') {
        return ['mode' => $mode, 'label' => 'Paid tickets', 'badge_class' => 'bg-warning text-dark'];
    }
    if ($mode === 'open') {
        return ['mode' => $mode, 'label' => 'Open entry', 'badge_class' => 'bg-info text-dark'];
    }
    return ['mode' => $mode, 'label' => 'Free RSVP', 'badge_class' => 'bg-primary'];
}

function eventify_event_uses_paid_ticketing(array $event): bool
{
    return eventify_event_registration_mode($event) === 'paid_ticket';
}

function eventify_event_has_sellable_ticket_types(mysqli $conn, int $eventId): bool
{
    if ($eventId < 1 || !eventify_ticketing_ensure_schema($conn)) {
        return false;
    }
    $types = eventify_load_ticket_types_for_event($conn, $eventId, true);
    return $types !== [];
}

/** Whole-event paid shop or hub activity tickets on a free-RSVP parent event. */
function eventify_event_allows_ticket_shop(mysqli $conn, array $event): bool
{
    if (eventify_event_uses_paid_ticketing($event)) {
        return true;
    }
    return eventify_event_has_sellable_ticket_types($conn, (int) ($event['id'] ?? 0));
}

function eventify_payment_mode(): string
{
    if (defined('EVENTIFY_PAYMENT_MODE')) {
        $m = strtolower(trim((string) EVENTIFY_PAYMENT_MODE));
        if (in_array($m, ['simulate', 'gcash_manual', 'both'], true)) {
            return $m;
        }
    }
    return 'both';
}

function eventify_format_ticket_price(float $amount): string
{
    return '₱' . number_format($amount, 2);
}

function eventify_generate_order_ref(): string
{
    return 'ORD-' . strtoupper(bin2hex(random_bytes(4))) . '-' . date('ymd');
}

function eventify_generate_ticket_code(): string
{
    return 'TKT-' . strtoupper(bin2hex(random_bytes(4)));
}

/** @return list<array<string, mixed>> */
function eventify_load_ticket_types_for_event(mysqli $conn, int $eventId, bool $activeOnly = true): array
{
    if ($eventId < 1 || !eventify_ticketing_ensure_schema($conn)) {
        return [];
    }
    $sql = 'SELECT * FROM event_ticket_types WHERE event_id = ?';
    if ($activeOnly) {
        $sql .= ' AND is_active = 1';
    }
    $sql .= ' ORDER BY sort_order ASC, price ASC, id ASC';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows ?: [];
}

/**
 * Create a new ticket type for an event.
 *
 * @param array{name?: string, description?: string, price?: float|string, quantity?: int|string|null, sort_order?: int} $fields
 * @return array{ok: bool, error?: string, id?: int}
 */
function eventify_add_ticket_type(mysqli $conn, int $eventId, array $fields): array
{
    if ($eventId < 1 || !eventify_ticketing_ensure_schema($conn)) {
        return ['ok' => false, 'error' => 'Invalid event.'];
    }
    $name = trim((string) ($fields['name'] ?? ''));
    if ($name === '') {
        return ['ok' => false, 'error' => 'Ticket name is required.'];
    }
    if (mb_strlen($name) > 100) {
        $name = mb_substr($name, 0, 100);
    }
    $desc = trim((string) ($fields['description'] ?? ''));
    $price = (float) ($fields['price'] ?? 0);
    if ($price < 0) {
        $price = 0;
    }
    $qtyRaw = $fields['quantity'] ?? null;
    if (is_string($qtyRaw)) {
        $qtyRaw = trim($qtyRaw);
    }
    $qty = ($qtyRaw === null || $qtyRaw === '') ? null : max(0, (int) $qtyRaw);
    $sortOrder = max(0, (int) ($fields['sort_order'] ?? 0));

    if ($qty === null) {
        $ins = $conn->prepare(
            'INSERT INTO event_ticket_types (event_id, name, description, price, quantity, sort_order) VALUES (?, ?, ?, ?, NULL, ?)'
        );
        if (!$ins) {
            return ['ok' => false, 'error' => 'Could not add ticket type.'];
        }
        $ins->bind_param('issdi', $eventId, $name, $desc, $price, $sortOrder);
    } else {
        $ins = $conn->prepare(
            'INSERT INTO event_ticket_types (event_id, name, description, price, quantity, sort_order) VALUES (?, ?, ?, ?, ?, ?)'
        );
        if (!$ins) {
            return ['ok' => false, 'error' => 'Could not add ticket type.'];
        }
        $ins->bind_param('issdii', $eventId, $name, $desc, $price, $qty, $sortOrder);
    }
    $ins->execute();
    $newId = (int) $conn->insert_id;
    $ins->close();
    return ['ok' => true, 'id' => $newId];
}

function eventify_ticket_type_remaining(array $type): ?int
{
    $qty = isset($type['quantity']) && $type['quantity'] !== null && $type['quantity'] !== ''
        ? (int) $type['quantity'] : null;
    if ($qty === null || $qty < 1) {
        return null;
    }
    $sold = (int) ($type['sold_count'] ?? 0);
    return max(0, $qty - $sold);
}

/** @return array<string, mixed>|null */
function eventify_load_ticket_type_for_event(mysqli $conn, int $typeId, int $eventId): ?array
{
    if ($typeId < 1 || $eventId < 1 || !eventify_ticketing_ensure_schema($conn)) {
        return null;
    }
    $stmt = $conn->prepare('SELECT * FROM event_ticket_types WHERE id = ? AND event_id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('ii', $typeId, $eventId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * @param array{name?: string, description?: string, price?: float, quantity?: int|null, sort_order?: int} $fields
 * @return array{ok: bool, error?: string}
 */
function eventify_update_ticket_type(mysqli $conn, int $typeId, int $eventId, array $fields): array
{
    $type = eventify_load_ticket_type_for_event($conn, $typeId, $eventId);
    if (!$type) {
        return ['ok' => false, 'error' => 'Ticket type not found.'];
    }
    $sold = (int) ($type['sold_count'] ?? 0);
    $name = trim((string) ($fields['name'] ?? $type['name'] ?? ''));
    $desc = trim((string) ($fields['description'] ?? $type['description'] ?? ''));
    $price = isset($fields['price']) ? (float) $fields['price'] : (float) ($type['price'] ?? 0);
    $sortOrder = isset($fields['sort_order']) ? (int) $fields['sort_order'] : (int) ($type['sort_order'] ?? 0);

    $qtyRaw = array_key_exists('quantity', $fields) ? $fields['quantity'] : ($type['quantity'] ?? null);
    $qty = null;
    if ($qtyRaw !== null && $qtyRaw !== '') {
        if (!ctype_digit((string) $qtyRaw) || (int) $qtyRaw < 1) {
            return ['ok' => false, 'error' => 'Quantity cap must be a positive number or empty for unlimited.'];
        }
        $qty = (int) $qtyRaw;
    }

    if ($name === '') {
        return ['ok' => false, 'error' => 'Ticket name is required.'];
    }
    if ($price < 0) {
        return ['ok' => false, 'error' => 'Price cannot be negative.'];
    }
    if ($qty !== null && $qty < $sold) {
        return ['ok' => false, 'error' => 'Quantity cap cannot be less than tickets already sold (' . $sold . ').'];
    }
    if ($sold > 0 && abs($price - (float) ($type['price'] ?? 0)) > 0.009) {
        return ['ok' => false, 'error' => 'Price cannot be changed after tickets have been sold.'];
    }

    if ($qty === null) {
        $stmt = $conn->prepare(
            'UPDATE event_ticket_types SET name = ?, description = ?, price = ?, quantity = NULL, sort_order = ? WHERE id = ? AND event_id = ?'
        );
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Could not update ticket type.'];
        }
        $stmt->bind_param('ssdiii', $name, $desc, $price, $sortOrder, $typeId, $eventId);
    } else {
        $stmt = $conn->prepare(
            'UPDATE event_ticket_types SET name = ?, description = ?, price = ?, quantity = ?, sort_order = ? WHERE id = ? AND event_id = ?'
        );
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Could not update ticket type.'];
        }
        $stmt->bind_param('sssdiii', $name, $desc, $price, $qty, $sortOrder, $typeId, $eventId);
    }
    $stmt->execute();
    $ok = $stmt->affected_rows >= 0;
    $stmt->close();
    return $ok ? ['ok' => true] : ['ok' => false, 'error' => 'Could not update ticket type.'];
}

/** @return array{ok: bool, error?: string} */
function eventify_set_ticket_type_active(mysqli $conn, int $typeId, int $eventId, bool $active): array
{
    $type = eventify_load_ticket_type_for_event($conn, $typeId, $eventId);
    if (!$type) {
        return ['ok' => false, 'error' => 'Ticket type not found.'];
    }
    $flag = $active ? 1 : 0;
    $stmt = $conn->prepare('UPDATE event_ticket_types SET is_active = ? WHERE id = ? AND event_id = ?');
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Could not update ticket type.'];
    }
    $stmt->bind_param('iii', $flag, $typeId, $eventId);
    $stmt->execute();
    $stmt->close();
    return ['ok' => true];
}

/** @return array{ok: bool, error?: string} */
function eventify_cancel_ticket_order(mysqli $conn, int $orderId, int $eventId): array
{
    if ($orderId < 1 || $eventId < 1) {
        return ['ok' => false, 'error' => 'Invalid order.'];
    }
    $stmt = $conn->prepare("UPDATE ticket_orders SET status = 'cancelled' WHERE id = ? AND event_id = ? AND status = 'pending'");
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Could not reject order.'];
    }
    $stmt->bind_param('ii', $orderId, $eventId);
    $stmt->execute();
    $ok = $stmt->affected_rows > 0;
    $stmt->close();
    return $ok ? ['ok' => true] : ['ok' => false, 'error' => 'Order not found or already processed.'];
}

/**
 * @return array{ok: bool, error?: string, ticket_count?: int}
 */
function eventify_fulfill_ticket_order_for_event(
    mysqli $conn,
    int $orderId,
    int $eventId,
    string $paymentMethod = 'gcash',
    ?string $paymentReference = null
): array {
    $order = eventify_load_ticket_order($conn, $orderId);
    if (!$order || (int) ($order['event_id'] ?? 0) !== $eventId) {
        return ['ok' => false, 'error' => 'Order not found for this event.'];
    }
    $existingRef = trim((string) ($order['payment_reference'] ?? ''));
    if ($paymentReference === null || $paymentReference === '') {
        $paymentReference = $existingRef !== '' ? $existingRef . ' (verified)' : 'verified_by_organizer';
    }
    return eventify_fulfill_ticket_order($conn, $orderId, $paymentMethod, $paymentReference);
}

/** @return list<array<string, mixed>> */
function eventify_load_pending_orders_for_event(mysqli $conn, int $eventId): array
{
    if ($eventId < 1 || !eventify_ticketing_ensure_schema($conn)) {
        return [];
    }
    // Exclude online (PayMongo) orders — those settle automatically after the
    // gateway confirms payment and must never be fulfilled by manual verification.
    $stmt = $conn->prepare(
        "SELECT o.*, u.name AS student_name, u.user_id AS student_number
         FROM ticket_orders o
         JOIN users u ON u.id = o.user_id
         WHERE o.event_id = ? AND o.status = 'pending'
           AND (o.payment_method IS NULL OR o.payment_method <> 'gcash_paymongo')
         ORDER BY o.created_at DESC"
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    $stmt->close();
    foreach ($rows as &$row) {
        $oid = (int) ($row['id'] ?? 0);
        $items = $conn->prepare(
            'SELECT i.quantity, i.subtotal, t.name AS type_name FROM ticket_order_items i
             JOIN event_ticket_types t ON t.id = i.ticket_type_id WHERE i.order_id = ?'
        );
        if ($items) {
            $items->bind_param('i', $oid);
            $items->execute();
            $row['items'] = $items->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
            $items->close();
        } else {
            $row['items'] = [];
        }
    }
    unset($row);
    return $rows;
}

function eventify_format_order_items_summary(array $items): string
{
    if ($items === []) {
        return '—';
    }
    $parts = [];
    foreach ($items as $it) {
        $qty = (int) ($it['quantity'] ?? 0);
        $name = (string) ($it['type_name'] ?? 'Ticket');
        $parts[] = $qty . '× ' . $name;
    }
    return implode(', ', $parts);
}

/**
 * Sales summary for organizer ticket management.
 *
 * @return array{
 *   total_revenue: float,
 *   tickets_sold: int,
 *   tickets_used: int,
 *   orders_paid: int,
 *   orders_pending: int,
 *   by_type: list<array<string, mixed>>
 * }
 */
function eventify_load_ticket_sales_summary(mysqli $conn, int $eventId): array
{
    $empty = [
        'total_revenue' => 0.0,
        'tickets_sold' => 0,
        'tickets_used' => 0,
        'orders_paid' => 0,
        'orders_pending' => 0,
        'by_type' => [],
    ];
    if ($eventId < 1 || !eventify_ticketing_ensure_schema($conn)) {
        return $empty;
    }

    $summary = $empty;
    $revStmt = $conn->prepare(
        "SELECT COALESCE(SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END), 0) AS rev,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid_cnt,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_cnt
         FROM ticket_orders WHERE event_id = ?"
    );
    if ($revStmt) {
        $revStmt->bind_param('i', $eventId);
        $revStmt->execute();
        $row = $revStmt->get_result()->fetch_assoc();
        $revStmt->close();
        $summary['total_revenue'] = (float) ($row['rev'] ?? 0);
        $summary['orders_paid'] = (int) ($row['paid_cnt'] ?? 0);
        $summary['orders_pending'] = (int) ($row['pending_cnt'] ?? 0);
    }

    $tStmt = $conn->prepare(
        "SELECT COUNT(*) AS sold,
                SUM(CASE WHEN t.status = 'used' THEN 1 ELSE 0 END) AS used_cnt
         FROM event_tickets t
         JOIN ticket_orders o ON o.id = t.order_id
         WHERE t.event_id = ? AND o.status = 'paid'"
    );
    if ($tStmt) {
        $tStmt->bind_param('i', $eventId);
        $tStmt->execute();
        $tRow = $tStmt->get_result()->fetch_assoc();
        $tStmt->close();
        $summary['tickets_sold'] = (int) ($tRow['sold'] ?? 0);
        $summary['tickets_used'] = (int) ($tRow['used_cnt'] ?? 0);
    }

    $types = eventify_load_ticket_types_for_event($conn, $eventId, false);
    foreach ($types as $type) {
        $summary['by_type'][] = [
            'name' => $type['name'] ?? '',
            'price' => (float) ($type['price'] ?? 0),
            'sold' => (int) ($type['sold_count'] ?? 0),
            'quantity' => $type['quantity'] ?? null,
            'revenue' => (float) ($type['price'] ?? 0) * (int) ($type['sold_count'] ?? 0),
        ];
    }

    return $summary;
}

/** @return list<array<string, string>> */
function eventify_ticket_sales_export_rows(mysqli $conn, int $eventId): array
{
    if ($eventId < 1 || !eventify_ticketing_ensure_schema($conn)) {
        return [];
    }
    $stmt = $conn->prepare(
        "SELECT t.ticket_code, t.status, t.used_at, tt.name AS type_name, tt.price,
                u.name AS student_name, u.user_id AS student_id, o.order_ref, o.paid_at
         FROM event_tickets t
         JOIN ticket_orders o ON o.id = t.order_id
         JOIN event_ticket_types tt ON tt.id = t.ticket_type_id
         JOIN users u ON u.id = t.user_id
         WHERE t.event_id = ? AND o.status = 'paid'
         ORDER BY o.paid_at DESC, t.id DESC"
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows ?: [];
}

/** @return array{ok: bool, error?: string, order_id?: int, order_ref?: string} */
function eventify_create_ticket_order(mysqli $conn, int $userId, int $eventId, array $cartLines): array
{
    if ($userId < 1 || $eventId < 1 || $cartLines === [] || !eventify_ticketing_ensure_schema($conn)) {
        return ['ok' => false, 'error' => 'Invalid order.'];
    }

    // Need date/end fields (not just status) so eventify_event_is_live() works correctly.
    if (!is_file(__DIR__ . '/../../config/student_sections.php')) {
        return ['ok' => false, 'error' => 'Server configuration error.'];
    }
    require_once __DIR__ . '/../../config/student_sections.php';
    if (!function_exists('eventify_sections_schema_ensure')) {
        return ['ok' => false, 'error' => 'Server configuration error.'];
    }
    eventify_sections_schema_ensure($conn);
    $tsCol = eventify_events_has_target_sections($conn) ? ', target_sections' : '';
    $evStmt = $conn->prepare(
        "SELECT id, title, status, registration_mode, date, end_date, start_time, end_time, end_time_na, department{$tsCol}
         FROM events WHERE id = ? LIMIT 1"
    );
    if (!$evStmt) {
        return ['ok' => false, 'error' => 'Server error.'];
    }
    $evStmt->bind_param('i', $eventId);
    $evStmt->execute();
    $event = $evStmt->get_result()->fetch_assoc();
    $evStmt->close();
    require_once __DIR__ . '/event_calendar.php';
    if (!$event || !eventify_event_is_live($event)) {
        return ['ok' => false, 'error' => 'This event is not available for ticket sales.'];
    }
    if (!eventify_event_allows_ticket_shop($conn, $event)) {
        return ['ok' => false, 'error' => 'This event does not use paid ticketing.'];
    }

    $stuStmt = $conn->prepare('SELECT department, student_section FROM users WHERE id = ? LIMIT 1');
    if ($stuStmt) {
        $stuStmt->bind_param('i', $userId);
        $stuStmt->execute();
        $stu = $stuStmt->get_result()->fetch_assoc() ?: [];
        $stuStmt->close();
        if (!eventify_student_may_access_event($event, $stu)) {
            return ['ok' => false, 'error' => 'This event is not available for your department or class section.'];
        }
    }

    $types = eventify_load_ticket_types_for_event($conn, $eventId, true);
    $byId = [];
    foreach ($types as $t) {
        $byId[(int) $t['id']] = $t;
    }

    $total = 0.0;
    $lines = [];
    foreach ($cartLines as $line) {
        $tid = (int) ($line['ticket_type_id'] ?? 0);
        $qty = (int) ($line['quantity'] ?? 0);
        if ($tid < 1 || $qty < 1 || !isset($byId[$tid])) {
            return ['ok' => false, 'error' => 'Invalid ticket selection.'];
        }
        $type = $byId[$tid];
        $remaining = eventify_ticket_type_remaining($type);
        if ($remaining !== null && $qty > $remaining) {
            return ['ok' => false, 'error' => 'Not enough tickets left for "' . ($type['name'] ?? 'ticket') . '".'];
        }
        $unit = (float) ($type['price'] ?? 0);
        if ($unit < 0) {
            $unit = 0;
        }
        $sub = round($unit * $qty, 2);
        $total += $sub;
        $lines[] = [
            'ticket_type_id' => $tid,
            'quantity' => $qty,
            'unit_price' => $unit,
            'subtotal' => $sub,
        ];
    }
    if ($lines === []) {
        return ['ok' => false, 'error' => 'Your cart is empty.'];
    }
    $total = round($total, 2);

    $orderRef = eventify_generate_order_ref();
    try {
        $conn->begin_transaction();
        $ins = $conn->prepare(
            'INSERT INTO ticket_orders (order_ref, user_id, event_id, total_amount, status) VALUES (?, ?, ?, ?, ?)'
        );
        if (!$ins) {
            throw new RuntimeException('Could not create order.');
        }
        $status = 'pending';
        $ins->bind_param('siids', $orderRef, $userId, $eventId, $total, $status);
        $ins->execute();
        $orderId = (int) $conn->insert_id;
        $ins->close();

        $itemStmt = $conn->prepare(
            'INSERT INTO ticket_order_items (order_id, ticket_type_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)'
        );
        if (!$itemStmt) {
            throw new RuntimeException('Could not save order items.');
        }
        foreach ($lines as $line) {
            $itemStmt->bind_param(
                'iiidd',
                $orderId,
                $line['ticket_type_id'],
                $line['quantity'],
                $line['unit_price'],
                $line['subtotal']
            );
            $itemStmt->execute();
        }
        $itemStmt->close();
        $conn->commit();
        return ['ok' => true, 'order_id' => $orderId, 'order_ref' => $orderRef, 'total' => $total];
    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $e2) {
        }
        return ['ok' => false, 'error' => 'Could not place order. Please try again.'];
    }
}

/** @return array<string, mixed>|null */
function eventify_load_ticket_order(mysqli $conn, int $orderId, ?int $userId = null): ?array
{
    if ($orderId < 1 || !eventify_ticketing_ensure_schema($conn)) {
        return null;
    }
    $sql = 'SELECT o.*, e.title AS event_title, e.date AS event_date, e.location AS event_location, e.start_time AS event_start_time
            FROM ticket_orders o
            JOIN events e ON e.id = o.event_id
            WHERE o.id = ?';
    if ($userId !== null && $userId > 0) {
        $sql .= ' AND o.user_id = ?';
    }
    $sql .= ' LIMIT 1';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    if ($userId !== null && $userId > 0) {
        $stmt->bind_param('ii', $orderId, $userId);
    } else {
        $stmt->bind_param('i', $orderId);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return null;
    }
    $items = $conn->prepare(
        'SELECT i.*, t.name AS type_name FROM ticket_order_items i
         JOIN event_ticket_types t ON t.id = i.ticket_type_id
         WHERE i.order_id = ?'
    );
    if ($items) {
        $items->bind_param('i', $orderId);
        $items->execute();
        $row['items'] = $items->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
        $items->close();
    } else {
        $row['items'] = [];
    }
    return $row;
}

/**
 * Mark order paid and issue digital tickets.
 *
 * @return array{ok: bool, error?: string, ticket_count?: int}
 */
function eventify_fulfill_ticket_order(
    mysqli $conn,
    int $orderId,
    string $paymentMethod = 'simulate',
    ?string $paymentReference = null
): array {
    $order = eventify_load_ticket_order($conn, $orderId);
    if (!$order) {
        return ['ok' => false, 'error' => 'Order not found.'];
    }
    if (($order['status'] ?? '') === 'paid') {
        return ['ok' => true, 'ticket_count' => eventify_count_tickets_for_order($conn, $orderId)];
    }
    if (($order['status'] ?? '') !== 'pending') {
        return ['ok' => false, 'error' => 'This order cannot be paid.'];
    }

    $userId = (int) ($order['user_id'] ?? 0);
    $eventId = (int) ($order['event_id'] ?? 0);

    try {
        $conn->begin_transaction();

        foreach ($order['items'] as $item) {
            $tid = (int) ($item['ticket_type_id'] ?? 0);
            $qty = (int) ($item['quantity'] ?? 0);
            if ($tid < 1 || $qty < 1) {
                continue;
            }
            $upd = $conn->prepare('UPDATE event_ticket_types SET sold_count = sold_count + ? WHERE id = ? AND event_id = ?');
            if ($upd) {
                $upd->bind_param('iii', $qty, $tid, $eventId);
                $upd->execute();
                $upd->close();
            }
        }

        $ticketIns = $conn->prepare(
            'INSERT INTO event_tickets (order_id, ticket_type_id, user_id, event_id, ticket_code, checkin_token)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        if (!$ticketIns) {
            throw new RuntimeException('Could not issue tickets.');
        }

        $issued = 0;
        foreach ($order['items'] as $item) {
            $tid = (int) ($item['ticket_type_id'] ?? 0);
            $qty = (int) ($item['quantity'] ?? 0);
            for ($i = 0; $i < $qty; $i++) {
                $code = eventify_generate_ticket_code();
                $token = bin2hex(random_bytes(16));
                $ticketIns->bind_param('iiiiss', $orderId, $tid, $userId, $eventId, $code, $token);
                $ticketIns->execute();
                $issued++;
            }
        }
        $ticketIns->close();

        $payStmt = $conn->prepare(
            "UPDATE ticket_orders SET status = 'paid', payment_method = ?, payment_reference = ?, paid_at = NOW() WHERE id = ? AND status = 'pending'"
        );
        if (!$payStmt) {
            throw new RuntimeException('Could not update order.');
        }
        $payStmt->bind_param('ssi', $paymentMethod, $paymentReference, $orderId);
        $payStmt->execute();
        $payStmt->close();

        $evModeStmt = $conn->prepare('SELECT registration_mode FROM events WHERE id = ? LIMIT 1');
        $regMode = 'rsvp';
        if ($evModeStmt) {
            $evModeStmt->bind_param('i', $eventId);
            $evModeStmt->execute();
            $evModeRow = $evModeStmt->get_result()->fetch_assoc();
            $evModeStmt->close();
            if ($evModeRow) {
                $regMode = eventify_event_registration_mode($evModeRow);
            }
        }
        if ($regMode === 'paid_ticket') {
            eventify_ensure_registration_for_ticket_holder($conn, $userId, $eventId);
        }

        $conn->commit();

        try {
            $title = (string) ($order['event_title'] ?? 'event');
            $n = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, event_id) VALUES (?, 'ticket_paid', 'Tickets confirmed', ?, ?)");
            if ($n) {
                $msg = 'Your tickets for "' . $title . '" are ready. Open My Tickets to view your digital pass.';
                $n->bind_param('isi', $userId, $msg, $eventId);
                if ($n->execute()) {
                    eventify_push_notify_user($conn, $userId, 'ticket_paid', 'Tickets confirmed', $msg, $eventId);
                }
                $n->close();
            }
        } catch (Throwable $e) {
            /* ignore */
        }

        return ['ok' => true, 'ticket_count' => $issued];
    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $e2) {
        }
        return ['ok' => false, 'error' => 'Payment could not be completed.'];
    }
}

function eventify_ensure_registration_for_ticket_holder(mysqli $conn, int $userId, int $eventId): void
{
    $stmt = $conn->prepare(
        "INSERT INTO registrations (user_id, event_id) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE user_id = user_id"
    );
    if ($stmt) {
        $stmt->bind_param('ii', $userId, $eventId);
        $stmt->execute();
        $stmt->close();
    }
}

function eventify_count_tickets_for_order(mysqli $conn, int $orderId): int
{
    $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM event_tickets WHERE order_id = ?');
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int) ($row['c'] ?? 0);
}

/** @return list<array<string, mixed>> */
function eventify_load_user_tickets(mysqli $conn, int $userId, ?int $eventId = null): array
{
    if ($userId < 1 || !eventify_ticketing_ensure_schema($conn)) {
        return [];
    }
    $sql = 'SELECT t.*, e.title AS event_title, e.date AS event_date, e.location AS event_location,
                   e.start_time AS event_start_time, tt.name AS type_name, o.order_ref, o.paid_at
            FROM event_tickets t
            JOIN events e ON e.id = t.event_id
            JOIN event_ticket_types tt ON tt.id = t.ticket_type_id
            JOIN ticket_orders o ON o.id = t.order_id
            WHERE t.user_id = ? AND t.status = ? AND o.status = ?';
    $status = 'valid';
    $orderStatus = 'paid';
    if ($eventId !== null && $eventId > 0) {
        $sql .= ' AND t.event_id = ?';
    }
    $sql .= ' ORDER BY o.paid_at DESC, t.id DESC';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    if ($eventId !== null && $eventId > 0) {
        $stmt->bind_param('issi', $userId, $status, $orderStatus, $eventId);
    } else {
        $stmt->bind_param('iss', $userId, $status, $orderStatus);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows ?: [];
}

/** @return array<string, mixed>|null */
function eventify_load_ticket_by_code(mysqli $conn, string $ticketCode): ?array
{
    $ticketCode = trim($ticketCode);
    if ($ticketCode === '' || !eventify_ticketing_ensure_schema($conn)) {
        return null;
    }
    $stmt = $conn->prepare(
        'SELECT t.*, e.title AS event_title, e.status AS event_status, e.location AS event_location,
                e.date AS event_date, e.start_time AS event_start_time, tt.name AS type_name,
                u.name AS holder_name, u.user_id AS holder_student_id
         FROM event_tickets t
         JOIN events e ON e.id = t.event_id
         JOIN event_ticket_types tt ON tt.id = t.ticket_type_id
         JOIN users u ON u.id = t.user_id
         WHERE t.ticket_code = ? LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $ticketCode);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/** @return array<string, mixed>|null */
function eventify_load_ticket_by_checkin_token(mysqli $conn, string $token): ?array
{
    $token = trim($token);
    if ($token === '' || !eventify_ticketing_ensure_schema($conn)) {
        return null;
    }
    $geoCols = '';
    try {
        $gc = $conn->query("SHOW COLUMNS FROM events WHERE Field IN ('latitude','longitude','checkin_require_geo')");
        $hasLat = $hasLng = $hasGeoFlag = false;
        if ($gc) {
            while ($col = $gc->fetch_assoc()) {
                $f = (string) ($col['Field'] ?? '');
                if ($f === 'latitude') $hasLat = true;
                if ($f === 'longitude') $hasLng = true;
                if ($f === 'checkin_require_geo') $hasGeoFlag = true;
            }
        }
        if ($hasLat && $hasLng) {
            $geoCols = ', e.latitude, e.longitude';
        }
        if ($hasGeoFlag) {
            $geoCols .= ', e.checkin_require_geo';
        }
    } catch (Throwable $e) {
        $geoCols = '';
    }
    $stmt = $conn->prepare(
        'SELECT t.*, e.title AS event_title, e.status AS event_status, e.location AS event_location,
                e.date AS event_date, tt.name AS type_name, u.name AS holder_name' . $geoCols . '
         FROM event_tickets t
         JOIN events e ON e.id = t.event_id
         JOIN event_ticket_types tt ON tt.id = t.ticket_type_id
         JOIN users u ON u.id = t.user_id
         WHERE t.checkin_token = ? LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * System-wide ticket revenue rollup for admin / super admin oversight.
 *
 * "Real" revenue counts only settled (paid) orders that are NOT demo/simulate
 * payments, so the headline figure reflects actual money collected. Demo orders
 * are reported separately for transparency.
 *
 * @return array{
 *   total_revenue: float,
 *   tickets_sold: int,
 *   orders_paid: int,
 *   pending_amount: float,
 *   pending_orders: int,
 *   demo_revenue: float,
 *   demo_orders: int,
 *   by_method: list<array{method: string, label: string, revenue: float, orders: int}>,
 *   by_event: list<array{event_id: int, title: string, date: ?string, organizer_name: ?string, revenue: float, orders: int}>
 * }
 */
function eventify_revenue_overview(mysqli $conn): array
{
    $out = [
        'total_revenue' => 0.0,
        'tickets_sold' => 0,
        'orders_paid' => 0,
        'pending_amount' => 0.0,
        'pending_orders' => 0,
        'demo_revenue' => 0.0,
        'demo_orders' => 0,
        'by_method' => [],
        'by_event' => [],
    ];
    if (!eventify_ticketing_ensure_schema($conn)) {
        return $out;
    }

    // Real, settled revenue (exclude demo/simulate).
    if ($r = $conn->query("SELECT COALESCE(SUM(total_amount),0) AS rev, COUNT(*) AS cnt
                           FROM ticket_orders
                           WHERE status = 'paid' AND (payment_method IS NULL OR payment_method <> 'simulate')")) {
        if ($row = $r->fetch_assoc()) {
            $out['total_revenue'] = (float) ($row['rev'] ?? 0);
            $out['orders_paid'] = (int) ($row['cnt'] ?? 0);
        }
    }

    // Demo/simulate orders (reported separately, never in the headline total).
    if ($r = $conn->query("SELECT COALESCE(SUM(total_amount),0) AS rev, COUNT(*) AS cnt
                           FROM ticket_orders
                           WHERE status = 'paid' AND payment_method = 'simulate'")) {
        if ($row = $r->fetch_assoc()) {
            $out['demo_revenue'] = (float) ($row['rev'] ?? 0);
            $out['demo_orders'] = (int) ($row['cnt'] ?? 0);
        }
    }

    // Money still in flight.
    if ($r = $conn->query("SELECT COALESCE(SUM(total_amount),0) AS amt, COUNT(*) AS cnt
                           FROM ticket_orders WHERE status = 'pending'")) {
        if ($row = $r->fetch_assoc()) {
            $out['pending_amount'] = (float) ($row['amt'] ?? 0);
            $out['pending_orders'] = (int) ($row['cnt'] ?? 0);
        }
    }

    // Real tickets issued.
    if ($r = $conn->query("SELECT COUNT(*) AS c
                           FROM event_tickets t
                           JOIN ticket_orders o ON o.id = t.order_id
                           WHERE o.status = 'paid' AND (o.payment_method IS NULL OR o.payment_method <> 'simulate')")) {
        if ($row = $r->fetch_assoc()) {
            $out['tickets_sold'] = (int) ($row['c'] ?? 0);
        }
    }

    // Split by payment method (paid orders only).
    $methodLabels = [
        'gcash_paymongo' => 'GCash (online)',
        'gcash' => 'GCash (manual)',
        'cash' => 'Cash',
        'simulate' => 'Demo / test',
    ];
    if ($r = $conn->query("SELECT payment_method, COALESCE(SUM(total_amount),0) AS rev, COUNT(*) AS cnt
                           FROM ticket_orders WHERE status = 'paid'
                           GROUP BY payment_method ORDER BY rev DESC")) {
        while ($row = $r->fetch_assoc()) {
            $method = (string) ($row['payment_method'] ?? '');
            $out['by_method'][] = [
                'method' => $method,
                'label' => $methodLabels[$method] ?? ($method !== '' ? ucfirst($method) : 'Unknown'),
                'revenue' => (float) ($row['rev'] ?? 0),
                'orders' => (int) ($row['cnt'] ?? 0),
            ];
        }
    }

    // Per-event breakdown (real revenue only).
    if ($r = $conn->query("SELECT e.id AS event_id, e.title, e.date, u.name AS organizer_name,
                                  COALESCE(SUM(o.total_amount),0) AS rev, COUNT(o.id) AS orders
                           FROM ticket_orders o
                           JOIN events e ON e.id = o.event_id
                           LEFT JOIN users u ON u.id = e.organizer_id
                           WHERE o.status = 'paid' AND (o.payment_method IS NULL OR o.payment_method <> 'simulate')
                           GROUP BY e.id, e.title, e.date, u.name
                           ORDER BY rev DESC")) {
        while ($row = $r->fetch_assoc()) {
            $out['by_event'][] = [
                'event_id' => (int) ($row['event_id'] ?? 0),
                'title' => (string) ($row['title'] ?? ''),
                'date' => $row['date'] ?? null,
                'organizer_name' => $row['organizer_name'] ?? null,
                'revenue' => (float) ($row['rev'] ?? 0),
                'orders' => (int) ($row['orders'] ?? 0),
            ];
        }
    }

    return $out;
}

/** @return array{ok: bool, error?: string} */
function eventify_mark_ticket_used(mysqli $conn, int $ticketId, int $userId): array
{
    $stmt = $conn->prepare(
        "UPDATE event_tickets SET status = 'used', used_at = NOW() WHERE id = ? AND user_id = ? AND status = 'valid'"
    );
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Could not record entry.'];
    }
    $stmt->bind_param('ii', $ticketId, $userId);
    $stmt->execute();
    $ok = $stmt->affected_rows > 0;
    $stmt->close();
    if ($ok) {
        $reg = $conn->prepare(
            "UPDATE registrations SET status = 'present', time_in = NOW() WHERE user_id = ? AND event_id = (
                SELECT event_id FROM event_tickets WHERE id = ? LIMIT 1
            )"
        );
        if ($reg) {
            $reg->bind_param('ii', $userId, $ticketId);
            $reg->execute();
            $reg->close();
        }
    }
    return $ok ? ['ok' => true] : ['ok' => false, 'error' => 'Invalid or already used ticket.'];
}
