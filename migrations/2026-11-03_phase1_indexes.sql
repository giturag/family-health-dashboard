-- Phase 1 index migration.
--
-- Adds lookup/performance indexes that are safe to apply to existing data.
-- The UNIQUE constraints for subscribers.email and appointment slots are in
-- 2026-11-03_phase1_unique_indexes.sql because the current data contains
-- duplicates (see the preflight queries below).
--
-- This file is repeatable: each index is added only when it does not exist.

-- ---------------------------------------------------------------------------
-- Preflight: unique-index blockers (informational only)
-- ---------------------------------------------------------------------------
SELECT 'duplicate subscriber emails' AS check_name, COUNT(*) AS duplicate_groups
FROM (
    SELECT email
    FROM subscribers
    WHERE email IS NOT NULL AND email <> ''
    GROUP BY email
    HAVING COUNT(*) > 1
) d;

SELECT 'duplicate appointment slots' AS check_name, COUNT(*) AS duplicate_groups
FROM (
    SELECT doctorID_FK, appt_date, time_slot
    FROM appointments
    WHERE doctorID_FK IS NOT NULL AND appt_date IS NOT NULL AND time_slot IS NOT NULL
    GROUP BY doctorID_FK, appt_date, time_slot
    HAVING COUNT(*) > 1
) d;

-- ---------------------------------------------------------------------------
-- Repeatable index helper
-- ---------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS fhd_add_index;

DELIMITER //
CREATE PROCEDURE fhd_add_index(
    IN p_table VARCHAR(64),
    IN p_index VARCHAR(64),
    IN p_ddl TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = p_table
          AND index_name = p_index
    ) THEN
        SET @fhd_ddl = p_ddl;
        PREPARE fhd_stmt FROM @fhd_ddl;
        EXECUTE fhd_stmt;
        DEALLOCATE PREPARE fhd_stmt;
    END IF;
END//
DELIMITER ;

-- Subscribers: login lookup, dependent lookup, regional routing, role stats.
CALL fhd_add_index('subscribers', 'idx_subscribers_email',
    'ALTER TABLE subscribers ADD INDEX idx_subscribers_email (email)');
CALL fhd_add_index('subscribers', 'idx_subscribers_guardian',
    'ALTER TABLE subscribers ADD INDEX idx_subscribers_guardian (guardianID)');
CALL fhd_add_index('subscribers', 'idx_subscribers_region',
    'ALTER TABLE subscribers ADD INDEX idx_subscribers_region (region)');
CALL fhd_add_index('subscribers', 'idx_subscribers_role',
    'ALTER TABLE subscribers ADD INDEX idx_subscribers_role (sub_role)');

-- Appointments: slot checks and patient/doctor history.
CALL fhd_add_index('appointments', 'idx_appointments_doctor_slot',
    'ALTER TABLE appointments ADD INDEX idx_appointments_doctor_slot (doctorID_FK, appt_date, time_slot)');
CALL fhd_add_index('appointments', 'idx_appointments_patient_date',
    'ALTER TABLE appointments ADD INDEX idx_appointments_patient_date (patientID_FK, appt_date)');
CALL fhd_add_index('appointments', 'idx_appointments_status',
    'ALTER TABLE appointments ADD INDEX idx_appointments_status (status)');

-- Visits: doctor/patient timelines.
CALL fhd_add_index('health_visits', 'idx_health_visits_doctor_date',
    'ALTER TABLE health_visits ADD INDEX idx_health_visits_doctor_date (doctorID_FK, visit_date)');
CALL fhd_add_index('health_visits', 'idx_health_visits_patient_date',
    'ALTER TABLE health_visits ADD INDEX idx_health_visits_patient_date (patientID_FK, visit_date)');

-- Vitals: latest reading per patient.
CALL fhd_add_index('vitals', 'idx_vitals_patient_date',
    'ALTER TABLE vitals ADD INDEX idx_vitals_patient_date (subID_FK, date_of_reading)');

-- Billing: patient statements and status filters.
CALL fhd_add_index('bill_headers', 'idx_bill_headers_patient_status',
    'ALTER TABLE bill_headers ADD INDEX idx_bill_headers_patient_status (patientID_FK, status)');
CALL fhd_add_index('bill_headers', 'idx_bill_headers_created',
    'ALTER TABLE bill_headers ADD INDEX idx_bill_headers_created (created_at)');

-- Lab and prescription timelines.
CALL fhd_add_index('lab_test_items', 'idx_lab_items_request_result',
    'ALTER TABLE lab_test_items ADD INDEX idx_lab_items_request_result (requestID_FK, result_date)');
CALL fhd_add_index('prescriptions', 'idx_prescriptions_patient_date',
    'ALTER TABLE prescriptions ADD INDEX idx_prescriptions_patient_date (patientID_FK, prescribe_date)');
CALL fhd_add_index('prescriptions', 'idx_prescriptions_doctor_date',
    'ALTER TABLE prescriptions ADD INDEX idx_prescriptions_doctor_date (doctorID_FK, prescribe_date)');

-- Provider profile lookups.
CALL fhd_add_index('doctor_profiles', 'idx_doctor_profiles_specialty',
    'ALTER TABLE doctor_profiles ADD INDEX idx_doctor_profiles_specialty (specialty)');
CALL fhd_add_index('doctor_profiles', 'uq_doctor_profiles_doc',
    'ALTER TABLE doctor_profiles ADD UNIQUE KEY uq_doctor_profiles_doc (docID_FK)');
CALL fhd_add_index('lab_technicians', 'uq_lab_technicians_labtech',
    'ALTER TABLE lab_technicians ADD UNIQUE KEY uq_lab_technicians_labtech (labtechID_FK)');

-- Contact inbox ordering.
CALL fhd_add_index('messages', 'idx_messages_created',
    'ALTER TABLE messages ADD INDEX idx_messages_created (created_at)');

DROP PROCEDURE IF EXISTS fhd_add_index;
