<?php

/**
 * Simple helper to record admin / super admin actions.
 *
 * @param mysqli $conn
 * @param int|null $actorId
 * @param string|null $actorRole
 * @param string $action        Short key, e.g. "event_approved"
 * @param string|null $targetType  e.g. "event", "user"
 * @param int|null $targetId
 * @param string|null $details     Optional human-readable description
 */
function log_activity($conn, $actorId, $actorRole, $action, $targetType = null, $targetId = null, $details = null)
{
    if (!$conn || !$action) {
        return;
    }

    $sql = "INSERT INTO activity_logs (actor_id, actor_role, action, target_type, target_id, details) 
            VALUES (?, ?, ?, ?, ?, ?)";
    if (!$stmt = $conn->prepare($sql)) {
        return;
    }

    $actorId    = $actorId ? (int)$actorId : null;
    $targetId   = $targetId ? (int)$targetId : null;
    $actorRole  = $actorRole ?: null;
    $targetType = $targetType ?: null;
    $details    = $details ?: null;

    $stmt->bind_param(
        "isssis",
        $actorId,
        $actorRole,
        $action,
        $targetType,
        $targetId,
        $details
    );
    $stmt->execute();
    $stmt->close();
}

