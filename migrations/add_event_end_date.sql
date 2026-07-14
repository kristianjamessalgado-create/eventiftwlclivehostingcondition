-- Multi-day events (e.g. intramurals): optional last calendar day (inclusive).
-- `date` remains the event start date; `end_date` is NULL or >= `date`.
ALTER TABLE `events`
  ADD COLUMN `end_date` DATE DEFAULT NULL AFTER `date`;
