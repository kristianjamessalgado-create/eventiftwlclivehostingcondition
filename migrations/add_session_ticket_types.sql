-- Allows a single activity (event_day_session) to accept multiple ticket tiers.
-- A student gets into the activity if they hold a valid ticket for ANY linked type.
CREATE TABLE IF NOT EXISTS event_session_ticket_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    ticket_type_id INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_session_type (session_id, ticket_type_id),
    KEY idx_session (session_id),
    KEY idx_ticket_type (ticket_type_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
