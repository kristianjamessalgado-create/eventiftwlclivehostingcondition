<?php

require_once __DIR__ . '/event_evaluation.php';

/**
 * Ensure event_feedback has is_anonymous (1 = hide student name from organizer/admin UI).
 */
function eventify_event_feedback_ensure_schema(mysqli $conn): bool
{
    try {
        $t = $conn->query("SHOW TABLES LIKE 'event_feedback'");
        if (!$t || $t->num_rows < 1) {
            return false;
        }
        $c = $conn->query("SHOW COLUMNS FROM event_feedback LIKE 'is_anonymous'");
        if (!$c || $c->num_rows < 1) {
            $conn->query(
                "ALTER TABLE event_feedback ADD COLUMN is_anonymous TINYINT(1) NOT NULL DEFAULT 1 AFTER comment"
            );
        }
        eventify_evaluation_ensure_schema($conn);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/** Label shown to organizer/admin: always anonymous; department only when set. */
function eventify_feedback_respondent_label(?string $department): string
{
    $dept = trim((string) $department);
    if ($dept === '' || strtoupper($dept) === 'ALL') {
        return 'Anonymous';
    }
    return 'Anonymous · ' . $dept;
}
