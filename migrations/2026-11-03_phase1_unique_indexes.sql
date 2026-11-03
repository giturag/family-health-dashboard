-- Phase 1 UNIQUE index migration.
--
-- DO NOT apply this file until the duplicate data has been reviewed and
-- cleaned. The preflight queries below report duplicate subscriber emails
-- and duplicate appointment slots (same doctor/date/time).
--
-- The ALTER statements below fail safely (no data is deleted) while the
-- duplicates remain. Run the preflight queries in
-- 2026-11-03_phase1_indexes.sql first.
--
-- After cleanup, apply this file once. It is not idempotent on its own; the
-- safe index migration uses the fhd_add_index helper for that reason.

-- Uncomment / run after deduplication:

-- ALTER TABLE subscribers
--     ADD UNIQUE KEY uq_subscribers_email (email);

-- ALTER TABLE appointments
--     ADD UNIQUE KEY uq_appointments_doctor_slot (doctorID_FK, appt_date, time_slot);

-- Preflight report for the cleanup work:
SELECT email, COUNT(*) AS accounts,
       GROUP_CONCAT(subID ORDER BY subID) AS subscriber_ids
FROM subscribers
WHERE email IS NOT NULL AND email <> ''
GROUP BY email
HAVING COUNT(*) > 1;

SELECT doctorID_FK, appt_date, time_slot, COUNT(*) AS bookings,
       GROUP_CONCAT(apptID ORDER BY apptID) AS appointment_ids,
       GROUP_CONCAT(patientID_FK ORDER BY apptID) AS patient_ids,
       GROUP_CONCAT(status ORDER BY apptID) AS statuses
FROM appointments
GROUP BY doctorID_FK, appt_date, time_slot
HAVING COUNT(*) > 1;

-- Decide whether duplicate appointments are legitimate (e.g. reschedules)
-- and whether duplicate accounts are test data or need merging. Do not delete
-- rows until that decision is recorded.
