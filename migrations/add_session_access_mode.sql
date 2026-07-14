-- Per-activity access inside a parent event hub (free RSVP vs ticket required).
ALTER TABLE event_day_sessions
  ADD COLUMN access_mode VARCHAR(20) NOT NULL DEFAULT 'free' AFTER status,
  ADD COLUMN ticket_type_id INT NULL DEFAULT NULL AFTER access_mode;
