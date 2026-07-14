-- Add reject_reason to events (for admin reject flow)
-- Run this once if your events table was created before this feature.
ALTER TABLE events ADD COLUMN reject_reason TEXT DEFAULT NULL;
