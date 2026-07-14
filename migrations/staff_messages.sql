-- Admin ↔ Organizer messaging (optional manual run; app also creates table if missing)
CREATE TABLE IF NOT EXISTS staff_messages (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    recipient_id INT NOT NULL,
    body VARCHAR(8000) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME NULL DEFAULT NULL,
    KEY idx_pair_time (sender_id, recipient_id, created_at),
    KEY idx_inbox (recipient_id, read_at, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
