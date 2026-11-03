-- Family Health Dashboard schema (schema-only, no data).
-- Exported 2026-11-03. Import with: mysql -u root -p < sql/schema.sql


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `fhddb` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;

USE `fhddb`;
DROP TABLE IF EXISTS `allergy_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `allergy_records` (
  `allergyID` int NOT NULL AUTO_INCREMENT,
  `allergen` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `patientID_FK` int DEFAULT NULL,
  `severity` varchar(255) DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`allergyID`),
  KEY `patientID_FK` (`patientID_FK`),
  CONSTRAINT `allergy_records_ibfk_1` FOREIGN KEY (`patientID_FK`) REFERENCES `subscribers` (`subID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `appointments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `appointments` (
  `apptID` int NOT NULL AUTO_INCREMENT,
  `patientID_FK` int DEFAULT NULL,
  `doctorID_FK` int DEFAULT NULL,
  `appt_date` date DEFAULT NULL,
  `time_slot` time DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`apptID`),
  KEY `idx_appointments_doctor_slot` (`doctorID_FK`,`appt_date`,`time_slot`),
  KEY `idx_appointments_patient_date` (`patientID_FK`,`appt_date`),
  KEY `idx_appointments_status` (`status`),
  CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patientID_FK`) REFERENCES `subscribers` (`subID`),
  CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`doctorID_FK`) REFERENCES `subscribers` (`subID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `approved_centres`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `approved_centres` (
  `ctID` int NOT NULL AUTO_INCREMENT,
  `ctName` varchar(255) DEFAULT NULL,
  `ctLocation` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`ctID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bill_headers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bill_headers` (
  `billID` int NOT NULL AUTO_INCREMENT,
  `patientID_FK` int NOT NULL,
  `hvisitID_FK` int NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('unpaid','paid','partially_paid') DEFAULT 'unpaid',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `lab_testID_FK` int DEFAULT NULL,
  `description` text,
  `paid_date` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`billID`),
  KEY `hvisitID_FK` (`hvisitID_FK`),
  KEY `fk_bills_labtest` (`lab_testID_FK`),
  KEY `idx_bill_headers_patient_status` (`patientID_FK`,`status`),
  KEY `idx_bill_headers_created` (`created_at`),
  CONSTRAINT `bill_headers_ibfk_1` FOREIGN KEY (`patientID_FK`) REFERENCES `subscribers` (`subID`),
  CONSTRAINT `bill_headers_ibfk_2` FOREIGN KEY (`hvisitID_FK`) REFERENCES `health_visits` (`hvisitID`),
  CONSTRAINT `fk_bills_labtest` FOREIGN KEY (`lab_testID_FK`) REFERENCES `lab_requests` (`labtestID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bill_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bill_items` (
  `billitemID` int NOT NULL AUTO_INCREMENT,
  `billID_FK` int NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  PRIMARY KEY (`billitemID`),
  KEY `billID_FK` (`billID_FK`),
  CONSTRAINT `bill_items_ibfk_1` FOREIGN KEY (`billID_FK`) REFERENCES `bill_headers` (`billID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `chronic_condition_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chronic_condition_records` (
  `conditionID` int NOT NULL AUTO_INCREMENT,
  `condition_name` varchar(255) NOT NULL,
  `patientID_FK` int DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`conditionID`),
  KEY `patientID_FK` (`patientID_FK`),
  CONSTRAINT `chronic_condition_records_ibfk_1` FOREIGN KEY (`patientID_FK`) REFERENCES `subscribers` (`subID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `doctor_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `doctor_profiles` (
  `doctorID` int NOT NULL AUTO_INCREMENT,
  `specialty` varchar(80) DEFAULT NULL,
  `docID_FK` int DEFAULT NULL,
  `hospital` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`doctorID`),
  UNIQUE KEY `uq_doctor_profiles_doc` (`docID_FK`),
  KEY `idx_doctor_profiles_specialty` (`specialty`),
  CONSTRAINT `doctor_profiles_ibfk_1` FOREIGN KEY (`docID_FK`) REFERENCES `subscribers` (`subID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `health_visits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `health_visits` (
  `hvisitID` int NOT NULL AUTO_INCREMENT,
  `specialty` varchar(255) DEFAULT NULL,
  `visit_date` date DEFAULT NULL,
  `visit_notes` varchar(255) DEFAULT NULL,
  `diagnosis` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `appointmentID_FK` int DEFAULT NULL,
  `hv_consultation_fee` float DEFAULT NULL,
  `patientID_FK` int DEFAULT NULL,
  `doctorID_FK` int DEFAULT NULL,
  `next_appointment` datetime DEFAULT NULL,
  PRIMARY KEY (`hvisitID`),
  KEY `patientID_FK` (`patientID_FK`),
  KEY `doctorID_FK` (`doctorID_FK`),
  KEY `appointmentID_FK` (`appointmentID_FK`),
  KEY `idx_health_visits_doctor_date` (`doctorID_FK`,`visit_date`),
  KEY `idx_health_visits_patient_date` (`patientID_FK`,`visit_date`),
  CONSTRAINT `appointmentID_FK` FOREIGN KEY (`appointmentID_FK`) REFERENCES `appointments` (`apptID`),
  CONSTRAINT `apppointmentID_FK` FOREIGN KEY (`appointmentID_FK`) REFERENCES `appointments` (`apptID`),
  CONSTRAINT `health_visits_ibfk_1` FOREIGN KEY (`patientID_FK`) REFERENCES `subscribers` (`subID`),
  CONSTRAINT `health_visits_ibfk_2` FOREIGN KEY (`doctorID_FK`) REFERENCES `subscribers` (`subID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lab_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lab_requests` (
  `labtestID` int NOT NULL AUTO_INCREMENT,
  `test_centre` varchar(255) DEFAULT NULL,
  `patientID_FK` int DEFAULT NULL,
  `doctorID_FK` int DEFAULT NULL,
  `labtechID_FK` int DEFAULT NULL,
  `hvisitID_FK` int DEFAULT NULL,
  PRIMARY KEY (`labtestID`),
  KEY `patientID_FK` (`patientID_FK`),
  KEY `doctorID_FK` (`doctorID_FK`),
  KEY `pharmaID_FK` (`labtechID_FK`),
  KEY `hvisitID_FK` (`hvisitID_FK`),
  CONSTRAINT `lab_requests_ibfk_1` FOREIGN KEY (`patientID_FK`) REFERENCES `subscribers` (`subID`),
  CONSTRAINT `lab_requests_ibfk_2` FOREIGN KEY (`doctorID_FK`) REFERENCES `subscribers` (`subID`),
  CONSTRAINT `lab_requests_ibfk_3` FOREIGN KEY (`labtechID_FK`) REFERENCES `subscribers` (`subID`),
  CONSTRAINT `lab_requests_ibfk_4` FOREIGN KEY (`hvisitID_FK`) REFERENCES `health_visits` (`hvisitID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lab_technicians`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lab_technicians` (
  `techID` int NOT NULL AUTO_INCREMENT,
  `lab_name` varchar(80) DEFAULT NULL,
  `labtechID_FK` int DEFAULT NULL,
  PRIMARY KEY (`techID`),
  UNIQUE KEY `uq_lab_technicians_labtech` (`labtechID_FK`),
  CONSTRAINT `lab_technicians_ibfk_1` FOREIGN KEY (`labtechID_FK`) REFERENCES `subscribers` (`subID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lab_test_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lab_test_items` (
  `lbitemID` int NOT NULL AUTO_INCREMENT,
  `testID_FK` int DEFAULT NULL,
  `test_date` date DEFAULT NULL,
  `result_date` date DEFAULT NULL,
  `test_result` varchar(255) DEFAULT NULL,
  `test_verdict` varchar(255) DEFAULT NULL,
  `requestID_FK` int DEFAULT NULL,
  PRIMARY KEY (`lbitemID`),
  KEY `labtestID_FK` (`requestID_FK`),
  KEY `testID_FK` (`testID_FK`),
  KEY `idx_lab_items_request_result` (`requestID_FK`,`result_date`),
  CONSTRAINT `lab_test_items_ibfk_1` FOREIGN KEY (`requestID_FK`) REFERENCES `lab_requests` (`labtestID`),
  CONSTRAINT `requestID_FK` FOREIGN KEY (`requestID_FK`) REFERENCES `lab_requests` (`labtestID`),
  CONSTRAINT `testID_FK` FOREIGN KEY (`testID_FK`) REFERENCES `lab_tests_catalog` (`testID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lab_tests_catalog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lab_tests_catalog` (
  `testID` int NOT NULL AUTO_INCREMENT,
  `test_name` varchar(255) NOT NULL,
  `test_type` varchar(100) NOT NULL,
  PRIMARY KEY (`testID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `messages` (
  `messageID` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`messageID`),
  KEY `idx_messages_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prescription_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prescription_items` (
  `presitemID` int NOT NULL AUTO_INCREMENT,
  `drug_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `strength` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `dosage_amt` varchar(50) DEFAULT NULL,
  `dosage_frequency` varchar(50) DEFAULT NULL,
  `dosage_duration` varchar(50) DEFAULT NULL,
  `presID_FK` int DEFAULT NULL,
  PRIMARY KEY (`presitemID`),
  KEY `presID_FK` (`presID_FK`),
  CONSTRAINT `prescription_items_ibfk_1` FOREIGN KEY (`presID_FK`) REFERENCES `prescriptions` (`presID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prescriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prescriptions` (
  `presID` int NOT NULL AUTO_INCREMENT,
  `prescribe_date` date DEFAULT (curdate()),
  `patientID_FK` int DEFAULT NULL,
  `doctorID_FK` int DEFAULT NULL,
  `hvisitID_FK` int DEFAULT NULL,
  PRIMARY KEY (`presID`),
  KEY `hvisit_id_FK` (`hvisitID_FK`),
  KEY `idx_prescriptions_patient_date` (`patientID_FK`,`prescribe_date`),
  KEY `idx_prescriptions_doctor_date` (`doctorID_FK`,`prescribe_date`),
  CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`patientID_FK`) REFERENCES `subscribers` (`subID`),
  CONSTRAINT `prescriptions_ibfk_2` FOREIGN KEY (`doctorID_FK`) REFERENCES `subscribers` (`subID`),
  CONSTRAINT `prescriptions_ibfk_3` FOREIGN KEY (`hvisitID_FK`) REFERENCES `health_visits` (`hvisitID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subscribers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscribers` (
  `subID` int NOT NULL AUTO_INCREMENT,
  `fname` varchar(50) NOT NULL,
  `mname` varchar(50) NOT NULL,
  `lname` varchar(50) NOT NULL,
  `DOB` date DEFAULT NULL,
  `gender` varchar(50) DEFAULT NULL,
  `phone_no` int NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `nok_name` varchar(50) NOT NULL,
  `nok_phone_no` int NOT NULL,
  `sub_role` varchar(255) DEFAULT 'patient',
  `acc_creation_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `pwd` varchar(255) NOT NULL,
  `region` varchar(80) NOT NULL,
  `guardianID` int DEFAULT NULL,
  PRIMARY KEY (`subID`),
  KEY `idx_subscribers_email` (`email`),
  KEY `idx_subscribers_guardian` (`guardianID`),
  KEY `idx_subscribers_region` (`region`),
  KEY `idx_subscribers_role` (`sub_role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vitals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vitals` (
  `vitalsID` int NOT NULL AUTO_INCREMENT,
  `date_of_reading` date DEFAULT NULL,
  `user_weight` float DEFAULT NULL,
  `user_height` float DEFAULT NULL,
  `user_BMI` float DEFAULT NULL,
  `blood_pressure_systolic` int DEFAULT NULL,
  `blood_pressure_diastolic` int DEFAULT NULL,
  `heart_rate` int DEFAULT NULL,
  `blood_glucose` int DEFAULT NULL,
  `symptoms` varchar(100) DEFAULT NULL,
  `subID_FK` int DEFAULT NULL,
  `temperature` float DEFAULT NULL,
  PRIMARY KEY (`vitalsID`),
  KEY `subID_FK` (`subID_FK`),
  KEY `idx_vitals_patient_date` (`subID_FK`,`date_of_reading`),
  CONSTRAINT `vitals_ibfk_1` FOREIGN KEY (`subID_FK`) REFERENCES `subscribers` (`subID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

