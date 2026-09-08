-- COLLATION COMPATIBILITY UPDATE
-- All utf8mb4 collations in this canonical integration schema are standardized to
-- utf8mb4_unicode_ci for compatibility across the combined Nureen, Aidid, Izz, Tya,
-- and Syiqin modules and with MySQL/MariaDB environments that do not support
-- the unsupported MySQL 8-only collation.

-- ============================================================
-- AL AMIN EDU OASIS - CANONICAL COMBINED DATABASE (SCHEMA ONLY)
-- Pre-INSERT integration version
-- Sources: Nureen, Aidid, Izz, Tya, Syiqin
--
-- Key canonical decisions applied:
-- - applicant/application removed.
-- - Business master IDs use VARCHAR according to their owner schemas.
-- - teacher/serviceDate is DATE; created_at/updated_at added.
-- - course/course_session/trainer follow Nureen + timestamps.
-- - guru_new follows Syiqin + created_at/updated_at timestamps; service_date and remember_token removed by owner request.
-- - Aidid-owned learning/assessment tables retain Aidid column names but
--   shared IDs use canonical VARCHAR types/FKs.
-- - users follows Izz.
-- - Laravel infrastructure is shared by Tya + Izz using one InnoDB schema.
-- - sp_review_proposal intentionally removed.
-- - Nureen supplied functions/procedures included; DEFINER removed.
-- - pre_response class/subject/observation date are nullable.
-- - INSERT data intentionally deferred to the next integration phase.
-- - sp_register_guru_new updated to set created_at/updated_at with NOW().
-- ============================================================

SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;
SET UNIQUE_CHECKS = 0;
SET NAMES utf8mb4;

DROP DATABASE IF EXISTS `alamin_main_database`;
CREATE DATABASE `alamin_main_database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `alamin_main_database`;

-- Laravel shared-infrastructure deployment note:
-- Tya and Izz can share cache/cache_locks/jobs/job_batches/failed_jobs/sessions
-- when each Laravel app uses distinct cache prefixes, session cookie names and
-- queue names. For migration history, configure separate migration repository
-- table names per application before production deployment to avoid migration-
-- name collisions. This schema keeps the source-compatible `migrations` table;
-- no migration-history INSERTs are included yet.

-- ============================================================
-- TABLES
-- ============================================================

-- assessment | OWNER: Aidid
DROP TABLE IF EXISTS `assessment`;
CREATE TABLE `assessment` (
  `assessment_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `course_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Draft',
  `module_id` int DEFAULT NULL,
  `instruction_text` text COLLATE utf8mb4_unicode_ci,
  `available_from` datetime DEFAULT NULL,
  `available_until` datetime DEFAULT NULL,
  `accepted_format` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `time_limit_minutes` int DEFAULT NULL,
  `max_attempts` int DEFAULT NULL COMMENT 'Quiz max attempts. NULL = unlimited.',
  `total_marks` decimal(6,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `weight_percent` decimal(5,2) DEFAULT NULL COMMENT 'Quiz weight in overall course grade (e.g. 10.00 = 10% of course)',
  PRIMARY KEY (`assessment_id`),
  KEY `assessment_course_id_foreign` (`course_id`),
  KEY `fk_assessment_module` (`module_id`),
  CONSTRAINT `assessment_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `course` (`courseID`) ON UPDATE NO ACTION ON DELETE CASCADE,
  CONSTRAINT `fk_assessment_module` FOREIGN KEY (`module_id`) REFERENCES `training_module` (`module_id`) ON UPDATE NO ACTION ON DELETE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- assessment_question | OWNER: Aidid
DROP TABLE IF EXISTS `assessment_question`;
CREATE TABLE `assessment_question` (
  `question_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `assessment_id` bigint unsigned NOT NULL,
  `question_text` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `question_image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `question_order` int DEFAULT '1',
  `question_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `options_json` json DEFAULT NULL,
  `correct_answer` text COLLATE utf8mb4_unicode_ci,
  `marks` decimal(5,2) DEFAULT '1.00',
  `grading_rubric` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`question_id`),
  KEY `assessment_question_assessment_id_foreign` (`assessment_id`),
  CONSTRAINT `assessment_question_assessment_id_foreign` FOREIGN KEY (`assessment_id`) REFERENCES `assessment` (`assessment_id`) ON UPDATE NO ACTION ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- assessment_submission | OWNER: Izz
DROP TABLE IF EXISTS `assessment_submission`;
CREATE TABLE `assessment_submission` (
  `submission_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `assessment_id` bigint unsigned NOT NULL,
  `enrollment_id` bigint unsigned NOT NULL,
  `submitted_file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `original_file_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `answers_json` json DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `grade` decimal(8,2) DEFAULT NULL,
  `feedback` text COLLATE utf8mb4_unicode_ci,
  `graded_by_trainer_id` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `graded_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`submission_id`),
  KEY `assessment_submission_assessment_id_foreign` (`assessment_id`),
  CONSTRAINT `assessment_submission_assessment_id_foreign` FOREIGN KEY (`assessment_id`) REFERENCES `assessment` (`assessment_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- assign | OWNER: Tya (canonical shared; InnoDB + FKs)
DROP TABLE IF EXISTS `assign`;
CREATE TABLE `assign` (
  `schoolID` varchar(50) NOT NULL,
  `teacherID` varchar(20) NOT NULL,
  `assignDate` date DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`schoolID`,`teacherID`),
  KEY `idx_assign_teacher_status` (`teacherID`,`status`),
  KEY `idx_assign_school_status` (`schoolID`,`status`),
  KEY `idx_assign_date` (`assignDate`),
  KEY `idx_assign_status` (`status`),
  CONSTRAINT `fk_assign_school` FOREIGN KEY (`schoolID`) REFERENCES `school` (`schoolID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_assign_teacher` FOREIGN KEY (`teacherID`) REFERENCES `teacher` (`teacherID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- attendance | OWNER: Nureen
DROP TABLE IF EXISTS `attendance`;
CREATE TABLE `attendance` (
  `attendance_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `timestamp_status` decimal(5,2) NOT NULL DEFAULT 0.00,
  `hours_ladap` decimal(5,2) NOT NULL DEFAULT 0.00,
  `teacher_id` varchar(20) NOT NULL,
  `session_id` varchar(20) NOT NULL,
  `attendance_status` varchar(20) NOT NULL DEFAULT 'pending',
  `scanned_at` datetime DEFAULT current_timestamp(),
  `approvedByStaff` varchar(20) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`attendance_id`),
  UNIQUE KEY `uq_attendance_teacher_session` (`teacher_id`,`session_id`),
  KEY `idx_attendance_session` (`session_id`),
  KEY `idx_attendance_approved_by` (`approvedByStaff`),
  CONSTRAINT `fk_attendance_approver` FOREIGN KEY (`approvedByStaff`) REFERENCES `staff_edu` (`staffID`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_attendance_session` FOREIGN KEY (`session_id`) REFERENCES `course_session` (`sessionID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_attendance_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teacher` (`teacherID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_attendance_1` CHECK (`attendance_status` in ('pending','approved','rejected'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- attendance_guru_baru | OWNER: Nureen
DROP TABLE IF EXISTS `attendance_guru_baru`;
CREATE TABLE `attendance_guru_baru` (
  `attendGuruBaru_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `timestamp_status` decimal(5,2) NOT NULL DEFAULT 0.00,
  `hours_ladap` decimal(5,2) NOT NULL DEFAULT 0.00,
  `gn_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `session_id` varchar(20) NOT NULL,
  `attendance_status` varchar(20) NOT NULL DEFAULT 'pending',
  `scanned_at` datetime DEFAULT current_timestamp(),
  `approvedByStaff` varchar(20) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`attendGuruBaru_id`),
  UNIQUE KEY `uq_attendance_guru_baru_session` (`gn_id`,`session_id`),
  KEY `idx_attendance_guru_baru_session` (`session_id`),
  KEY `idx_attendance_guru_baru_approved_by` (`approvedByStaff`),
  CONSTRAINT `fk_attendance_guru_baru_approver` FOREIGN KEY (`approvedByStaff`) REFERENCES `staff_edu` (`staffID`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_attendance_guru_baru_gn` FOREIGN KEY (`gn_id`) REFERENCES `guru_new` (`gn_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_attendance_guru_baru_session` FOREIGN KEY (`session_id`) REFERENCES `course_session` (`sessionID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_attendance_guru_baru_1` CHECK (`attendance_status` in ('pending','approved','rejected'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- attendance_outsider | OWNER: Nureen
DROP TABLE IF EXISTS `attendance_outsider`;
CREATE TABLE `attendance_outsider` (
  `attendOutsider_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `timestamp_status` decimal(5,2) NOT NULL DEFAULT 0.00,
  `outsider_id` bigint(20) unsigned NOT NULL,
  `session_id` varchar(20) NOT NULL,
  `attendance_status` varchar(20) NOT NULL DEFAULT 'pending',
  `scanned_at` datetime DEFAULT current_timestamp(),
  `approvedByStaff` varchar(20) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`attendOutsider_id`),
  UNIQUE KEY `uq_attendance_outsider_session` (`outsider_id`,`session_id`),
  KEY `idx_attendance_outsider_session` (`session_id`),
  KEY `idx_attendance_outsider_approved_by` (`approvedByStaff`),
  CONSTRAINT `fk_attendance_outsider_approver` FOREIGN KEY (`approvedByStaff`) REFERENCES `staff_edu` (`staffID`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_attendance_outsider_person` FOREIGN KEY (`outsider_id`) REFERENCES `outsider` (`outsider_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_attendance_outsider_session` FOREIGN KEY (`session_id`) REFERENCES `course_session` (`sessionID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_attendance_outsider_1` CHECK (`attendance_status` in ('pending','approved','rejected'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- attendance_staff | OWNER: Nureen
DROP TABLE IF EXISTS `attendance_staff`;
CREATE TABLE `attendance_staff` (
  `attendanceStaff_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `timestamp_status` decimal(5,2) NOT NULL DEFAULT 0.00,
  `hours_ladap` decimal(5,2) NOT NULL DEFAULT 0.00,
  `staffID` varchar(20) NOT NULL,
  `session_id` varchar(20) NOT NULL,
  `attendance_status` varchar(20) NOT NULL DEFAULT 'pending',
  `scanned_at` datetime DEFAULT current_timestamp(),
  `approvedByStaff` varchar(20) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`attendanceStaff_id`),
  UNIQUE KEY `uq_attendance_staff_session` (`staffID`,`session_id`),
  KEY `idx_attendance_staff_session` (`session_id`),
  KEY `idx_attendance_staff_approved_by` (`approvedByStaff`),
  CONSTRAINT `fk_attendance_staff_approver` FOREIGN KEY (`approvedByStaff`) REFERENCES `staff_edu` (`staffID`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_attendance_staff_session` FOREIGN KEY (`session_id`) REFERENCES `course_session` (`sessionID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_attendance_staff_staff` FOREIGN KEY (`staffID`) REFERENCES `staff_edu` (`staffID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_attendance_staff_1` CHECK (`attendance_status` in ('pending','approved','rejected'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- audit_log | OWNER: Nureen
DROP TABLE IF EXISTS `audit_log`;
CREATE TABLE `audit_log` (
  `logID` int(11) NOT NULL AUTO_INCREMENT,
  `staffID` varchar(20) DEFAULT NULL,
  `userName` varchar(250) DEFAULT NULL,
  `actionType` varchar(20) NOT NULL,
  `tableName` varchar(100) NOT NULL,
  `newValue` text DEFAULT NULL,
  `actionDate` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`logID`),
  KEY `idx_audit_log_staff` (`staffID`),
  KEY `idx_audit_log_action_date` (`actionDate`),
  CONSTRAINT `fk_audit_log_staff` FOREIGN KEY (`staffID`) REFERENCES `staff_edu` (`staffID`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_audit_log_1` CHECK (`actionType` in ('INSERT','UPDATE','DELETE','LOGIN','LOGOUT'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- audit_logs | OWNER: Izz
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_id` bigint unsigned NOT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `audit_logs_user_id_foreign` (`user_id`),
  CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- audit_observation | OWNER: Syiqin
DROP TABLE IF EXISTS `audit_observation`;
CREATE TABLE `audit_observation` (
  `audit_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `teacherID` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gn_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('Observer','External Observer') NOT NULL,
  `stage` enum('PRE','EXTERNAL','POST') NOT NULL,
  `form_name` varchar(255) NOT NULL,
  `action` enum('Submitted') NOT NULL,
  `audit_date` date NOT NULL,
  `audit_time` time NOT NULL,
  PRIMARY KEY (`audit_id`),
  KEY `fk_audit_teacher` (`teacherID`),
  KEY `fk_audit_guru_new` (`gn_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- cache | OWNER: Shared Laravel (Tya + Izz)
DROP TABLE IF EXISTS `cache`;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- cache_locks | OWNER: Shared Laravel (Tya + Izz)
DROP TABLE IF EXISTS `cache_locks`;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- certificate | OWNER: Nureen
DROP TABLE IF EXISTS `certificate`;
CREATE TABLE `certificate` (
  `certificateID` varchar(20) NOT NULL,
  `generatedDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `generatedCertificate` varchar(255) NOT NULL,
  `generatedByStaff` varchar(20) NOT NULL,
  `status` varchar(20) DEFAULT 'generated',
  `participantID` varchar(20) NOT NULL,
  `courseID` varchar(20) NOT NULL,
  `sessionID` varchar(20) NOT NULL,
  `trainerID` varchar(20) NOT NULL,
  `templateID` varchar(20) NOT NULL,
  PRIMARY KEY (`certificateID`),
  KEY `idx_certificate_staff` (`generatedByStaff`),
  KEY `idx_certificate_participant` (`participantID`),
  KEY `idx_certificate_course` (`courseID`),
  KEY `idx_certificate_session` (`sessionID`),
  KEY `idx_certificate_trainer` (`trainerID`),
  KEY `idx_certificate_template` (`templateID`),
  CONSTRAINT `fk_certificate_course` FOREIGN KEY (`courseID`) REFERENCES `course` (`courseID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_certificate_participant` FOREIGN KEY (`participantID`) REFERENCES `course_participant` (`participantID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_certificate_session` FOREIGN KEY (`sessionID`) REFERENCES `course_session` (`sessionID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_certificate_staff` FOREIGN KEY (`generatedByStaff`) REFERENCES `staff_edu` (`staffID`) ON UPDATE CASCADE,
  CONSTRAINT `fk_certificate_template` FOREIGN KEY (`templateID`) REFERENCES `certificate_template` (`templateID`) ON UPDATE CASCADE,
  CONSTRAINT `fk_certificate_trainer` FOREIGN KEY (`trainerID`) REFERENCES `trainer` (`trainerID`) ON UPDATE CASCADE,
  CONSTRAINT `chk_certificate_1` CHECK (`status` in ('generated','revoked')),
  CONSTRAINT `chk_certificate_2` CHECK (`generatedCertificate` like '%.pdf' or `generatedCertificate` like '%.jpg' or `generatedCertificate` like '%.jpeg' or `generatedCertificate` like '%.png')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- certificate_position | OWNER: Nureen
DROP TABLE IF EXISTS `certificate_position`;
CREATE TABLE `certificate_position` (
  `positionID` varchar(20) NOT NULL,
  `fieldName` varchar(50) NOT NULL,
  `positionX` decimal(10,2) NOT NULL,
  `positionY` decimal(10,2) NOT NULL,
  `fontSize` int(11) DEFAULT 16,
  `fontFamily` varchar(100) DEFAULT 'Arial',
  `fontColor` varchar(20) DEFAULT '#000000',
  `templateID` varchar(20) NOT NULL,
  PRIMARY KEY (`positionID`),
  UNIQUE KEY `uq_certificate_position_template_field` (`templateID`,`fieldName`),
  CONSTRAINT `fk_certificate_position_template` FOREIGN KEY (`templateID`) REFERENCES `certificate_template` (`templateID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_certificate_position_1` CHECK (`fieldName` in ('participant_name','course_name','session_date','trainer_name','generated_date','custom_text_1','signature_image','signature_name','signature_title'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- certificate_template | OWNER: Nureen
DROP TABLE IF EXISTS `certificate_template`;
CREATE TABLE `certificate_template` (
  `templateID` varchar(20) NOT NULL,
  `templateName` varchar(255) NOT NULL,
  `templateFile` varchar(255) NOT NULL,
  `uploadedByStaff` varchar(20) NOT NULL,
  `uploadDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) DEFAULT 'active',
  PRIMARY KEY (`templateID`),
  KEY `idx_certificate_template_staff` (`uploadedByStaff`),
  CONSTRAINT `fk_certificate_template_staff` FOREIGN KEY (`uploadedByStaff`) REFERENCES `staff_edu` (`staffID`) ON UPDATE CASCADE,
  CONSTRAINT `chk_certificate_template_1` CHECK (`status` in ('active','inactive')),
  CONSTRAINT `chk_certificate_template_2` CHECK (`templateFile` like '%.pdf' or `templateFile` like '%.jpg' or `templateFile` like '%.jpeg' or `templateFile` like '%.png')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- course | OWNER: Nureen (canonical shared; timestamps added)
DROP TABLE IF EXISTS `course`;
CREATE TABLE `course` (
  `courseID` varchar(20) NOT NULL,
  `courseName` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `capacity` int(11) NOT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `courseCategory` varchar(100) DEFAULT NULL COMMENT 'Free-written type/category of course, e.g. Technology, Leadership, Tarbiah',
  `targetAudience` set('teacher','new_teacher','staff','public','other') NOT NULL DEFAULT 'teacher' COMMENT 'Multi-select target audience stored in course table',
  `otherTargetAudience` varchar(255) DEFAULT NULL COMMENT 'Required when targetAudience contains other',
  `staffAttendeeIDs` text DEFAULT NULL COMMENT 'Comma-separated staff_edu.staffID values for Staff EDU who will attend this course',
  `staffAttendeeAssignedBy` varchar(20) DEFAULT NULL,
  `staffAttendeeAssignedDate` date DEFAULT NULL,
  `courseRating` decimal(3,2) DEFAULT 0.00,
  `status` varchar(20) DEFAULT 'upcoming',
  `poster` varchar(255) DEFAULT NULL,
  `mode` varchar(20) DEFAULT NULL,
  `onlineLink` varchar(255) DEFAULT NULL,
  `whatsappGroup` varchar(255) DEFAULT NULL,
  `closeDate` date DEFAULT NULL,
  `organiserName` varchar(255) DEFAULT NULL,
  `courseType` varchar(20) DEFAULT NULL COMMENT 'LMS only when targetAudience is new_teacher only; Simple for other audiences',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`courseID`),
  KEY `idx_course_targetAudience` (`targetAudience`),
  KEY `idx_course_attendee_assigned_by` (`staffAttendeeAssignedBy`),
  CONSTRAINT `fk_course_attendee_assigned_by` FOREIGN KEY (`staffAttendeeAssignedBy`) REFERENCES `staff_edu` (`staffID`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_course_1` CHECK (`capacity` > 0),
  CONSTRAINT `chk_course_2` CHECK (`price` is null or `price` >= 0),
  CONSTRAINT `chk_course_3` CHECK (`courseRating` between 0 and 5),
  CONSTRAINT `chk_course_4` CHECK (`status` in ('upcoming','ongoing','completed','cancelled')),
  CONSTRAINT `chk_course_5` CHECK (`mode` in ('online','physical','hybrid')),
  CONSTRAINT `chk_course_6` CHECK (`courseType` in ('LMS','Simple')),
  CONSTRAINT `chk_course_7` CHECK (`poster` is null or `poster` like '%.jpg' or `poster` like '%.jpeg' or `poster` like '%.png' or `poster` like '%.pdf')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- course_participant | OWNER: Nureen
DROP TABLE IF EXISTS `course_participant`;
CREATE TABLE `course_participant` (
  `participantID` varchar(20) NOT NULL,
  `participantType` varchar(20) NOT NULL,
  `participantName` varchar(255) NOT NULL,
  `organisationName` varchar(255) DEFAULT NULL,
  `ICNumber` varchar(20) DEFAULT NULL,
  `phoneNumber` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `paymentProof` varchar(255) DEFAULT NULL,
  `RSVPStatus` varchar(30) DEFAULT 'Will come',
  `isFeedbackCompleted` tinyint(1) DEFAULT 0,
  `replacementStatus` varchar(20) DEFAULT 'original',
  `replacedByName` varchar(255) DEFAULT NULL,
  `replacementReason` text DEFAULT NULL,
  `courseID` varchar(20) NOT NULL,
  `gn_id` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `outsider_id` bigint(20) unsigned DEFAULT NULL,
  `teacherID` varchar(20) DEFAULT NULL,
  `staffID` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`participantID`),
  KEY `idx_course_participant_course` (`courseID`),
  KEY `idx_course_participant_gn` (`gn_id`),
  KEY `idx_course_participant_outsider` (`outsider_id`),
  KEY `idx_course_participant_teacher` (`teacherID`),
  KEY `idx_course_participant_staff` (`staffID`),
  CONSTRAINT `fk_course_participant_course` FOREIGN KEY (`courseID`) REFERENCES `course` (`courseID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_course_participant_gn` FOREIGN KEY (`gn_id`) REFERENCES `guru_new` (`gn_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_course_participant_outsider` FOREIGN KEY (`outsider_id`) REFERENCES `outsider` (`outsider_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_course_participant_staff` FOREIGN KEY (`staffID`) REFERENCES `staff_edu` (`staffID`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_course_participant_teacher` FOREIGN KEY (`teacherID`) REFERENCES `teacher` (`teacherID`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_course_participant_1` CHECK (`participantType` in ('teacher','new_teacher','staff','public')),
  CONSTRAINT `chk_course_participant_2` CHECK (`RSVPStatus` in ('Will come','Cannot attend','Pending'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- course_proposal | OWNER: Aidid
DROP TABLE IF EXISTS `course_proposal`;
CREATE TABLE `course_proposal` (
  `proposal_id` int unsigned NOT NULL AUTO_INCREMENT,
  `trainer_id` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proposed_title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `course_category` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target_audience` text COLLATE utf8mb4_unicode_ci,
  `objective` text COLLATE utf8mb4_unicode_ci,
  `description` text COLLATE utf8mb4_unicode_ci,
  `training_mode` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_mode` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estimated_capacity` int DEFAULT NULL,
  `proposed_start_date` date DEFAULT NULL,
  `proposed_end_date` date DEFAULT NULL,
  `document_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `document_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pending',
  `admin_comment` text COLLATE utf8mb4_unicode_ci,
  `reviewed_by` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`proposal_id`),
  KEY `idx_proposal_trainer` (`trainer_id`),
  KEY `idx_proposal_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- course_session | OWNER: Nureen (canonical shared; timestamps added)
DROP TABLE IF EXISTS `course_session`;
CREATE TABLE `course_session` (
  `sessionID` varchar(20) NOT NULL,
  `sessionDate` date NOT NULL,
  `sessionName` varchar(255) NOT NULL,
  `startTime` time NOT NULL,
  `endTime` time NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `courseID` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`sessionID`),
  KEY `idx_course_session_course` (`courseID`,`sessionDate`),
  CONSTRAINT `fk_course_session_course` FOREIGN KEY (`courseID`) REFERENCES `course` (`courseID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- document | OWNER: Tya
DROP TABLE IF EXISTS `document`;
CREATE TABLE `document` (
  `documentID` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gn_id` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `applicant_id` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `documentType` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `documentDescription` text COLLATE utf8mb4_unicode_ci,
  `dateIssued` date DEFAULT NULL,
  `signedBy` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hrid` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `teacherID` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pdfFile` longblob,
  `documentTitle` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `filePath` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`documentID`),
  KEY `gn_id` (`gn_id`),
  KEY `hrid` (`hrid`),
  KEY `teacherID` (`teacherID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- enroll_guru_baru | OWNER: Nureen
DROP TABLE IF EXISTS `enroll_guru_baru`;
CREATE TABLE `enroll_guru_baru` (
  `enrollment_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `gn_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `course_id` varchar(20) NOT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `applied_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`enrollment_id`),
  UNIQUE KEY `uq_enroll_guru_baru_gn_course` (`gn_id`,`course_id`),
  KEY `idx_enroll_guru_baru_course` (`course_id`),
  CONSTRAINT `fk_enroll_guru_baru_course` FOREIGN KEY (`course_id`) REFERENCES `course` (`courseID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_enroll_guru_baru_gn` FOREIGN KEY (`gn_id`) REFERENCES `guru_new` (`gn_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_enroll_guru_baru_1` CHECK (`status` in ('pending','approved','rejected','auto_enrolled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- enroll_outsider | OWNER: Nureen
DROP TABLE IF EXISTS `enroll_outsider`;
CREATE TABLE `enroll_outsider` (
  `outsider_id` bigint(20) unsigned NOT NULL,
  `course_id` varchar(20) NOT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `applied_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`outsider_id`,`course_id`),
  KEY `idx_enroll_outsider_course` (`course_id`),
  CONSTRAINT `fk_enroll_outsider_course` FOREIGN KEY (`course_id`) REFERENCES `course` (`courseID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_enroll_outsider_person` FOREIGN KEY (`outsider_id`) REFERENCES `outsider` (`outsider_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_enroll_outsider_1` CHECK (`status` in ('pending','approved','rejected'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- enroll_teacher | OWNER: Nureen
DROP TABLE IF EXISTS `enroll_teacher`;
CREATE TABLE `enroll_teacher` (
  `teacherID` varchar(20) NOT NULL,
  `course_id` varchar(20) NOT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `applied_at` datetime DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`teacherID`,`course_id`),
  KEY `idx_enroll_teacher_course` (`course_id`),
  CONSTRAINT `fk_enroll_teacher_course` FOREIGN KEY (`course_id`) REFERENCES `course` (`courseID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_enroll_teacher_teacher` FOREIGN KEY (`teacherID`) REFERENCES `teacher` (`teacherID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_enroll_teacher_1` CHECK (`status` in ('pending','approved','rejected'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- evaluation_doc | OWNER: Syiqin
DROP TABLE IF EXISTS `evaluation_doc`;
CREATE TABLE `evaluation_doc` (
  `doc_id` int NOT NULL AUTO_INCREMENT,
  `form_name` varchar(150) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(20) NOT NULL,
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `staffID` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`doc_id`),
  KEY `fk_evaluation_doc_staff` (`staffID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- external_observer | OWNER: Nureen (canonical shared)
DROP TABLE IF EXISTS `external_observer`;
CREATE TABLE `external_observer` (
  `externalObserverID` varchar(20) NOT NULL,
  `startDate` date NOT NULL,
  `endDate` date DEFAULT NULL,
  `status` varchar(20) DEFAULT 'active',
  `teacherID` varchar(20) NOT NULL,
  PRIMARY KEY (`externalObserverID`),
  KEY `idx_external_observer_teacher` (`teacherID`),
  CONSTRAINT `fk_external_observer_teacher` FOREIGN KEY (`teacherID`) REFERENCES `teacher` (`teacherID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_external_observer_1` CHECK (`status` in ('active','inactive'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- failed_jobs | OWNER: Shared Laravel (Tya + Izz)
DROP TABLE IF EXISTS `failed_jobs`;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- feedback_category | OWNER: Nureen
DROP TABLE IF EXISTS `feedback_category`;
CREATE TABLE `feedback_category` (
  `categoryID` varchar(20) NOT NULL,
  `categoryName` varchar(100) NOT NULL,
  `categoryOrder` int(11) DEFAULT 1,
  `formID` varchar(20) NOT NULL,
  PRIMARY KEY (`categoryID`),
  KEY `idx_feedback_category_form` (`formID`,`categoryOrder`),
  CONSTRAINT `fk_feedback_category_form` FOREIGN KEY (`formID`) REFERENCES `feedback_form` (`formID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- feedback_form | OWNER: Nureen
DROP TABLE IF EXISTS `feedback_form`;
CREATE TABLE `feedback_form` (
  `formID` varchar(20) NOT NULL,
  `title` varchar(255) NOT NULL,
  `createdByStaff` varchar(20) NOT NULL,
  `createdDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `sessionID` varchar(20) DEFAULT NULL,
  `courseID` varchar(20) DEFAULT NULL,
  `feedbackType` varchar(20) DEFAULT 'participant',
  `generatedPdf` varchar(255) DEFAULT NULL COMMENT 'Generated PDF report file path for all feedback responses',
  `pdfGeneratedByStaff` varchar(20) DEFAULT NULL,
  `pdfGeneratedAt` datetime DEFAULT NULL,
  PRIMARY KEY (`formID`),
  KEY `idx_feedback_form_staff` (`createdByStaff`),
  KEY `idx_feedback_form_session` (`sessionID`),
  KEY `idx_feedback_form_course` (`courseID`),
  KEY `idx_feedback_form_pdf_staff` (`pdfGeneratedByStaff`),
  CONSTRAINT `fk_feedback_form_course` FOREIGN KEY (`courseID`) REFERENCES `course` (`courseID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_feedback_form_pdf_staff` FOREIGN KEY (`pdfGeneratedByStaff`) REFERENCES `staff_edu` (`staffID`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_feedback_form_session` FOREIGN KEY (`sessionID`) REFERENCES `course_session` (`sessionID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_feedback_form_staff` FOREIGN KEY (`createdByStaff`) REFERENCES `staff_edu` (`staffID`) ON UPDATE CASCADE,
  CONSTRAINT `chk_feedback_form_1` CHECK (`feedbackType` in ('participant','staff_edu')),
  CONSTRAINT `chk_feedback_form_3` CHECK (`generatedPdf` is null or `generatedPdf` like '%.pdf')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- feedback_question | OWNER: Nureen
DROP TABLE IF EXISTS `feedback_question`;
CREATE TABLE `feedback_question` (
  `questionID` varchar(20) NOT NULL,
  `questionText` text NOT NULL,
  `questionType` enum('rating','paragraph','image') DEFAULT 'rating',
  `questionImage` varchar(255) DEFAULT NULL COMMENT 'Image file path when questionType is image',
  `isRequired` tinyint(1) DEFAULT 1,
  `questionOrder` int(11) DEFAULT 1,
  `categoryID` varchar(20) NOT NULL,
  PRIMARY KEY (`questionID`),
  KEY `idx_feedback_question_category` (`categoryID`,`questionOrder`),
  CONSTRAINT `fk_feedback_question_category` FOREIGN KEY (`categoryID`) REFERENCES `feedback_category` (`categoryID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_feedback_question_2` CHECK (`questionImage` is null or `questionImage` like '%.jpg' or `questionImage` like '%.jpeg' or `questionImage` like '%.png')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- feedback_response | OWNER: Nureen
DROP TABLE IF EXISTS `feedback_response`;
CREATE TABLE `feedback_response` (
  `responseID` varchar(20) NOT NULL,
  `rating` int(11) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `responseDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `participantID` varchar(20) DEFAULT NULL,
  `questionID` varchar(20) NOT NULL,
  `staffID` varchar(20) DEFAULT NULL,
  `responseType` varchar(20) DEFAULT 'participant',
  PRIMARY KEY (`responseID`),
  UNIQUE KEY `uq_feedback_response_participant_question` (`participantID`,`questionID`),
  KEY `idx_feedback_response_question` (`questionID`),
  KEY `idx_feedback_response_staff` (`staffID`),
  CONSTRAINT `fk_feedback_response_participant` FOREIGN KEY (`participantID`) REFERENCES `course_participant` (`participantID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_feedback_response_question` FOREIGN KEY (`questionID`) REFERENCES `feedback_question` (`questionID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_feedback_response_staff` FOREIGN KEY (`staffID`) REFERENCES `staff_edu` (`staffID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_feedback_response_1` CHECK (`rating` is null or `rating` between 1 and 5),
  CONSTRAINT `chk_feedback_response_2` CHECK (`responseType` in ('participant','staff_edu'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- guru_new | OWNER: Syiqin (canonical shared)
DROP TABLE IF EXISTS `guru_new`;
CREATE TABLE `guru_new` (
  `gn_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ic_number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gn_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone_number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `marital_status` enum('Single','Married','Divorced') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gender` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `race` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `appointed_date` date NOT NULL,
  `role` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'new_teacher',
  `current_status` enum('Inactive','Active','Complete') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Inactive',
  `hrid` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `schoolID` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`gn_id`),
  UNIQUE KEY `ic_number` (`ic_number`),
  UNIQUE KEY `email` (`email`),
  KEY `hrID` (`hrid`),
  KEY `idx_guru_new_school_status` (`schoolID`,`current_status`),
  CONSTRAINT `fk_guru_new_hr` FOREIGN KEY (`hrid`) REFERENCES `hr_administrator` (`hrid`) ON UPDATE CASCADE,
  CONSTRAINT `fk_guru_new_school` FOREIGN KEY (`schoolID`) REFERENCES `school` (`schoolID`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- hr_administrator | OWNER: Tya (canonical shared; timestamps + unique email)
DROP TABLE IF EXISTS `hr_administrator`;
CREATE TABLE `hr_administrator` (
  `hrid` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone_number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'hr',
  `password_change_required` bit(1) DEFAULT b'0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`hrid`),
  UNIQUE KEY `uq_hr_administrator_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- job_batches | OWNER: Shared Laravel (Tya + Izz)
DROP TABLE IF EXISTS `job_batches`;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- jobs | OWNER: Shared Laravel (Tya + Izz)
DROP TABLE IF EXISTS `jobs`;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- learning_material | OWNER: Aidid
DROP TABLE IF EXISTS `learning_material`;
CREATE TABLE `learning_material` (
  `material_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `course_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `trainer_id` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `session_id` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `original_file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `material_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadata_cleared` tinyint(1) NOT NULL DEFAULT '0',
  `extracted_metadata` text COLLATE utf8mb4_unicode_ci,
  `ai_generated_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ai_synopsis` text COLLATE utf8mb4_unicode_ci,
  `uploaded_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`material_id`),
  KEY `learning_material_course_id_foreign` (`course_id`),
  KEY `learning_material_trainer_id_foreign` (`trainer_id`),
  CONSTRAINT `learning_material_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `course` (`courseID`) ON UPDATE NO ACTION ON DELETE CASCADE,
  CONSTRAINT `learning_material_trainer_id_foreign` FOREIGN KEY (`trainer_id`) REFERENCES `trainer` (`trainerID`) ON UPDATE NO ACTION ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- login_session | OWNER: Nureen
DROP TABLE IF EXISTS `login_session`;
CREATE TABLE `login_session` (
  `sessionID` int(11) NOT NULL AUTO_INCREMENT,
  `loginTime` timestamp NOT NULL DEFAULT current_timestamp(),
  `logoutTime` timestamp NULL DEFAULT NULL,
  `sessionStatus` varchar(20) DEFAULT 'active',
  `staffID` varchar(20) NOT NULL,
  PRIMARY KEY (`sessionID`),
  KEY `idx_login_session_staff` (`staffID`),
  CONSTRAINT `fk_login_session_staff` FOREIGN KEY (`staffID`) REFERENCES `staff_edu` (`staffID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_login_session_1` CHECK (`sessionStatus` in ('active','ended'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- migrations | OWNER: Shared Laravel (Tya + Izz)
DROP TABLE IF EXISTS `migrations`;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- model_has_permissions | OWNER: Izz
DROP TABLE IF EXISTS `model_has_permissions`;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- model_has_roles | OWNER: Izz
DROP TABLE IF EXISTS `model_has_roles`;
CREATE TABLE `model_has_roles` (
  `role_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- notification_ladap | OWNER: Izz
DROP TABLE IF EXISTS `notification_ladap`;
CREATE TABLE `notification_ladap` (
  `Notification_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `teacherID` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gn_id` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `principalID` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL,
  PRIMARY KEY (`Notification_id`),
  KEY `notifications_user_id_is_read_index` (`user_id`,`is_read`),
  KEY `notification_ladap_teacherid_foreign` (`teacherID`),
  KEY `notification_ladap_principalid_foreign` (`principalID`),
  KEY `notification_ladap_gn_id_foreign` (`gn_id`),
  CONSTRAINT `notification_ladap_gn_id_foreign` FOREIGN KEY (`gn_id`) REFERENCES `guru_new` (`gn_id`) ON DELETE SET NULL,
  CONSTRAINT `notification_ladap_principalid_foreign` FOREIGN KEY (`principalID`) REFERENCES `principal` (`principalID`) ON DELETE SET NULL,
  CONSTRAINT `notification_ladap_teacherid_foreign` FOREIGN KEY (`teacherID`) REFERENCES `teacher` (`teacherID`) ON DELETE SET NULL,
  CONSTRAINT `notifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- observer | OWNER: Nureen (canonical shared)
DROP TABLE IF EXISTS `observer`;
CREATE TABLE `observer` (
  `observerID` varchar(20) NOT NULL,
  `startDate` date NOT NULL,
  `endDate` date DEFAULT NULL,
  `status` varchar(20) DEFAULT 'active',
  `teacherID` varchar(20) NOT NULL,
  PRIMARY KEY (`observerID`),
  KEY `idx_observer_teacher` (`teacherID`),
  CONSTRAINT `fk_observer_teacher` FOREIGN KEY (`teacherID`) REFERENCES `teacher` (`teacherID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_observer_1` CHECK (`status` in ('active','inactive'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- observer_assignment | OWNER: Nureen (canonical shared)
DROP TABLE IF EXISTS `observer_assignment`;
CREATE TABLE `observer_assignment` (
  `assignmentID` varchar(20) NOT NULL,
  `assignedDate` date NOT NULL,
  `endDate` date DEFAULT NULL,
  `status` varchar(20) DEFAULT 'active',
  `observerID` varchar(20) DEFAULT NULL,
  `gn_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `externalObserverID` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`assignmentID`),
  KEY `idx_observer_assignment_observer` (`observerID`),
  KEY `idx_observer_assignment_guru_new` (`gn_id`),
  KEY `idx_observer_assignment_external` (`externalObserverID`),
  CONSTRAINT `fk_observer_assignment_external` FOREIGN KEY (`externalObserverID`) REFERENCES `external_observer` (`externalObserverID`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_observer_assignment_guru_new` FOREIGN KEY (`gn_id`) REFERENCES `guru_new` (`gn_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_observer_assignment_observer` FOREIGN KEY (`observerID`) REFERENCES `observer` (`observerID`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_observer_assignment_1` CHECK (`status` in ('active','inactive'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- organizations | OWNER: Tya
DROP TABLE IF EXISTS `organizations`;
CREATE TABLE `organizations` (
  `OrganizationID` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `OrganizationName` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `OrganizationAddress` text COLLATE utf8mb4_unicode_ci,
  `RegisterDate` date DEFAULT NULL,
  `PhoneNumber` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`OrganizationID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- outsider | OWNER: Izz
DROP TABLE IF EXISTS `outsider`;
CREATE TABLE `outsider` (
  `outsider_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ICNumber` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phoneNumber` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gender` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `organization` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`outsider_id`),
  UNIQUE KEY `outsider_icnumber_unique` (`ICNumber`),
  KEY `outsider_user_id_foreign` (`user_id`),
  CONSTRAINT `outsider_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- password_reset_tokens | OWNER: Shared Laravel (Tya + Izz)
DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- pdpc_aspect | OWNER: Syiqin
DROP TABLE IF EXISTS `pdpc_aspect`;
CREATE TABLE `pdpc_aspect` (
  `aspectID` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `formID` bigint UNSIGNED NOT NULL,
  `aspect_code` varchar(30) DEFAULT NULL,
  `aspect_name` varchar(255) NOT NULL,
  `display_order` int UNSIGNED NOT NULL DEFAULT '1',
  PRIMARY KEY (`aspectID`),
  KEY `fk_pdpc_aspect_form` (`formID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- pdpc_form | OWNER: Syiqin
DROP TABLE IF EXISTS `pdpc_form`;
CREATE TABLE `pdpc_form` (
  `formID` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `form_name` varchar(255) NOT NULL,
  `instruction` text,
  `version_no` int UNSIGNED NOT NULL DEFAULT '1',
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `staffID` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`formID`),
  KEY `fk_pdpc_form_staff` (`staffID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- pdpc_response | OWNER: Syiqin
DROP TABLE IF EXISTS `pdpc_response`;
CREATE TABLE `pdpc_response` (
  `responseID` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `formID` bigint UNSIGNED NOT NULL,
  `gn_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `observerID` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `externalObserverID` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observation_stage` enum('EXTERNAL','POST') NOT NULL,
  `attempt_no` int UNSIGNED NOT NULL DEFAULT '1',
  `class_name` varchar(100) DEFAULT NULL,
  `subject_name` varchar(100) DEFAULT NULL,
  `observation_date` date DEFAULT NULL,
  `observation_time` time DEFAULT NULL,
  `total_score` decimal(10,2) DEFAULT NULL,
  `percentage` decimal(5,2) DEFAULT NULL,
  `achievement_level` varchar(50) DEFAULT NULL,
  `result` enum('PASS','REPEAT') DEFAULT NULL,
  `status` enum('Draft','Submitted') NOT NULL DEFAULT 'Draft',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`responseID`),
  UNIQUE KEY `uq_pdpc_response_attempt` (`gn_id`,`observation_stage`,`attempt_no`),
  KEY `fk_pdpc_response_form` (`formID`),
  KEY `fk_pdpc_response_observer` (`observerID`),
  KEY `fk_pdpc_response_external_observer` (`externalObserverID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- pdpc_rubric | OWNER: Syiqin
DROP TABLE IF EXISTS `pdpc_rubric`;
CREATE TABLE `pdpc_rubric` (
  `rubricID` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `tumsID` bigint UNSIGNED NOT NULL,
  `score` tinyint UNSIGNED NOT NULL,
  `description` text NOT NULL,
  PRIMARY KEY (`rubricID`),
  UNIQUE KEY `uq_pdpc_rubric_score` (`tumsID`,`score`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- pdpc_score | OWNER: Syiqin
DROP TABLE IF EXISTS `pdpc_score`;
CREATE TABLE `pdpc_score` (
  `scoreID` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `responseID` bigint UNSIGNED NOT NULL,
  `pointID` bigint UNSIGNED NOT NULL,
  `score` tinyint UNSIGNED NOT NULL,
  PRIMARY KEY (`scoreID`),
  UNIQUE KEY `uq_pdpc_score` (`responseID`,`pointID`),
  KEY `fk_pdpc_score_point` (`pointID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- pdpc_tt | OWNER: Syiqin
DROP TABLE IF EXISTS `pdpc_tt`;
CREATE TABLE `pdpc_tt` (
  `ttID` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `tumsID` bigint UNSIGNED NOT NULL,
  `display_order` int UNSIGNED NOT NULL DEFAULT '1',
  PRIMARY KEY (`ttID`),
  KEY `fk_pdpc_tt_tums` (`tumsID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- pdpc_tt_point | OWNER: Syiqin
DROP TABLE IF EXISTS `pdpc_tt_point`;
CREATE TABLE `pdpc_tt_point` (
  `pointID` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `ttID` bigint UNSIGNED NOT NULL,
  `point_text` text NOT NULL,
  `display_order` int UNSIGNED NOT NULL DEFAULT '1',
  PRIMARY KEY (`pointID`),
  KEY `fk_pdpc_tt_point_tt` (`ttID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- pdpc_tums | OWNER: Syiqin
DROP TABLE IF EXISTS `pdpc_tums`;
CREATE TABLE `pdpc_tums` (
  `tumsID` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `aspectID` bigint UNSIGNED NOT NULL,
  `tums_code` varchar(30) DEFAULT NULL,
  `tums_name` varchar(500) NOT NULL,
  `wajaran` decimal(5,2) NOT NULL DEFAULT '0.00',
  `display_order` int UNSIGNED NOT NULL DEFAULT '1',
  PRIMARY KEY (`tumsID`),
  KEY `fk_pdpc_tums_aspect` (`aspectID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- permissions | OWNER: Izz
DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- post_answer | OWNER: Syiqin
DROP TABLE IF EXISTS `post_answer`;
CREATE TABLE `post_answer` (
  `answerID` int NOT NULL AUTO_INCREMENT,
  `answer_value` text,
  `responseID` int NOT NULL,
  `fieldID` int NOT NULL,
  PRIMARY KEY (`answerID`),
  UNIQUE KEY `uq_post_answer` (`responseID`,`fieldID`),
  KEY `fk_post_answer_field` (`fieldID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- post_field | OWNER: Syiqin
DROP TABLE IF EXISTS `post_field`;
CREATE TABLE `post_field` (
  `fieldID` int NOT NULL AUTO_INCREMENT,
  `field_label` varchar(500) NOT NULL,
  `field_type` varchar(30) NOT NULL,
  `display_order` int NOT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT '1',
  `sectionID` int NOT NULL,
  PRIMARY KEY (`fieldID`),
  KEY `fk_post_field_section` (`sectionID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- post_field_option | OWNER: Syiqin
DROP TABLE IF EXISTS `post_field_option`;
CREATE TABLE `post_field_option` (
  `optionID` int NOT NULL AUTO_INCREMENT,
  `fieldID` int NOT NULL,
  `option_label` varchar(255) NOT NULL,
  `display_order` int NOT NULL,
  PRIMARY KEY (`optionID`),
  KEY `fk_post_field_option_field` (`fieldID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- post_form | OWNER: Syiqin
DROP TABLE IF EXISTS `post_form`;
CREATE TABLE `post_form` (
  `formID` int NOT NULL AUTO_INCREMENT,
  `form_name` varchar(255) NOT NULL,
  `version` int NOT NULL DEFAULT '1',
  `instruction` text,
  `status` varchar(20) NOT NULL DEFAULT 'Active',
  `staffID` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`formID`),
  KEY `fk_post_form_staff` (`staffID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- post_response | OWNER: Syiqin
DROP TABLE IF EXISTS `post_response`;
CREATE TABLE `post_response` (
  `responseID` int NOT NULL AUTO_INCREMENT,
  `observation_stage` varchar(20) NOT NULL DEFAULT 'POST',
  `attempt_no` int NOT NULL DEFAULT '1',
  `class_name` varchar(100) DEFAULT NULL,
  `subject_name` varchar(100) DEFAULT NULL,
  `observation_date` date DEFAULT NULL,
  `observation_time` time DEFAULT NULL,
  `status` enum('Draft','Submitted') NOT NULL DEFAULT 'Draft',
  `observerID` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `externalObserverID` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gn_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `formID` int NOT NULL,
  PRIMARY KEY (`responseID`),
  UNIQUE KEY `uq_post_response_attempt` (`gn_id`,`observation_stage`,`attempt_no`),
  KEY `fk_post_response_observer` (`observerID`),
  KEY `fk_post_response_external` (`externalObserverID`),
  KEY `fk_post_response_form` (`formID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- post_section | OWNER: Syiqin
DROP TABLE IF EXISTS `post_section`;
CREATE TABLE `post_section` (
  `sectionID` int NOT NULL AUTO_INCREMENT,
  `section_name` varchar(255) NOT NULL,
  `display_order` int NOT NULL,
  `formID` int NOT NULL,
  PRIMARY KEY (`sectionID`),
  KEY `fk_post_section_form` (`formID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- pre_criteria | OWNER: Syiqin
DROP TABLE IF EXISTS `pre_criteria`;
CREATE TABLE `pre_criteria` (
  `criteriaID` int NOT NULL AUTO_INCREMENT,
  `sectionID` int NOT NULL,
  `criteria_label` varchar(500) NOT NULL,
  `display_order` int NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`criteriaID`),
  KEY `fk_pre_criteria_section` (`sectionID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- pre_form | OWNER: Syiqin
DROP TABLE IF EXISTS `pre_form`;
CREATE TABLE `pre_form` (
  `formID` int NOT NULL AUTO_INCREMENT,
  `form_name` varchar(255) NOT NULL,
  `version` int NOT NULL DEFAULT '1',
  `instruction` text,
  `min_score` int NOT NULL DEFAULT '1',
  `max_score` int NOT NULL DEFAULT '5',
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `staffID` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`formID`),
  KEY `fk_pre_form_staff` (`staffID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- pre_response | OWNER: Syiqin
-- Integration update: class_name/subject_name/observation_date are nullable.
DROP TABLE IF EXISTS `pre_response`;
CREATE TABLE `pre_response` (
  `responseID` int NOT NULL AUTO_INCREMENT,
  `formID` int NOT NULL,
  `gn_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `observerID` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `observation_stage` enum('PRE') NOT NULL DEFAULT 'PRE',
  `class_name` varchar(100) DEFAULT NULL,
  `subject_name` varchar(100) DEFAULT NULL,
  `observation_date` date DEFAULT NULL,
  `total_score` int DEFAULT NULL,
  `percentage` decimal(5,2) DEFAULT NULL,
  `achievement_level` enum('Weak','Satisfactory','Good','Very Good','Excellent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `other_comment` text,
  `status` enum('Draft','Submitted') NOT NULL DEFAULT 'Draft',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`responseID`),
  KEY `fk_pre_response_form` (`formID`),
  KEY `fk_pre_response_gn` (`gn_id`),
  KEY `fk_pre_response_observer` (`observerID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- pre_score | OWNER: Syiqin
DROP TABLE IF EXISTS `pre_score`;
CREATE TABLE `pre_score` (
  `scoreID` int NOT NULL AUTO_INCREMENT,
  `responseID` int NOT NULL,
  `criteriaID` int NOT NULL,
  `score` tinyint DEFAULT NULL,
  `comment` text,
  PRIMARY KEY (`scoreID`),
  UNIQUE KEY `uq_pre_score_response_criteria` (`responseID`,`criteriaID`),
  KEY `fk_pre_score_criteria` (`criteriaID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- pre_section | OWNER: Syiqin
DROP TABLE IF EXISTS `pre_section`;
CREATE TABLE `pre_section` (
  `sectionID` int NOT NULL AUTO_INCREMENT,
  `formID` int NOT NULL,
  `section_name` varchar(255) NOT NULL,
  `display_order` int NOT NULL,
  PRIMARY KEY (`sectionID`),
  KEY `fk_pre_section_form` (`formID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- pre_section_comment | OWNER: Syiqin
DROP TABLE IF EXISTS `pre_section_comment`;
CREATE TABLE `pre_section_comment` (
  `sectionCommentID` int NOT NULL AUTO_INCREMENT,
  `responseID` int NOT NULL,
  `sectionID` int NOT NULL,
  `comment` text,
  PRIMARY KEY (`sectionCommentID`),
  UNIQUE KEY `uq_pre_section_comment` (`responseID`,`sectionID`),
  KEY `fk_pre_section_comment_section` (`sectionID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- principal | OWNER: Tya (canonical shared; Syiqin school FK integrated)
DROP TABLE IF EXISTS `principal`;
CREATE TABLE `principal` (
  `principalID` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `principalName` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ICNumber` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phoneNumber` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `maritalStatus` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gender` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `race` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `appointedDate` date DEFAULT NULL,
  `serviceDate` int DEFAULT NULL,
  `pensionDate` date DEFAULT NULL,
  `latestAge` int DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_change_required` tinyint(1) DEFAULT '1',
  `role` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'principal',
  `schoolID` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Aktif',
  `resignation_request_date` date DEFAULT NULL,
  `resignation_request_reason` text COLLATE utf8mb4_unicode_ci,
  `resignation_request_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`principalID`),
  UNIQUE KEY `ICNumber` (`ICNumber`),
  KEY `idx_principal_school_status` (`schoolID`,`status`),
  KEY `idx_principal_status` (`status`),
  KEY `idx_principal_name` (`principalName`),
  KEY `idx_principal_resignation_status` (`resignation_request_status`),
  KEY `idx_principal_resignation_date` (`resignation_request_date`),
  KEY `idx_principal_pension` (`pensionDate`),
  CONSTRAINT `fk_principal_school` FOREIGN KEY (`schoolID`) REFERENCES `school` (`schoolID`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- qr_session | OWNER: Nureen
DROP TABLE IF EXISTS `qr_session`;
CREATE TABLE `qr_session` (
  `qrID` varchar(20) NOT NULL,
  `qrCode` text NOT NULL,
  `attendanceLink` varchar(255) DEFAULT NULL,
  `expiryTime` datetime DEFAULT NULL,
  `sessionID` varchar(20) NOT NULL,
  PRIMARY KEY (`qrID`),
  UNIQUE KEY `uq_qr_session_session` (`sessionID`),
  CONSTRAINT `fk_qr_session_session` FOREIGN KEY (`sessionID`) REFERENCES `course_session` (`sessionID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- role_has_permissions | OWNER: Izz
DROP TABLE IF EXISTS `role_has_permissions`;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- roles | OWNER: Izz
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- school | OWNER: Tya (canonical shared)
DROP TABLE IF EXISTS `school`;
CREATE TABLE `school` (
  `schoolID` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `schoolName` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
  `schoolAddress` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `registerDate` date DEFAULT NULL,
  `phoneNumber` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `totalTeacher` int NOT NULL,
  `vacancy` int NOT NULL DEFAULT '0',
  `capacity` int DEFAULT NULL,
  PRIMARY KEY (`schoolID`),
  KEY `idx_school_name` (`schoolName`),
  KEY `idx_school_totalTeacher` (`totalTeacher`),
  KEY `idx_school_vacancy` (`vacancy`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- session_trainer | OWNER: Nureen
DROP TABLE IF EXISTS `session_trainer`;
CREATE TABLE `session_trainer` (
  `trainerID` varchar(20) NOT NULL,
  `sessionID` varchar(20) NOT NULL,
  PRIMARY KEY (`trainerID`,`sessionID`),
  KEY `idx_session_trainer_session` (`sessionID`),
  CONSTRAINT `fk_session_trainer_session` FOREIGN KEY (`sessionID`) REFERENCES `course_session` (`sessionID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_session_trainer_trainer` FOREIGN KEY (`trainerID`) REFERENCES `trainer` (`trainerID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- sessions | OWNER: Shared Laravel (Tya + Izz)
DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- staff | OWNER: Tya
DROP TABLE IF EXISTS `staff`;
CREATE TABLE `staff` (
  `staffID` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `staffName` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ICNumber` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phoneNumber` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `maritalStatus` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gender` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `race` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `appointedDate` date DEFAULT NULL,
  `serviceDate` int DEFAULT NULL,
  `pensionDate` date DEFAULT NULL,
  `latestAge` int DEFAULT NULL,
  `credit_hour` tinyint DEFAULT NULL,
  `department` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_change_required` tinyint(1) DEFAULT '1',
  `role` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'staff',
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `resignation_request_date` date DEFAULT NULL,
  `resignation_request_reason` text COLLATE utf8mb4_unicode_ci,
  `resignation_request_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`staffID`),
  UNIQUE KEY `ICNumber` (`ICNumber`),
  KEY `idx_staff_status` (`status`),
  KEY `idx_staff_name` (`staffName`),
  KEY `idx_staff_status_resignation` (`status`,`resignation_request_status`),
  KEY `idx_staff_resignation_date` (`resignation_request_date`),
  KEY `idx_staff_pension` (`pensionDate`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- staff_edu | OWNER: Nureen (canonical shared)
DROP TABLE IF EXISTS `staff_edu`;
CREATE TABLE `staff_edu` (
  `staffID` varchar(20) NOT NULL,
  `staffName` varchar(250) NOT NULL,
  `ICNumber` varchar(20) DEFAULT NULL,
  `phoneNumber` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `maritalStatus` varchar(20) DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `race` varchar(50) DEFAULT NULL,
  `appointedDate` date DEFAULT NULL,
  `serviceDate` varchar(20) DEFAULT NULL,
  `pensionDate` date DEFAULT NULL,
  `latestAge` int(11) DEFAULT NULL,
  `password` varchar(255) NOT NULL DEFAULT 'password',
  `password_changed_required` tinyint(1) DEFAULT 1,
  `role` varchar(20) DEFAULT 'STAFF_EDU',
  `credit_hour` decimal(5,2) DEFAULT 0.00,
  `department` varchar(50) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'active',
  PRIMARY KEY (`staffID`),
  UNIQUE KEY `uq_staff_edu_ic` (`ICNumber`),
  UNIQUE KEY `uq_staff_edu_email` (`email`),
  CONSTRAINT `chk_staff_edu_1` CHECK (`credit_hour` between 0 and 40)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- staff_tarbiah_attendance | OWNER: Nureen
DROP TABLE IF EXISTS `staff_tarbiah_attendance`;
CREATE TABLE `staff_tarbiah_attendance` (
  `staff_tarbiah_attendance_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `staffID` varchar(20) NOT NULL,
  `tarbiah_id` bigint(20) unsigned NOT NULL,
  `attendance_status` varchar(20) NOT NULL DEFAULT 'approved',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`staff_tarbiah_attendance_id`),
  UNIQUE KEY `uq_staff_tarbiah_attendance` (`staffID`,`tarbiah_id`),
  KEY `idx_staff_tarbiah_tarbiah` (`tarbiah_id`),
  CONSTRAINT `fk_staff_tarbiah_staff` FOREIGN KEY (`staffID`) REFERENCES `staff_edu` (`staffID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_staff_tarbiah_tarbiah` FOREIGN KEY (`tarbiah_id`) REFERENCES `tarbiah` (`tarbiah_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_staff_tarbiah_status` CHECK (`attendance_status` in ('pending','approved','rejected'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- tarbiah | OWNER: Nureen
DROP TABLE IF EXISTS `tarbiah`;
CREATE TABLE `tarbiah` (
  `tarbiah_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `schoolID` varchar(50) NOT NULL,
  `session_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`tarbiah_id`),
  KEY `idx_tarbiah_school` (`schoolID`),
  KEY `idx_tarbiah_created_by` (`created_by`),
  KEY `idx_tarbiah_session_date` (`session_date`),
  CONSTRAINT `fk_tarbiah_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_tarbiah_school` FOREIGN KEY (`schoolID`) REFERENCES `school` (`schoolID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_tarbiah_time` CHECK (`end_time` > `start_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- tarbiah_attendance | OWNER: Nureen
DROP TABLE IF EXISTS `tarbiah_attendance`;
CREATE TABLE `tarbiah_attendance` (
  `tarbiah_attendance_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `teacher_id` varchar(20) NOT NULL,
  `tarbiah_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`tarbiah_attendance_id`),
  UNIQUE KEY `tarbiah_attendance_teacher_id_tarbiah_id_unique` (`teacher_id`,`tarbiah_id`),
  KEY `tarbiah_attendance_tarbiah_id_foreign` (`tarbiah_id`),
  CONSTRAINT `tarbiah_attendance_tarbiah_id_foreign` FOREIGN KEY (`tarbiah_id`) REFERENCES `tarbiah` (`tarbiah_id`) ON DELETE CASCADE,
  CONSTRAINT `tarbiah_attendance_teacher_id_foreign` FOREIGN KEY (`teacher_id`) REFERENCES `teacher` (`teacherID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- teacher | OWNER: Tya + Nureen + Syiqin (canonical shared)
DROP TABLE IF EXISTS `teacher`;
CREATE TABLE `teacher` (
  `teacherID` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `teacherName` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ICNumber` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phoneNumber` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `maritalStatus` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gender` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `race` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `appointedDate` date DEFAULT NULL,
  `serviceDate` date DEFAULT NULL,
  `pensionDate` date DEFAULT NULL,
  `latestAge` int DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_change_required` tinyint(1) DEFAULT '1',
  `role` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'teacher',
  `resignation_request_date` date DEFAULT NULL,
  `resignation_request_reason` text COLLATE utf8mb4_unicode_ci,
  `resignation_request_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`teacherID`),
  UNIQUE KEY `ICNumber` (`ICNumber`),
  KEY `idx_teacher_pension` (`pensionDate`),
  KEY `idx_teacher_resignation_status` (`resignation_request_status`),
  KEY `idx_teacher_resignation_date` (`resignation_request_date`),
  KEY `idx_teacher_name` (`teacherName`),
  KEY `idx_teacher_id_status` (`teacherID`,`resignation_request_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- teacher_audit | OWNER: Tya
DROP TABLE IF EXISTS `teacher_audit`;
CREATE TABLE `teacher_audit` (
  `auditID` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `teacherID` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `oldData` text COLLATE utf8mb4_unicode_ci,
  `newData` text COLLATE utf8mb4_unicode_ci,
  `actionDate` datetime DEFAULT NULL,
  `action` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`auditID`),
  KEY `teacherID` (`teacherID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- teaching_plan_comments | OWNER: Aidid
DROP TABLE IF EXISTS `teaching_plan_comments`;
CREATE TABLE `teaching_plan_comments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `teaching_plan_id` bigint unsigned NOT NULL,
  `reviewer_staff_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `seen_by_trainer_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `teaching_plan_comments_teaching_plan_id_index` (`teaching_plan_id`),
  KEY `teaching_plan_comments_reviewer_staff_id_index` (`reviewer_staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- teaching_plans | OWNER: Aidid
DROP TABLE IF EXISTS `teaching_plans`;
CREATE TABLE `teaching_plans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `course_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `trainer_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` bigint unsigned NOT NULL,
  `submitted_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `teaching_plans_course_id_trainer_id_unique` (`course_id`,`trainer_id`),
  KEY `teaching_plans_course_id_index` (`course_id`),
  KEY `teaching_plans_trainer_id_index` (`trainer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- trainer | OWNER: Nureen (canonical shared; timestamps added)
DROP TABLE IF EXISTS `trainer`;
CREATE TABLE `trainer` (
  `trainerID` varchar(20) NOT NULL,
  `trainerName` varchar(255) NOT NULL,
  `trainerIC` varchar(20) DEFAULT NULL,
  `trainerEmail` varchar(255) DEFAULT NULL,
  `trainerPhoneNo` varchar(20) DEFAULT NULL,
  `expertise` text DEFAULT NULL,
  `trainerPic` varchar(255) DEFAULT NULL,
  `trainerPassword` varchar(255) NOT NULL DEFAULT 'password',
  `trainer_changed_password` tinyint(1) DEFAULT 1,
  `averageRating` decimal(3,2) DEFAULT 0.00,
  `status` varchar(20) DEFAULT 'active',
  `paymentStatus` varchar(20) DEFAULT 'unpaid',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`trainerID`),
  UNIQUE KEY `uq_trainer_email` (`trainerEmail`),
  CONSTRAINT `chk_trainer_1` CHECK (`averageRating` between 0 and 5),
  CONSTRAINT `chk_trainer_2` CHECK (`status` in ('active','inactive')),
  CONSTRAINT `chk_trainer_3` CHECK (`paymentStatus` in ('unpaid','pending','paid')),
  CONSTRAINT `chk_trainer_4` CHECK (`trainerPic` is null or `trainerPic` like '%.jpg' or `trainerPic` like '%.jpeg' or `trainerPic` like '%.png')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- trainer_activity_log | OWNER: Aidid
DROP TABLE IF EXISTS `trainer_activity_log`;
CREATE TABLE `trainer_activity_log` (
  `log_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `trainer_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `detail` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `course_id` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target_type` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target_id` bigint DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `idx_trainer` (`trainer_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- trainer_certificate | OWNER: Aidid
DROP TABLE IF EXISTS `trainer_certificate`;
CREATE TABLE `trainer_certificate` (
  `cert_id` int NOT NULL AUTO_INCREMENT,
  `trainer_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `file_path` varchar(500) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`cert_id`),
  KEY `idx_tc_trainer` (`trainer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- trainer_document | OWNER: Nureen
DROP TABLE IF EXISTS `trainer_document`;
CREATE TABLE `trainer_document` (
  `document_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `trainerID` varchar(20) NOT NULL,
  `title` varchar(255) NOT NULL,
  `document_type` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `sentByStaffID` varchar(20) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`document_id`),
  KEY `idx_trainerdoc_trainer` (`trainerID`),
  KEY `idx_trainerdoc_sender` (`sentByStaffID`),
  CONSTRAINT `fk_trainer_document_sender` FOREIGN KEY (`sentByStaffID`) REFERENCES `staff_edu` (`staffID`) ON UPDATE CASCADE,
  CONSTRAINT `fk_trainer_document_trainer` FOREIGN KEY (`trainerID`) REFERENCES `trainer` (`trainerID`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- training_access_log | OWNER: Izz
DROP TABLE IF EXISTS `training_access_log`;
CREATE TABLE `training_access_log` (
  `access_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `courseID` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `enrollment_id` bigint unsigned NOT NULL,
  `accessed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`access_id`),
  KEY `training_access_log_course_id_enrollment_id_index` (`courseID`,`enrollment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- training_content_block | OWNER: Aidid
DROP TABLE IF EXISTS `training_content_block`;
CREATE TABLE `training_content_block` (
  `block_id` bigint NOT NULL AUTO_INCREMENT,
  `module_id` int NOT NULL,
  `block_type` varchar(50) NOT NULL,
  `block_title` varchar(255) DEFAULT NULL,
  `block_content` text,
  `external_url` varchar(500) DEFAULT NULL,
  `material_id` bigint unsigned DEFAULT NULL,
  `assessment_id` bigint unsigned DEFAULT NULL,
  `block_order` int DEFAULT '1',
  `is_visible` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`block_id`),
  KEY `fk_content_block_module` (`module_id`),
  KEY `fk_content_block_material` (`material_id`),
  KEY `fk_content_block_assessment` (`assessment_id`),
  CONSTRAINT `fk_content_block_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `assessment` (`assessment_id`) ON UPDATE NO ACTION ON DELETE NO ACTION,
  CONSTRAINT `fk_content_block_material` FOREIGN KEY (`material_id`) REFERENCES `learning_material` (`material_id`) ON UPDATE NO ACTION ON DELETE NO ACTION,
  CONSTRAINT `fk_content_block_module` FOREIGN KEY (`module_id`) REFERENCES `training_module` (`module_id`) ON UPDATE NO ACTION ON DELETE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- training_content_interaction_log | OWNER: Izz
DROP TABLE IF EXISTS `training_content_interaction_log`;
CREATE TABLE `training_content_interaction_log` (
  `interaction_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `courseID` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `module_id` int NOT NULL,
  `block_id` bigint NOT NULL,
  `enrollment_id` bigint unsigned NOT NULL,
  `first_opened_at` datetime DEFAULT NULL,
  `last_accessed_at` datetime DEFAULT NULL,
  `open_count` int unsigned NOT NULL DEFAULT '0',
  `total_seconds` int unsigned NOT NULL DEFAULT '0',
  `last_event_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`interaction_id`),
  UNIQUE KEY `training_content_interaction_log_enrollment_id_block_id_unique` (`enrollment_id`,`block_id`),
  KEY `training_content_interaction_log_block_id_index` (`block_id`),
  KEY `training_content_interaction_log_enrollment_id_index` (`enrollment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- training_module | OWNER: Aidid
DROP TABLE IF EXISTS `training_module`;
CREATE TABLE `training_module` (
  `module_id` int NOT NULL AUTO_INCREMENT,
  `course_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `module_title` varchar(255) NOT NULL,
  `module_type` varchar(100) NOT NULL,
  `module_order` int DEFAULT '1',
  `cover_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`module_id`),
  UNIQUE KEY `uq_training_module_course_type` (`course_id`,`module_type`),
  CONSTRAINT `fk_training_module_course` FOREIGN KEY (`course_id`) REFERENCES `course` (`courseID`) ON UPDATE NO ACTION ON DELETE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- users | OWNER: Izz
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `external_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SYIQIN-OWNED FOREIGN KEYS DEFINED OUTSIDE CREATE TABLE
-- ============================================================
-- Syiqin-owned: audit_observation
ALTER TABLE `audit_observation` ADD CONSTRAINT `fk_audit_guru_new` FOREIGN KEY (`gn_id`) REFERENCES `guru_new` (`gn_id`) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE `audit_observation` ADD CONSTRAINT `fk_audit_teacher` FOREIGN KEY (`teacherID`) REFERENCES `teacher` (`teacherID`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- Syiqin-owned: evaluation_doc
ALTER TABLE `evaluation_doc` ADD CONSTRAINT `fk_evaluation_doc_staff` FOREIGN KEY (`staffID`) REFERENCES `staff_edu` (`staffID`);

-- Syiqin-owned: guru_new
ALTER TABLE `guru_new` ADD CONSTRAINT `guru_new_ibfk_1` FOREIGN KEY (`schoolID`) REFERENCES `school` (`schoolID`);
ALTER TABLE `guru_new` ADD CONSTRAINT `guru_new_ibfk_2` FOREIGN KEY (`hrid`) REFERENCES `hr_administrator` (`hrid`);

-- Syiqin-owned: pdpc_aspect
ALTER TABLE `pdpc_aspect` ADD CONSTRAINT `fk_pdpc_aspect_form` FOREIGN KEY (`formID`) REFERENCES `pdpc_form` (`formID`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Syiqin-owned: pdpc_form
ALTER TABLE `pdpc_form` ADD CONSTRAINT `fk_pdpc_form_staff` FOREIGN KEY (`staffID`) REFERENCES `staff_edu` (`staffID`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- Syiqin-owned: pdpc_response
ALTER TABLE `pdpc_response` ADD CONSTRAINT `fk_pdpc_response_external_observer` FOREIGN KEY (`externalObserverID`) REFERENCES `external_observer` (`externalObserverID`) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE `pdpc_response` ADD CONSTRAINT `fk_pdpc_response_form` FOREIGN KEY (`formID`) REFERENCES `pdpc_form` (`formID`) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE `pdpc_response` ADD CONSTRAINT `fk_pdpc_response_gn` FOREIGN KEY (`gn_id`) REFERENCES `guru_new` (`gn_id`) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE `pdpc_response` ADD CONSTRAINT `fk_pdpc_response_observer` FOREIGN KEY (`observerID`) REFERENCES `observer` (`observerID`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- Syiqin-owned: pdpc_rubric
ALTER TABLE `pdpc_rubric` ADD CONSTRAINT `fk_pdpc_rubric_tums` FOREIGN KEY (`tumsID`) REFERENCES `pdpc_tums` (`tumsID`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Syiqin-owned: pdpc_score
ALTER TABLE `pdpc_score` ADD CONSTRAINT `fk_pdpc_score_point` FOREIGN KEY (`pointID`) REFERENCES `pdpc_tt_point` (`pointID`) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE `pdpc_score` ADD CONSTRAINT `fk_pdpc_score_response` FOREIGN KEY (`responseID`) REFERENCES `pdpc_response` (`responseID`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Syiqin-owned: pdpc_tt
ALTER TABLE `pdpc_tt` ADD CONSTRAINT `fk_pdpc_tt_tums` FOREIGN KEY (`tumsID`) REFERENCES `pdpc_tums` (`tumsID`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Syiqin-owned: pdpc_tt_point
ALTER TABLE `pdpc_tt_point` ADD CONSTRAINT `fk_pdpc_tt_point_tt` FOREIGN KEY (`ttID`) REFERENCES `pdpc_tt` (`ttID`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Syiqin-owned: pdpc_tums
ALTER TABLE `pdpc_tums` ADD CONSTRAINT `fk_pdpc_tums_aspect` FOREIGN KEY (`aspectID`) REFERENCES `pdpc_aspect` (`aspectID`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Syiqin-owned: post_answer
ALTER TABLE `post_answer` ADD CONSTRAINT `fk_post_answer_field` FOREIGN KEY (`fieldID`) REFERENCES `post_field` (`fieldID`) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE `post_answer` ADD CONSTRAINT `fk_post_answer_response` FOREIGN KEY (`responseID`) REFERENCES `post_response` (`responseID`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Syiqin-owned: post_field
ALTER TABLE `post_field` ADD CONSTRAINT `fk_post_field_section` FOREIGN KEY (`sectionID`) REFERENCES `post_section` (`sectionID`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Syiqin-owned: post_field_option
ALTER TABLE `post_field_option` ADD CONSTRAINT `fk_post_field_option_field` FOREIGN KEY (`fieldID`) REFERENCES `post_field` (`fieldID`) ON DELETE CASCADE;

-- Syiqin-owned: post_form
ALTER TABLE `post_form` ADD CONSTRAINT `fk_post_form_staff` FOREIGN KEY (`staffID`) REFERENCES `staff_edu` (`staffID`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- Syiqin-owned: post_response
ALTER TABLE `post_response` ADD CONSTRAINT `fk_post_response_external` FOREIGN KEY (`externalObserverID`) REFERENCES `external_observer` (`externalObserverID`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `post_response` ADD CONSTRAINT `fk_post_response_form` FOREIGN KEY (`formID`) REFERENCES `post_form` (`formID`) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE `post_response` ADD CONSTRAINT `fk_post_response_gn` FOREIGN KEY (`gn_id`) REFERENCES `guru_new` (`gn_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `post_response` ADD CONSTRAINT `fk_post_response_observer` FOREIGN KEY (`observerID`) REFERENCES `observer` (`observerID`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Syiqin-owned: post_section
ALTER TABLE `post_section` ADD CONSTRAINT `fk_post_section_form` FOREIGN KEY (`formID`) REFERENCES `post_form` (`formID`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Syiqin-owned: pre_criteria
ALTER TABLE `pre_criteria` ADD CONSTRAINT `fk_pre_criteria_section` FOREIGN KEY (`sectionID`) REFERENCES `pre_section` (`sectionID`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- Syiqin-owned: pre_form
ALTER TABLE `pre_form` ADD CONSTRAINT `fk_pre_form_staff` FOREIGN KEY (`staffID`) REFERENCES `staff_edu` (`staffID`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- Syiqin-owned: pre_response
ALTER TABLE `pre_response` ADD CONSTRAINT `fk_pre_response_form` FOREIGN KEY (`formID`) REFERENCES `pre_form` (`formID`) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE `pre_response` ADD CONSTRAINT `fk_pre_response_gn` FOREIGN KEY (`gn_id`) REFERENCES `guru_new` (`gn_id`) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE `pre_response` ADD CONSTRAINT `fk_pre_response_observer` FOREIGN KEY (`observerID`) REFERENCES `observer` (`observerID`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- Syiqin-owned: pre_score
ALTER TABLE `pre_score` ADD CONSTRAINT `fk_pre_score_criteria` FOREIGN KEY (`criteriaID`) REFERENCES `pre_criteria` (`criteriaID`) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE `pre_score` ADD CONSTRAINT `fk_pre_score_response` FOREIGN KEY (`responseID`) REFERENCES `pre_response` (`responseID`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Syiqin-owned: pre_section
ALTER TABLE `pre_section` ADD CONSTRAINT `fk_pre_section_form` FOREIGN KEY (`formID`) REFERENCES `pre_form` (`formID`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Syiqin-owned: pre_section_comment
ALTER TABLE `pre_section_comment` ADD CONSTRAINT `fk_pre_section_comment_response` FOREIGN KEY (`responseID`) REFERENCES `pre_response` (`responseID`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `pre_section_comment` ADD CONSTRAINT `fk_pre_section_comment_section` FOREIGN KEY (`sectionID`) REFERENCES `pre_section` (`sectionID`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- ============================================================
-- STORED PROCEDURES
-- ============================================================
DELIMITER $$

DROP PROCEDURE IF EXISTS `add_principal`$$
CREATE PROCEDURE `add_principal` (
    IN `p_principalID` VARCHAR(20), IN `p_principalName` VARCHAR(100),
    IN `p_ICNumber` VARCHAR(20), IN `p_phoneNumber` VARCHAR(20),
    IN `p_email` VARCHAR(100), IN `p_maritalStatus` VARCHAR(20),
    IN `p_gender` VARCHAR(10), IN `p_address` TEXT, IN `p_race` VARCHAR(50),
    IN `p_appointedDate` DATE, IN `p_serviceDate` INT, IN `p_pensionDate` DATE,
    IN `p_latestAge` INT, IN `p_schoolID` VARCHAR(50), IN `p_password` VARCHAR(255)
)
BEGIN
    INSERT INTO principal (
        principalID, principalName, ICNumber, phoneNumber, email,
        maritalStatus, gender, address, race, appointedDate, serviceDate,
        pensionDate, latestAge, schoolID, password,
        password_change_required, role, status
    ) VALUES (
        p_principalID, p_principalName, p_ICNumber, p_phoneNumber, p_email,
        p_maritalStatus, p_gender, p_address, p_race, p_appointedDate,
        p_serviceDate, p_pensionDate, p_latestAge, p_schoolID, p_password,
        1, 'principal', 'Aktif'
    );
END$$

DROP PROCEDURE IF EXISTS `add_school`$$
CREATE PROCEDURE `add_school` (
    IN `p_schoolID` VARCHAR(50), IN `p_schoolName` VARCHAR(255),
    IN `p_schoolAddress` TEXT, IN `p_registerDate` DATE,
    IN `p_phoneNumber` VARCHAR(50), IN `p_totalTeacher` INT, IN `p_vacancy` INT
)
BEGIN
    -- Tya's update_vacancy_before_insert trigger calculates
    -- vacancy = capacity - totalTeacher. Derive capacity here so the
    -- original add_school inputs remain compatible with that trigger.
    INSERT INTO school (
        schoolID, schoolName, schoolAddress, registerDate, phoneNumber,
        totalTeacher, vacancy, capacity
    ) VALUES (
        p_schoolID, p_schoolName, p_schoolAddress, p_registerDate, p_phoneNumber,
        IFNULL(p_totalTeacher,0), IFNULL(p_vacancy,0),
        IFNULL(p_totalTeacher,0) + IFNULL(p_vacancy,0)
    );
END$$

DROP PROCEDURE IF EXISTS `add_staff`$$
CREATE PROCEDURE `add_staff` (
    IN `p_staffID` VARCHAR(50), IN `p_staffName` VARCHAR(255),
    IN `p_ICNumber` VARCHAR(20), IN `p_phoneNumber` VARCHAR(20), IN `p_email` VARCHAR(255),
    IN `p_maritalStatus` VARCHAR(50), IN `p_gender` VARCHAR(50), IN `p_address` TEXT,
    IN `p_race` VARCHAR(50), IN `p_appointedDate` DATE, IN `p_serviceDate` INT,
    IN `p_pensionDate` DATE, IN `p_latestAge` INT, IN `p_credit_hour` INT,
    IN `p_department` VARCHAR(100), IN `p_password` VARCHAR(255),
    IN `p_password_change_required` TINYINT, IN `p_role` VARCHAR(50)
)
BEGIN
    INSERT INTO staff (
        staffID, staffName, ICNumber, phoneNumber, email, maritalStatus,
        gender, address, race, appointedDate, serviceDate, pensionDate,
        latestAge, credit_hour, department, password, password_change_required, role
    ) VALUES (
        p_staffID, p_staffName, p_ICNumber, p_phoneNumber, p_email, p_maritalStatus,
        p_gender, p_address, p_race, p_appointedDate, p_serviceDate, p_pensionDate,
        p_latestAge, p_credit_hour, p_department, p_password, p_password_change_required, p_role
    );
END$$

-- Repaired: schoolID is stored in assign, not teacher.
DROP PROCEDURE IF EXISTS `add_teacher`$$
CREATE PROCEDURE `add_teacher` (
    IN `p_teacherID` VARCHAR(20), IN `p_teacherName` VARCHAR(250),
    IN `p_ICNumber` VARCHAR(20), IN `p_phoneNumber` VARCHAR(20), IN `p_email` VARCHAR(100),
    IN `p_maritalStatus` VARCHAR(20), IN `p_gender` VARCHAR(10), IN `p_address` TEXT,
    IN `p_race` VARCHAR(50), IN `p_appointedDate` DATE, IN `p_serviceDate` DATE,
    IN `p_pensionDate` DATE, IN `p_latestAge` INT, IN `p_schoolID` VARCHAR(50),
    IN `p_password` VARCHAR(255)
)
BEGIN
    INSERT INTO teacher (
        teacherID, teacherName, ICNumber, phoneNumber, email, maritalStatus,
        gender, address, race, appointedDate, serviceDate, pensionDate,
        latestAge, password, role, password_change_required
    ) VALUES (
        p_teacherID, p_teacherName, p_ICNumber, p_phoneNumber, p_email,
        p_maritalStatus, p_gender, p_address, p_race, p_appointedDate,
        p_serviceDate, p_pensionDate, p_latestAge, p_password, 'teacher', 1
    );

    IF p_schoolID IS NOT NULL AND p_schoolID <> '' THEN
        INSERT INTO assign (schoolID, teacherID, assignDate, status)
        VALUES (p_schoolID, p_teacherID, CURDATE(), 'Aktif');
    END IF;
END$$

DROP PROCEDURE IF EXISTS `sync_school_teacher_counts`$$
CREATE PROCEDURE `sync_school_teacher_counts` ()
BEGIN
    UPDATE school s
    LEFT JOIN (
        SELECT a.schoolID, COUNT(DISTINCT t.teacherID) AS active_count
        FROM teacher t
        INNER JOIN assign a ON t.teacherID = a.teacherID
        WHERE a.status = 'Aktif'
        GROUP BY a.schoolID
    ) counts ON s.schoolID = counts.schoolID
    SET s.totalTeacher = COALESCE(counts.active_count,0);

    SELECT CONCAT('Synchronization completed. Total schools updated: ',
                  (SELECT COUNT(*) FROM school)) AS message;
END$$

DROP PROCEDURE IF EXISTS `update_staff`$$
CREATE PROCEDURE `update_staff` (
    IN `p_staffID` VARCHAR(50), IN `p_staffName` VARCHAR(255),
    IN `p_ICNumber` VARCHAR(20), IN `p_phoneNumber` VARCHAR(20), IN `p_email` VARCHAR(255),
    IN `p_maritalStatus` VARCHAR(50), IN `p_gender` VARCHAR(50), IN `p_address` TEXT,
    IN `p_race` VARCHAR(50), IN `p_appointedDate` DATE, IN `p_serviceDate` INT,
    IN `p_pensionDate` DATE, IN `p_latestAge` INT, IN `p_credit_hour` INT,
    IN `p_department` VARCHAR(100)
)
BEGIN
    UPDATE staff SET
        staffName=p_staffName, ICNumber=p_ICNumber, phoneNumber=p_phoneNumber,
        email=p_email, maritalStatus=p_maritalStatus, gender=p_gender,
        address=p_address, race=p_race, appointedDate=p_appointedDate,
        serviceDate=p_serviceDate, pensionDate=p_pensionDate, latestAge=p_latestAge,
        credit_hour=p_credit_hour, department=p_department
    WHERE staffID=p_staffID;
END$$

DROP PROCEDURE IF EXISTS `update_teacher`$$
CREATE PROCEDURE `update_teacher` (
    IN `p_teacherID` VARCHAR(20), IN `p_teacherName` VARCHAR(250),
    IN `p_ICNumber` VARCHAR(20), IN `p_phoneNumber` VARCHAR(20), IN `p_email` VARCHAR(100),
    IN `p_maritalStatus` VARCHAR(20), IN `p_gender` VARCHAR(10), IN `p_address` TEXT,
    IN `p_race` VARCHAR(50), IN `p_appointedDate` DATE, IN `p_serviceDate` DATE,
    IN `p_pensionDate` DATE, IN `p_latestAge` INT, IN `p_schoolID` VARCHAR(50)
)
BEGIN
    UPDATE teacher SET
        teacherName=p_teacherName, ICNumber=p_ICNumber, phoneNumber=p_phoneNumber,
        email=p_email, maritalStatus=p_maritalStatus, gender=p_gender,
        address=p_address, race=p_race, appointedDate=p_appointedDate,
        serviceDate=p_serviceDate, pensionDate=p_pensionDate, latestAge=p_latestAge,
        updated_at=CURRENT_TIMESTAMP
    WHERE teacherID=p_teacherID;

    IF p_schoolID IS NOT NULL AND p_schoolID <> '' THEN
        IF EXISTS (SELECT 1 FROM assign WHERE teacherID=p_teacherID) THEN
            UPDATE assign SET schoolID=p_schoolID WHERE teacherID=p_teacherID;
        ELSE
            INSERT INTO assign (schoolID, teacherID, assignDate, status)
            VALUES (p_schoolID, p_teacherID, CURDATE(), 'Aktif');
        END IF;
    END IF;
END$$

DROP PROCEDURE IF EXISTS `sp_register_guru_new`$$
CREATE PROCEDURE `sp_register_guru_new` (
    IN `p_ic_number` VARCHAR(20),
    IN `p_gn_name` VARCHAR(255),
    IN `p_phone_number` VARCHAR(20),
    IN `p_email` VARCHAR(100),
    IN `p_gender` VARCHAR(10),
    IN `p_password` VARCHAR(255),
    IN `p_appointed_date` DATE,
    IN `p_hrid` VARCHAR(20),
    IN `p_schoolID` VARCHAR(50)
)
BEGIN
    INSERT INTO guru_new (
        gn_id,
        ic_number,
        gn_name,
        phone_number,
        email,
        gender,
        password,
        appointed_date,
        role,
        current_status,
        hrid,
        schoolID,
        created_at,
        updated_at
    ) VALUES (
        NULL,
        p_ic_number,
        p_gn_name,
        p_phone_number,
        p_email,
        p_gender,
        p_password,
        p_appointed_date,
        'new_teacher',
        'Inactive',
        p_hrid,
        p_schoolID,
        NOW(),
        NOW()
    );
END$$
DELIMITER ;

-- ============================================================
-- TRIGGERS
-- ============================================================

-- TRIGGER `after_assign_delete`
DELIMITER $$
CREATE TRIGGER `after_assign_delete` AFTER DELETE ON `assign` FOR EACH ROW BEGIN
    DECLARE active_count INT;
    
    -- If the deleted assignment was Aktif, update the school count
    IF OLD.status = 'Aktif' THEN
        -- Count active teachers for this school
        SELECT COUNT(DISTINCT t.teacherID) INTO active_count
        FROM teacher t
        INNER JOIN `assign` a ON t.teacherID = a.teacherID
        WHERE a.schoolID = OLD.schoolID AND a.status = 'Aktif';
        
        -- Update school totalTeacher
        UPDATE school 
        SET totalTeacher = active_count 
        WHERE schoolID = OLD.schoolID;
    END IF;
END
$$
DELIMITER ;

-- TRIGGER `after_assign_insert`
DELIMITER $$
CREATE TRIGGER `after_assign_insert` AFTER INSERT ON `assign` FOR EACH ROW BEGIN
    DECLARE active_count INT;
    
    -- Only count if status is Aktif
    IF NEW.status = 'Aktif' THEN
        -- Count active teachers for this school
        SELECT COUNT(DISTINCT t.teacherID) INTO active_count
        FROM teacher t
        INNER JOIN `assign` a ON t.teacherID = a.teacherID
        WHERE a.schoolID = NEW.schoolID AND a.status = 'Aktif';
        
        -- Update school totalTeacher
        UPDATE school 
        SET totalTeacher = active_count 
        WHERE schoolID = NEW.schoolID;
    END IF;
END
$$
DELIMITER ;

-- TRIGGER `after_assign_update`
DELIMITER $$
CREATE TRIGGER `after_assign_update` AFTER UPDATE ON `assign` FOR EACH ROW BEGIN
    DECLARE active_count INT;
    
    -- Check if status changed
    IF OLD.status != NEW.status THEN
        -- If status changed to Aktif, update new school
        IF NEW.status = 'Aktif' THEN
            SELECT COUNT(DISTINCT t.teacherID) INTO active_count
            FROM teacher t
            INNER JOIN `assign` a ON t.teacherID = a.teacherID
            WHERE a.schoolID = NEW.schoolID AND a.status = 'Aktif';
            
            UPDATE school 
            SET totalTeacher = active_count 
            WHERE schoolID = NEW.schoolID;
        END IF;
        
        -- If status changed from Aktif to something else, update old school
        IF OLD.status = 'Aktif' THEN
            SELECT COUNT(DISTINCT t.teacherID) INTO active_count
            FROM teacher t
            INNER JOIN `assign` a ON t.teacherID = a.teacherID
            WHERE a.schoolID = OLD.schoolID AND a.status = 'Aktif';
            
            UPDATE school 
            SET totalTeacher = active_count 
            WHERE schoolID = OLD.schoolID;
        END IF;
    END IF;
    
    -- Check if school changed
    IF OLD.schoolID != NEW.schoolID THEN
        -- Update old school (decrease count)
        SELECT COUNT(DISTINCT t.teacherID) INTO active_count
        FROM teacher t
        INNER JOIN `assign` a ON t.teacherID = a.teacherID
        WHERE a.schoolID = OLD.schoolID AND a.status = 'Aktif';
        
        UPDATE school 
        SET totalTeacher = active_count 
        WHERE schoolID = OLD.schoolID;
        
        -- Update new school (increase count)
        SELECT COUNT(DISTINCT t.teacherID) INTO active_count
        FROM teacher t
        INNER JOIN `assign` a ON t.teacherID = a.teacherID
        WHERE a.schoolID = NEW.schoolID AND a.status = 'Aktif';
        
        UPDATE school 
        SET totalTeacher = active_count 
        WHERE schoolID = NEW.schoolID;
    END IF;
END
$$
DELIMITER ;

-- TRIGGER `after_teacher_delete`
DELIMITER $$
CREATE TRIGGER `after_teacher_delete` BEFORE DELETE ON `teacher` FOR EACH ROW
BEGIN
    DECLARE school_id VARCHAR(50);
    DECLARE done INT DEFAULT FALSE;
    DECLARE cur CURSOR FOR SELECT DISTINCT schoolID FROM assign WHERE teacherID=OLD.teacherID;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done=TRUE;
    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO school_id;
        IF done THEN LEAVE read_loop; END IF;
        UPDATE school s
        SET totalTeacher=(
            SELECT COUNT(DISTINCT a.teacherID)
            FROM assign a
            WHERE a.schoolID=school_id AND a.status='Aktif' AND a.teacherID<>OLD.teacherID
        )
        WHERE s.schoolID=school_id;
    END LOOP;
    CLOSE cur;
END
$$
DELIMITER ;

-- TRIGGER `assign_delete_update_school`
DELIMITER $$
CREATE TRIGGER `assign_delete_update_school` AFTER DELETE ON `assign` FOR EACH ROW BEGIN
    UPDATE school s
    SET totalTeacher = (
        SELECT COUNT(DISTINCT a.teacherID)
        FROM assign a
        JOIN teacher t ON a.teacherID = t.teacherID
        WHERE a.schoolID = s.schoolID
        AND a.status = 'Aktif'
    )
    WHERE s.schoolID = OLD.schoolID;
END
$$
DELIMITER ;

-- TRIGGER `set_principal_service_date_insert`
DELIMITER $$
CREATE TRIGGER `set_principal_service_date_insert` BEFORE INSERT ON `principal` FOR EACH ROW BEGIN
    IF NEW.appointedDate IS NOT NULL THEN
        SET NEW.serviceDate = TIMESTAMPDIFF(YEAR, NEW.appointedDate, CURDATE());
    END IF;
END
$$
DELIMITER ;

-- TRIGGER `set_principal_service_date_update`
DELIMITER $$
CREATE TRIGGER `set_principal_service_date_update` BEFORE UPDATE ON `principal` FOR EACH ROW BEGIN
    IF NEW.appointedDate IS NOT NULL AND (OLD.appointedDate != NEW.appointedDate OR OLD.appointedDate IS NULL) THEN
        SET NEW.serviceDate = TIMESTAMPDIFF(YEAR, NEW.appointedDate, CURDATE());
    END IF;
END
$$
DELIMITER ;

-- TRIGGER `set_staff_service_date_insert`
DELIMITER $$
CREATE TRIGGER `set_staff_service_date_insert` BEFORE INSERT ON `staff` FOR EACH ROW BEGIN
    IF NEW.appointedDate IS NOT NULL THEN
        SET NEW.serviceDate = TIMESTAMPDIFF(YEAR, NEW.appointedDate, CURDATE());
    END IF;
END
$$
DELIMITER ;

-- TRIGGER `set_staff_service_date_update`
DELIMITER $$
CREATE TRIGGER `set_staff_service_date_update` BEFORE UPDATE ON `staff` FOR EACH ROW BEGIN
    IF NEW.appointedDate IS NOT NULL AND (OLD.appointedDate != NEW.appointedDate OR OLD.appointedDate IS NULL) THEN
        SET NEW.serviceDate = TIMESTAMPDIFF(YEAR, NEW.appointedDate, CURDATE());
    END IF;
END
$$
DELIMITER ;

-- TRIGGER `teacher_delete_update_school`
DELIMITER $$
CREATE TRIGGER `teacher_delete_update_school` BEFORE DELETE ON `teacher` FOR EACH ROW
BEGIN
    DECLARE school_id VARCHAR(50);
    DECLARE done INT DEFAULT FALSE;
    DECLARE cur CURSOR FOR SELECT DISTINCT schoolID FROM assign WHERE teacherID=OLD.teacherID;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done=TRUE;
    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO school_id;
        IF done THEN LEAVE read_loop; END IF;
        UPDATE school s
        SET totalTeacher=(
            SELECT COUNT(DISTINCT a.teacherID)
            FROM assign a
            WHERE a.schoolID=school_id AND a.status='Aktif' AND a.teacherID<>OLD.teacherID
        )
        WHERE s.schoolID=school_id;
    END LOOP;
    CLOSE cur;
END
$$
DELIMITER ;

-- TRIGGER `trg_teacher_audit_id`
DELIMITER $$
CREATE TRIGGER `trg_teacher_audit_id` BEFORE INSERT ON `teacher_audit` FOR EACH ROW BEGIN
    DECLARE next_num INT;

    SELECT COALESCE(
        MAX(CAST(SUBSTRING(auditID, 3) AS UNSIGNED)),
        0
    ) + 1
    INTO next_num
    FROM teacher_audit;

    SET NEW.auditID = CONCAT(
        'AU',
        LPAD(next_num, 3, '0')
    );
END
$$
DELIMITER ;

-- TRIGGER `update_school_teacher_count_insert`
DELIMITER $$
CREATE TRIGGER `update_school_teacher_count_insert` AFTER INSERT ON `assign` FOR EACH ROW BEGIN
    UPDATE school 
    SET totalTeacher = (
        SELECT COUNT(DISTINCT teacherID)
        FROM assign
        WHERE schoolID = NEW.schoolID
          AND status = 'Aktif'
    )
    WHERE schoolID = NEW.schoolID;
END
$$
DELIMITER ;

-- TRIGGER `update_vacancy_before_insert`
DELIMITER $$
CREATE TRIGGER `update_vacancy_before_insert` BEFORE INSERT ON `school` FOR EACH ROW BEGIN
    SET NEW.vacancy = NEW.capacity - NEW.totalTeacher;
END
$$
DELIMITER ;

-- TRIGGER `trg_assign_delete_audit`
DELIMITER ;;
CREATE TRIGGER `trg_assign_delete_audit`
AFTER DELETE ON `assign`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'DELETE',
        'assign',
        CONCAT('Deleted assign. Record ID = ', CONCAT(OLD.schoolID, '-', OLD.teacherID))
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_assign_insert_audit`
DELIMITER ;;
CREATE TRIGGER `trg_assign_insert_audit`
AFTER INSERT ON `assign`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'INSERT',
        'assign',
        CONCAT('Added assign. Record ID = ', CONCAT(NEW.schoolID, '-', NEW.teacherID))
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_assign_update_audit`
DELIMITER ;;
CREATE TRIGGER `trg_assign_update_audit`
AFTER UPDATE ON `assign`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'UPDATE',
        'assign',
        CONCAT('Updated assign. Record ID = ', CONCAT(NEW.schoolID, '-', NEW.teacherID))
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_attendance_delete_audit`
DELIMITER ;;
CREATE TRIGGER `trg_attendance_delete_audit`
AFTER DELETE ON `attendance`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'DELETE',
        'attendance',
        CONCAT('Deleted attendance. Record ID = ', OLD.`attendance_id`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_attendance_guru_baru_delete_audit`
DELIMITER ;;
CREATE TRIGGER `trg_attendance_guru_baru_delete_audit`
AFTER DELETE ON `attendance_guru_baru`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'DELETE',
        'attendance_guru_baru',
        CONCAT('Deleted attendance_guru_baru. Record ID = ', OLD.`attendGuruBaru_id`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_attendance_guru_baru_insert_audit`
DELIMITER ;;
CREATE TRIGGER `trg_attendance_guru_baru_insert_audit`
AFTER INSERT ON `attendance_guru_baru`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'INSERT',
        'attendance_guru_baru',
        CONCAT('Added attendance_guru_baru. Record ID = ', NEW.`attendGuruBaru_id`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_attendance_guru_baru_update_audit`
DELIMITER ;;
CREATE TRIGGER `trg_attendance_guru_baru_update_audit`
AFTER UPDATE ON `attendance_guru_baru`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'UPDATE',
        'attendance_guru_baru',
        CONCAT('Updated attendance_guru_baru. Record ID = ', NEW.`attendGuruBaru_id`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_attendance_insert_audit`
DELIMITER ;;
CREATE TRIGGER `trg_attendance_insert_audit`
AFTER INSERT ON `attendance`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'INSERT',
        'attendance',
        CONCAT('Added attendance. Record ID = ', NEW.`attendance_id`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_attendance_outsider_delete_audit`
DELIMITER ;;
CREATE TRIGGER `trg_attendance_outsider_delete_audit`
AFTER DELETE ON `attendance_outsider`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'DELETE',
        'attendance_outsider',
        CONCAT('Deleted attendance_outsider. Record ID = ', OLD.`attendOutsider_id`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_attendance_outsider_insert_audit`
DELIMITER ;;
CREATE TRIGGER `trg_attendance_outsider_insert_audit`
AFTER INSERT ON `attendance_outsider`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'INSERT',
        'attendance_outsider',
        CONCAT('Added attendance_outsider. Record ID = ', NEW.`attendOutsider_id`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_attendance_outsider_update_audit`
DELIMITER ;;
CREATE TRIGGER `trg_attendance_outsider_update_audit`
AFTER UPDATE ON `attendance_outsider`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'UPDATE',
        'attendance_outsider',
        CONCAT('Updated attendance_outsider. Record ID = ', NEW.`attendOutsider_id`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_attendance_staff_delete_audit`
DELIMITER ;;
CREATE TRIGGER `trg_attendance_staff_delete_audit`
AFTER DELETE ON `attendance_staff`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'DELETE',
        'attendance_staff',
        CONCAT('Deleted attendance_staff. Record ID = ', OLD.`attendanceStaff_id`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_attendance_staff_insert_audit`
DELIMITER ;;
CREATE TRIGGER `trg_attendance_staff_insert_audit`
AFTER INSERT ON `attendance_staff`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'INSERT',
        'attendance_staff',
        CONCAT('Added attendance_staff. Record ID = ', NEW.`attendanceStaff_id`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_attendance_staff_update_audit`
DELIMITER ;;
CREATE TRIGGER `trg_attendance_staff_update_audit`
AFTER UPDATE ON `attendance_staff`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'UPDATE',
        'attendance_staff',
        CONCAT('Updated attendance_staff. Record ID = ', NEW.`attendanceStaff_id`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_attendance_update_audit`
DELIMITER ;;
CREATE TRIGGER `trg_attendance_update_audit`
AFTER UPDATE ON `attendance`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'UPDATE',
        'attendance',
        CONCAT('Updated attendance. Record ID = ', NEW.`attendance_id`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_certificate_delete_audit`
DELIMITER ;;
CREATE TRIGGER `trg_certificate_delete_audit`
AFTER DELETE ON `certificate`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'DELETE',
        'certificate',
        CONCAT('Deleted certificate. Record ID = ', OLD.`certificateID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_certificate_insert_audit`
DELIMITER ;;
CREATE TRIGGER `trg_certificate_insert_audit`
AFTER INSERT ON `certificate`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'INSERT',
        'certificate',
        CONCAT('Added certificate. Record ID = ', NEW.`certificateID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_certificate_position_delete_audit`
DELIMITER ;;
CREATE TRIGGER `trg_certificate_position_delete_audit`
AFTER DELETE ON `certificate_position`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'DELETE',
        'certificate_position',
        CONCAT('Deleted certificate_position. Record ID = ', OLD.`positionID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_certificate_position_insert_audit`
DELIMITER ;;
CREATE TRIGGER `trg_certificate_position_insert_audit`
AFTER INSERT ON `certificate_position`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'INSERT',
        'certificate_position',
        CONCAT('Added certificate_position. Record ID = ', NEW.`positionID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_certificate_position_update_audit`
DELIMITER ;;
CREATE TRIGGER `trg_certificate_position_update_audit`
AFTER UPDATE ON `certificate_position`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'UPDATE',
        'certificate_position',
        CONCAT('Updated certificate_position. Record ID = ', NEW.`positionID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_certificate_template_delete_audit`
DELIMITER ;;
CREATE TRIGGER `trg_certificate_template_delete_audit`
AFTER DELETE ON `certificate_template`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'DELETE',
        'certificate_template',
        CONCAT('Deleted certificate_template. Record ID = ', OLD.`templateID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_certificate_template_insert_audit`
DELIMITER ;;
CREATE TRIGGER `trg_certificate_template_insert_audit`
AFTER INSERT ON `certificate_template`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'INSERT',
        'certificate_template',
        CONCAT('Added certificate_template. Record ID = ', NEW.`templateID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_certificate_template_update_audit`
DELIMITER ;;
CREATE TRIGGER `trg_certificate_template_update_audit`
AFTER UPDATE ON `certificate_template`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'UPDATE',
        'certificate_template',
        CONCAT('Updated certificate_template. Record ID = ', NEW.`templateID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_certificate_update_audit`
DELIMITER ;;
CREATE TRIGGER `trg_certificate_update_audit`
AFTER UPDATE ON `certificate`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'UPDATE',
        'certificate',
        CONCAT('Updated certificate. Record ID = ', NEW.`certificateID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_course_delete_audit`
DELIMITER ;;
CREATE TRIGGER `trg_course_delete_audit`
AFTER DELETE ON `course`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'DELETE',
        'course',
        CONCAT('Deleted course. Record ID = ', OLD.`courseID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_course_insert_audit`
DELIMITER ;;
CREATE TRIGGER `trg_course_insert_audit`
AFTER INSERT ON `course`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'INSERT',
        'course',
        CONCAT('Added course. Record ID = ', NEW.`courseID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_course_participant_delete_audit`
DELIMITER ;;
CREATE TRIGGER `trg_course_participant_delete_audit`
AFTER DELETE ON `course_participant`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'DELETE',
        'course_participant',
        CONCAT('Deleted course_participant. Record ID = ', OLD.`participantID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_course_participant_insert_audit`
DELIMITER ;;
CREATE TRIGGER `trg_course_participant_insert_audit`
AFTER INSERT ON `course_participant`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'INSERT',
        'course_participant',
        CONCAT('Added course_participant. Record ID = ', NEW.`participantID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_course_participant_update_audit`
DELIMITER ;;
CREATE TRIGGER `trg_course_participant_update_audit`
AFTER UPDATE ON `course_participant`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'UPDATE',
        'course_participant',
        CONCAT('Updated course_participant. Record ID = ', NEW.`participantID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_course_session_delete_audit`
DELIMITER ;;
CREATE TRIGGER `trg_course_session_delete_audit`
AFTER DELETE ON `course_session`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'DELETE',
        'course_session',
        CONCAT('Deleted course_session. Record ID = ', OLD.`sessionID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_course_session_insert_audit`
DELIMITER ;;
CREATE TRIGGER `trg_course_session_insert_audit`
AFTER INSERT ON `course_session`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'INSERT',
        'course_session',
        CONCAT('Added course_session. Record ID = ', NEW.`sessionID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_course_session_update_audit`
DELIMITER ;;
CREATE TRIGGER `trg_course_session_update_audit`
AFTER UPDATE ON `course_session`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'UPDATE',
        'course_session',
        CONCAT('Updated course_session. Record ID = ', NEW.`sessionID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_course_target_type_insert_validate`
DELIMITER ;;
CREATE TRIGGER `trg_course_target_type_insert_validate`
BEFORE INSERT ON `course`
FOR EACH ROW
BEGIN
    IF FIND_IN_SET('other', NEW.targetAudience) > 0
       AND (NEW.otherTargetAudience IS NULL OR TRIM(NEW.otherTargetAudience) = '') THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Please specify other target audience.';
    END IF;

    IF NEW.targetAudience = 'new_teacher' AND NEW.courseType <> 'LMS' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'New Teacher only courses must use LMS.';
    END IF;

    IF NEW.targetAudience <> 'new_teacher' AND NEW.courseType <> 'Simple' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Teacher, staff, public, mixed, and other target audiences must use Simple course type.';
    END IF;
END ;;
DELIMITER ;

-- TRIGGER `trg_course_target_type_update_validate`
DELIMITER ;;
CREATE TRIGGER `trg_course_target_type_update_validate`
BEFORE UPDATE ON `course`
FOR EACH ROW
BEGIN
    IF FIND_IN_SET('other', NEW.targetAudience) > 0
       AND (NEW.otherTargetAudience IS NULL OR TRIM(NEW.otherTargetAudience) = '') THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Please specify other target audience.';
    END IF;

    IF NEW.targetAudience = 'new_teacher' AND NEW.courseType <> 'LMS' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'New Teacher only courses must use LMS.';
    END IF;

    IF NEW.targetAudience <> 'new_teacher' AND NEW.courseType <> 'Simple' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Teacher, staff, public, mixed, and other target audiences must use Simple course type.';
    END IF;
END ;;
DELIMITER ;

-- TRIGGER `trg_course_update_audit`
DELIMITER ;;
CREATE TRIGGER `trg_course_update_audit`
AFTER UPDATE ON `course`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'UPDATE',
        'course',
        CONCAT('Updated course. Record ID = ', NEW.`courseID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_enroll_guru_baru_delete_audit`
DELIMITER ;;
CREATE TRIGGER `trg_enroll_guru_baru_delete_audit`
AFTER DELETE ON `enroll_guru_baru`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'DELETE',
        'enroll_guru_baru',
        CONCAT('Deleted enroll_guru_baru. Record ID = ', OLD.`enrollment_id`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_enroll_guru_baru_insert_audit`
DELIMITER ;;
CREATE TRIGGER `trg_enroll_guru_baru_insert_audit`
AFTER INSERT ON `enroll_guru_baru`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'INSERT',
        'enroll_guru_baru',
        CONCAT('Added enroll_guru_baru. Record ID = ', NEW.`enrollment_id`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_enroll_guru_baru_update_audit`
DELIMITER ;;
CREATE TRIGGER `trg_enroll_guru_baru_update_audit`
AFTER UPDATE ON `enroll_guru_baru`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'UPDATE',
        'enroll_guru_baru',
        CONCAT('Updated enroll_guru_baru. Record ID = ', NEW.`enrollment_id`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_enroll_outsider_delete_audit`
DELIMITER ;;
CREATE TRIGGER `trg_enroll_outsider_delete_audit`
AFTER DELETE ON `enroll_outsider`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'DELETE',
        'enroll_outsider',
        CONCAT('Deleted enroll_outsider. Record ID = ', CONCAT(OLD.outsider_id, '-', OLD.course_id))
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_enroll_outsider_insert_audit`
DELIMITER ;;
CREATE TRIGGER `trg_enroll_outsider_insert_audit`
AFTER INSERT ON `enroll_outsider`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'INSERT',
        'enroll_outsider',
        CONCAT('Added enroll_outsider. Record ID = ', CONCAT(NEW.outsider_id, '-', NEW.course_id))
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_enroll_outsider_update_audit`
DELIMITER ;;
CREATE TRIGGER `trg_enroll_outsider_update_audit`
AFTER UPDATE ON `enroll_outsider`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'UPDATE',
        'enroll_outsider',
        CONCAT('Updated enroll_outsider. Record ID = ', CONCAT(NEW.outsider_id, '-', NEW.course_id))
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_enroll_teacher_delete_audit`
DELIMITER ;;
CREATE TRIGGER `trg_enroll_teacher_delete_audit`
AFTER DELETE ON `enroll_teacher`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'DELETE',
        'enroll_teacher',
        CONCAT('Deleted enroll_teacher. Record ID = ', CONCAT(OLD.teacherID, '-', OLD.course_id))
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_enroll_teacher_insert_audit`
DELIMITER ;;
CREATE TRIGGER `trg_enroll_teacher_insert_audit`
AFTER INSERT ON `enroll_teacher`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'INSERT',
        'enroll_teacher',
        CONCAT('Added enroll_teacher. Record ID = ', CONCAT(NEW.teacherID, '-', NEW.course_id))
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_enroll_teacher_update_audit`
DELIMITER ;;
CREATE TRIGGER `trg_enroll_teacher_update_audit`
AFTER UPDATE ON `enroll_teacher`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'UPDATE',
        'enroll_teacher',
        CONCAT('Updated enroll_teacher. Record ID = ', CONCAT(NEW.teacherID, '-', NEW.course_id))
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_external_observer_delete_audit`
DELIMITER ;;
CREATE TRIGGER `trg_external_observer_delete_audit`
AFTER DELETE ON `external_observer`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'DELETE',
        'external_observer',
        CONCAT('Deleted external_observer. Record ID = ', OLD.`externalObserverID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_external_observer_insert_audit`
DELIMITER ;;
CREATE TRIGGER `trg_external_observer_insert_audit`
AFTER INSERT ON `external_observer`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'INSERT',
        'external_observer',
        CONCAT('Added external_observer. Record ID = ', NEW.`externalObserverID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_external_observer_update_audit`
DELIMITER ;;
CREATE TRIGGER `trg_external_observer_update_audit`
AFTER UPDATE ON `external_observer`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'UPDATE',
        'external_observer',
        CONCAT('Updated external_observer. Record ID = ', NEW.`externalObserverID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_feedback_category_delete_audit`
DELIMITER ;;
CREATE TRIGGER `trg_feedback_category_delete_audit`
AFTER DELETE ON `feedback_category`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'DELETE',
        'feedback_category',
        CONCAT('Deleted feedback_category. Record ID = ', OLD.`categoryID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_feedback_category_insert_audit`
DELIMITER ;;
CREATE TRIGGER `trg_feedback_category_insert_audit`
AFTER INSERT ON `feedback_category`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'INSERT',
        'feedback_category',
        CONCAT('Added feedback_category. Record ID = ', NEW.`categoryID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_feedback_category_update_audit`
DELIMITER ;;
CREATE TRIGGER `trg_feedback_category_update_audit`
AFTER UPDATE ON `feedback_category`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'UPDATE',
        'feedback_category',
        CONCAT('Updated feedback_category. Record ID = ', NEW.`categoryID`)
    );
END ;;
DELIMITER ;

-- COMPATIBILITY VALIDATION: replaces chk_feedback_form_2 because MySQL/MariaDB
-- does not allow this CHECK to reference columns participating in FK CASCADE actions.
DELIMITER ;;
CREATE TRIGGER `trg_feedback_form_validate_insert`
BEFORE INSERT ON `feedback_form`
FOR EACH ROW
BEGIN
    IF NEW.`sessionID` IS NULL AND NEW.`courseID` IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Feedback form must reference a session or a course.';
    END IF;
END ;;
DELIMITER ;

DELIMITER ;;
CREATE TRIGGER `trg_feedback_form_validate_update`
BEFORE UPDATE ON `feedback_form`
FOR EACH ROW
BEGIN
    IF NEW.`sessionID` IS NULL AND NEW.`courseID` IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Feedback form must reference a session or a course.';
    END IF;
END ;;
DELIMITER ;

-- TRIGGER `trg_feedback_form_delete_audit`
DELIMITER ;;
CREATE TRIGGER `trg_feedback_form_delete_audit`
AFTER DELETE ON `feedback_form`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'DELETE',
        'feedback_form',
        CONCAT('Deleted feedback_form. Record ID = ', OLD.`formID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_feedback_form_insert_audit`
DELIMITER ;;
CREATE TRIGGER `trg_feedback_form_insert_audit`
AFTER INSERT ON `feedback_form`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'INSERT',
        'feedback_form',
        CONCAT('Added feedback_form. Record ID = ', NEW.`formID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_feedback_form_update_audit`
DELIMITER ;;
CREATE TRIGGER `trg_feedback_form_update_audit`
AFTER UPDATE ON `feedback_form`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'UPDATE',
        'feedback_form',
        CONCAT('Updated feedback_form. Record ID = ', NEW.`formID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_feedback_question_delete_audit`
DELIMITER ;;
CREATE TRIGGER `trg_feedback_question_delete_audit`
AFTER DELETE ON `feedback_question`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'DELETE',
        'feedback_question',
        CONCAT('Deleted feedback_question. Record ID = ', OLD.`questionID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_feedback_question_insert_audit`
DELIMITER ;;
CREATE TRIGGER `trg_feedback_question_insert_audit`
AFTER INSERT ON `feedback_question`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'INSERT',
        'feedback_question',
        CONCAT('Added feedback_question. Record ID = ', NEW.`questionID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_feedback_question_update_audit`
DELIMITER ;;
CREATE TRIGGER `trg_feedback_question_update_audit`
AFTER UPDATE ON `feedback_question`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'UPDATE',
        'feedback_question',
        CONCAT('Updated feedback_question. Record ID = ', NEW.`questionID`)
    );
END ;;
DELIMITER ;

-- COMPATIBILITY VALIDATION: replaces chk_feedback_response_3 because participantID/staffID
-- participate in cascading foreign keys.
DELIMITER ;;
CREATE TRIGGER `trg_feedback_response_validate_insert`
BEFORE INSERT ON `feedback_response`
FOR EACH ROW
BEGIN
    IF (NEW.`responseType` = 'participant' AND NEW.`participantID` IS NULL)
       OR (NEW.`responseType` = 'staff_edu' AND NEW.`staffID` IS NULL) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Participant feedback requires participantID; staff feedback requires staffID.';
    END IF;
END ;;
DELIMITER ;

DELIMITER ;;
CREATE TRIGGER `trg_feedback_response_validate_update`
BEFORE UPDATE ON `feedback_response`
FOR EACH ROW
BEGIN
    IF (NEW.`responseType` = 'participant' AND NEW.`participantID` IS NULL)
       OR (NEW.`responseType` = 'staff_edu' AND NEW.`staffID` IS NULL) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Participant feedback requires participantID; staff feedback requires staffID.';
    END IF;
END ;;
DELIMITER ;

-- TRIGGER `trg_feedback_response_delete_audit`
DELIMITER ;;
CREATE TRIGGER `trg_feedback_response_delete_audit`
AFTER DELETE ON `feedback_response`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'DELETE',
        'feedback_response',
        CONCAT('Deleted feedback_response. Record ID = ', OLD.`responseID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_feedback_response_insert_audit`
DELIMITER ;;
CREATE TRIGGER `trg_feedback_response_insert_audit`
AFTER INSERT ON `feedback_response`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'INSERT',
        'feedback_response',
        CONCAT('Added feedback_response. Record ID = ', NEW.`responseID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_feedback_response_update_audit`
DELIMITER ;;
CREATE TRIGGER `trg_feedback_response_update_audit`
AFTER UPDATE ON `feedback_response`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'UPDATE',
        'feedback_response',
        CONCAT('Updated feedback_response. Record ID = ', NEW.`responseID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_guru_new_delete_audit`
DELIMITER ;;
CREATE TRIGGER `trg_guru_new_delete_audit`
AFTER DELETE ON `guru_new`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'DELETE',
        'guru_new',
        CONCAT('Deleted guru_new. Record ID = ', OLD.`gn_id`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_guru_new_insert_audit`
DELIMITER ;;
CREATE TRIGGER `trg_guru_new_insert_audit`
AFTER INSERT ON `guru_new`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'INSERT',
        'guru_new',
        CONCAT('Added guru_new. Record ID = ', NEW.`gn_id`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_guru_new_update_audit`
DELIMITER ;;
CREATE TRIGGER `trg_guru_new_update_audit`
AFTER UPDATE ON `guru_new`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'UPDATE',
        'guru_new',
        CONCAT('Updated guru_new. Record ID = ', NEW.`gn_id`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_hr_administrator_delete_audit`
DELIMITER ;;
CREATE TRIGGER `trg_hr_administrator_delete_audit`
AFTER DELETE ON `hr_administrator`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'DELETE',
        'hr_administrator',
        CONCAT('Deleted hr_administrator. Record ID = ', OLD.`hrid`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_hr_administrator_insert_audit`
DELIMITER ;;
CREATE TRIGGER `trg_hr_administrator_insert_audit`
AFTER INSERT ON `hr_administrator`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'INSERT',
        'hr_administrator',
        CONCAT('Added hr_administrator. Record ID = ', NEW.`hrid`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_hr_administrator_update_audit`
DELIMITER ;;
CREATE TRIGGER `trg_hr_administrator_update_audit`
AFTER UPDATE ON `hr_administrator`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'UPDATE',
        'hr_administrator',
        CONCAT('Updated hr_administrator. Record ID = ', NEW.`hrid`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_login_audit`
DELIMITER ;;
CREATE TRIGGER `trg_login_audit`
AFTER INSERT ON `login_session`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        NEW.staffID,
        COALESCE((SELECT staffName FROM staff_edu WHERE staffID = NEW.staffID), 'System'),
        'LOGIN',
        'login_session',
        CONCAT('Staff logged in. Login Time = ', NEW.loginTime)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_logout_audit`
DELIMITER ;;
CREATE TRIGGER `trg_logout_audit`
AFTER UPDATE ON `login_session`
FOR EACH ROW
BEGIN
    IF OLD.sessionStatus = 'active' AND NEW.sessionStatus = 'ended' THEN
        INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
        VALUES (
            NEW.staffID,
            COALESCE((SELECT staffName FROM staff_edu WHERE staffID = NEW.staffID), 'System'),
            'LOGOUT',
            'login_session',
            CONCAT('Staff logged out. Logout Time = ', NEW.logoutTime)
        );
    END IF;
END ;;
DELIMITER ;

-- COMPATIBILITY VALIDATION: replaces chk_observer_assignment_2 because observerID and
-- externalObserverID participate in SET NULL / CASCADE foreign-key actions.
DELIMITER ;;
CREATE TRIGGER `trg_observer_assignment_validate_insert`
BEFORE INSERT ON `observer_assignment`
FOR EACH ROW
BEGIN
    IF NEW.`observerID` IS NULL AND NEW.`externalObserverID` IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Observer assignment requires an observer or external observer.';
    END IF;
END ;;
DELIMITER ;

DELIMITER ;;
CREATE TRIGGER `trg_observer_assignment_validate_update`
BEFORE UPDATE ON `observer_assignment`
FOR EACH ROW
BEGIN
    IF NEW.`observerID` IS NULL AND NEW.`externalObserverID` IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Observer assignment requires an observer or external observer.';
    END IF;
END ;;
DELIMITER ;

-- TRIGGER `trg_observer_assignment_delete_audit`
DELIMITER ;;
CREATE TRIGGER `trg_observer_assignment_delete_audit`
AFTER DELETE ON `observer_assignment`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'DELETE',
        'observer_assignment',
        CONCAT('Deleted observer_assignment. Record ID = ', OLD.`assignmentID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_observer_assignment_insert_audit`
DELIMITER ;;
CREATE TRIGGER `trg_observer_assignment_insert_audit`
AFTER INSERT ON `observer_assignment`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'INSERT',
        'observer_assignment',
        CONCAT('Added observer_assignment. Record ID = ', NEW.`assignmentID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_observer_assignment_update_audit`
DELIMITER ;;
CREATE TRIGGER `trg_observer_assignment_update_audit`
AFTER UPDATE ON `observer_assignment`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'UPDATE',
        'observer_assignment',
        CONCAT('Updated observer_assignment. Record ID = ', NEW.`assignmentID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_observer_delete_audit`
DELIMITER ;;
CREATE TRIGGER `trg_observer_delete_audit`
AFTER DELETE ON `observer`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'DELETE',
        'observer',
        CONCAT('Deleted observer. Record ID = ', OLD.`observerID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_observer_insert_audit`
DELIMITER ;;
CREATE TRIGGER `trg_observer_insert_audit`
AFTER INSERT ON `observer`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'INSERT',
        'observer',
        CONCAT('Added observer. Record ID = ', NEW.`observerID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_observer_update_audit`
DELIMITER ;;
CREATE TRIGGER `trg_observer_update_audit`
AFTER UPDATE ON `observer`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'UPDATE',
        'observer',
        CONCAT('Updated observer. Record ID = ', NEW.`observerID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_outsider_delete_audit`
DELIMITER ;;
CREATE TRIGGER `trg_outsider_delete_audit`
AFTER DELETE ON `outsider`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'DELETE',
        'outsider',
        CONCAT('Deleted outsider. Record ID = ', OLD.`outsider_id`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_outsider_insert_audit`
DELIMITER ;;
CREATE TRIGGER `trg_outsider_insert_audit`
AFTER INSERT ON `outsider`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'INSERT',
        'outsider',
        CONCAT('Added outsider. Record ID = ', NEW.`outsider_id`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_outsider_update_audit`
DELIMITER ;;
CREATE TRIGGER `trg_outsider_update_audit`
AFTER UPDATE ON `outsider`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'UPDATE',
        'outsider',
        CONCAT('Updated outsider. Record ID = ', NEW.`outsider_id`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_qr_session_delete_audit`
DELIMITER ;;
CREATE TRIGGER `trg_qr_session_delete_audit`
AFTER DELETE ON `qr_session`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'DELETE',
        'qr_session',
        CONCAT('Deleted qr_session. Record ID = ', OLD.`qrID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_qr_session_insert_audit`
DELIMITER ;;
CREATE TRIGGER `trg_qr_session_insert_audit`
AFTER INSERT ON `qr_session`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'INSERT',
        'qr_session',
        CONCAT('Added qr_session. Record ID = ', NEW.`qrID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_qr_session_update_audit`
DELIMITER ;;
CREATE TRIGGER `trg_qr_session_update_audit`
AFTER UPDATE ON `qr_session`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'UPDATE',
        'qr_session',
        CONCAT('Updated qr_session. Record ID = ', NEW.`qrID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_school_delete_audit`
DELIMITER ;;
CREATE TRIGGER `trg_school_delete_audit`
AFTER DELETE ON `school`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'DELETE',
        'school',
        CONCAT('Deleted school. Record ID = ', OLD.`schoolID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_school_insert_audit`
DELIMITER ;;
CREATE TRIGGER `trg_school_insert_audit`
AFTER INSERT ON `school`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'INSERT',
        'school',
        CONCAT('Added school. Record ID = ', NEW.`schoolID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_school_update_audit`
DELIMITER ;;
CREATE TRIGGER `trg_school_update_audit`
AFTER UPDATE ON `school`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'UPDATE',
        'school',
        CONCAT('Updated school. Record ID = ', NEW.`schoolID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_session_trainer_delete_audit`
DELIMITER ;;
CREATE TRIGGER `trg_session_trainer_delete_audit`
AFTER DELETE ON `session_trainer`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'DELETE',
        'session_trainer',
        CONCAT('Deleted session_trainer. Record ID = ', CONCAT(OLD.trainerID, '-', OLD.sessionID))
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_session_trainer_insert_audit`
DELIMITER ;;
CREATE TRIGGER `trg_session_trainer_insert_audit`
AFTER INSERT ON `session_trainer`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'INSERT',
        'session_trainer',
        CONCAT('Added session_trainer. Record ID = ', CONCAT(NEW.trainerID, '-', NEW.sessionID))
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_session_trainer_update_audit`
DELIMITER ;;
CREATE TRIGGER `trg_session_trainer_update_audit`
AFTER UPDATE ON `session_trainer`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'UPDATE',
        'session_trainer',
        CONCAT('Updated session_trainer. Record ID = ', CONCAT(NEW.trainerID, '-', NEW.sessionID))
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_staff_edu_delete_audit`
DELIMITER ;;
CREATE TRIGGER `trg_staff_edu_delete_audit`
AFTER DELETE ON `staff_edu`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'DELETE',
        'staff_edu',
        CONCAT('Deleted staff_edu. Record ID = ', OLD.`staffID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_staff_edu_insert_audit`
DELIMITER ;;
CREATE TRIGGER `trg_staff_edu_insert_audit`
AFTER INSERT ON `staff_edu`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'INSERT',
        'staff_edu',
        CONCAT('Added staff_edu. Record ID = ', NEW.`staffID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_staff_edu_update_audit`
DELIMITER ;;
CREATE TRIGGER `trg_staff_edu_update_audit`
AFTER UPDATE ON `staff_edu`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'UPDATE',
        'staff_edu',
        CONCAT('Updated staff_edu. Record ID = ', NEW.`staffID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_teacher_delete_audit`
DELIMITER ;;
CREATE TRIGGER `trg_teacher_delete_audit`
AFTER DELETE ON `teacher`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'DELETE',
        'teacher',
        CONCAT('Deleted teacher. Record ID = ', OLD.`teacherID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_teacher_insert_audit`
DELIMITER ;;
CREATE TRIGGER `trg_teacher_insert_audit`
AFTER INSERT ON `teacher`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'INSERT',
        'teacher',
        CONCAT('Added teacher. Record ID = ', NEW.`teacherID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_teacher_update_audit`
DELIMITER ;;
CREATE TRIGGER `trg_teacher_update_audit`
AFTER UPDATE ON `teacher`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'UPDATE',
        'teacher',
        CONCAT('Updated teacher. Record ID = ', NEW.`teacherID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_trainer_delete_audit`
DELIMITER ;;
CREATE TRIGGER `trg_trainer_delete_audit`
AFTER DELETE ON `trainer`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'DELETE',
        'trainer',
        CONCAT('Deleted trainer. Record ID = ', OLD.`trainerID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_trainer_document_delete_audit`
DELIMITER ;;
CREATE TRIGGER `trg_trainer_document_delete_audit`
AFTER DELETE ON `trainer_document`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'DELETE',
        'trainer_document',
        CONCAT('Deleted trainer document: ', OLD.title)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_trainer_document_insert_audit`
DELIMITER ;;
CREATE TRIGGER `trg_trainer_document_insert_audit`
AFTER INSERT ON `trainer_document`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'INSERT',
        'trainer_document',
        CONCAT('Added trainer document: ', NEW.title)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_trainer_document_update_audit`
DELIMITER ;;
CREATE TRIGGER `trg_trainer_document_update_audit`
AFTER UPDATE ON `trainer_document`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'UPDATE',
        'trainer_document',
        CONCAT('Updated trainer document: ', NEW.title)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_trainer_insert_audit`
DELIMITER ;;
CREATE TRIGGER `trg_trainer_insert_audit`
AFTER INSERT ON `trainer`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'INSERT',
        'trainer',
        CONCAT('Added trainer. Record ID = ', NEW.`trainerID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_trainer_update_audit`
DELIMITER ;;
CREATE TRIGGER `trg_trainer_update_audit`
AFTER UPDATE ON `trainer`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'UPDATE',
        'trainer',
        CONCAT('Updated trainer. Record ID = ', NEW.`trainerID`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_users_delete_audit`
DELIMITER ;;
CREATE TRIGGER `trg_users_delete_audit`
AFTER DELETE ON `users`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'DELETE',
        'users',
        CONCAT('Deleted users. Record ID = ', OLD.`id`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_users_insert_audit`
DELIMITER ;;
CREATE TRIGGER `trg_users_insert_audit`
AFTER INSERT ON `users`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'INSERT',
        'users',
        CONCAT('Added users. Record ID = ', NEW.`id`)
    );
END ;;
DELIMITER ;

-- TRIGGER `trg_users_update_audit`
DELIMITER ;;
CREATE TRIGGER `trg_users_update_audit`
AFTER UPDATE ON `users`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (staffID, userName, actionType, tableName, newValue)
    VALUES (
        @current_staff_id,
        COALESCE(@current_staff_name, @current_staff_id, 'System'),
        'UPDATE',
        'users',
        CONCAT('Updated users. Record ID = ', NEW.`id`)
    );
END ;;
DELIMITER ;

-- TRIGGER `set_teacher_service_date_insert`
DELIMITER $$
CREATE TRIGGER `set_teacher_service_date_insert` BEFORE INSERT ON `teacher` FOR EACH ROW
BEGIN
    -- serviceDate is now a DATE. Preserve an explicitly supplied serviceDate;
    -- otherwise use appointedDate as the service-start fallback.
    IF NEW.serviceDate IS NULL AND NEW.appointedDate IS NOT NULL THEN
        SET NEW.serviceDate = NEW.appointedDate;
    END IF;
END
$$
DELIMITER ;

-- TRIGGER `set_teacher_service_date_update`
DELIMITER $$
CREATE TRIGGER `set_teacher_service_date_update` BEFORE UPDATE ON `teacher` FOR EACH ROW
BEGIN
    IF NEW.serviceDate IS NULL AND NEW.appointedDate IS NOT NULL THEN
        SET NEW.serviceDate = NEW.appointedDate;
    END IF;
END
$$
DELIMITER ;

-- TRIGGER `trg_generate_gn_id`
DELIMITER $$
CREATE TRIGGER `trg_generate_gn_id` BEFORE INSERT ON `guru_new` FOR EACH ROW
BEGIN
    DECLARE next_number INT;
    IF NEW.gn_id IS NULL OR NEW.gn_id = '' THEN
        SELECT COALESCE(MAX(CAST(SUBSTRING(gn_id, 3) AS UNSIGNED)), 0) + 1
          INTO next_number
          FROM guru_new;
        SET NEW.gn_id = CONCAT('GN', LPAD(next_number, 4, '0'));
    END IF;
END
$$
DELIMITER ;

-- ============================================================
-- NUREEN FUNCTIONS (from supplied stored procedure/functions file)
-- Environment-specific DEFINER removed for portable import.
-- ============================================================
DELIMITER $$

DROP FUNCTION IF EXISTS `fn_age_from_ic`$$
CREATE FUNCTION `fn_age_from_ic`(`p_ic` VARCHAR(20)
) RETURNS int(11)
    NO SQL
BEGIN
    DECLARE v_clean_ic VARCHAR(20);
    DECLARE v_year INT;
    DECLARE v_full_year INT;
    DECLARE v_birthdate DATE;
    DECLARE v_current_yy INT;

    SET v_clean_ic = REPLACE(TRIM(p_ic), '-', '');

    IF v_clean_ic IS NULL
       OR LENGTH(v_clean_ic) < 6
       OR LEFT(v_clean_ic, 6) NOT REGEXP '^[0-9]{6}$' THEN
        RETURN NULL;
    END IF;

    SET v_year = CAST(SUBSTRING(v_clean_ic, 1, 2) AS UNSIGNED);
    SET v_current_yy = CAST(DATE_FORMAT(CURDATE(), '%y') AS UNSIGNED);

    IF v_year > v_current_yy THEN
        SET v_full_year = 1900 + v_year;
    ELSE
        SET v_full_year = 2000 + v_year;
    END IF;

    SET v_birthdate = STR_TO_DATE(
        CONCAT(
            v_full_year,
            SUBSTRING(v_clean_ic, 3, 2),
            SUBSTRING(v_clean_ic, 5, 2)
        ),
        '%Y%m%d'
    );

    IF v_birthdate IS NULL
       OR v_birthdate > CURDATE() THEN
        RETURN NULL;
    END IF;

    RETURN TIMESTAMPDIFF(
        YEAR,
        v_birthdate,
        CURDATE()
    );
END$$

DROP FUNCTION IF EXISTS `fn_calculate_credit_hour`$$
CREATE FUNCTION `fn_calculate_credit_hour`(`p_staffID` VARCHAR(20),
    `p_courseID` VARCHAR(20),
    `p_hours` DECIMAL(7,2)
) RETURNS decimal(7,2)
    READS SQL DATA
BEGIN
    DECLARE v_staffIC VARCHAR(20);
    DECLARE v_organiser VARCHAR(255);
    DECLARE v_credit DECIMAL(7,2) DEFAULT 0;

    IF p_hours IS NULL OR p_hours <= 0 THEN
        RETURN 0;
    END IF;

    SELECT REPLACE(REPLACE(TRIM(ICNumber), '-', ''), ' ', '')
      INTO v_staffIC
      FROM staff_edu
     WHERE staffID = p_staffID
     LIMIT 1;

    SELECT organiserName
      INTO v_organiser
      FROM course
     WHERE courseID = p_courseID
     LIMIT 1;

    IF v_staffIC IS NOT NULL AND v_staffIC <> '' AND EXISTS (
        SELECT 1
        FROM trainer tr
        JOIN session_trainer st ON st.trainerID = tr.trainerID
        JOIN course_session cs ON cs.sessionID = st.sessionID
        WHERE cs.courseID = p_courseID
          AND REPLACE(REPLACE(TRIM(tr.trainerIC), '-', ''), ' ', '') = v_staffIC
    ) THEN
        SET v_credit = p_hours * 1.5;
    ELSEIF LOWER(COALESCE(v_organiser, '')) LIKE '%al amin edu oasis%' THEN
        SET v_credit = p_hours * 1.0;
    ELSE
        SET v_credit = p_hours * 0.5;
    END IF;

    RETURN ROUND(v_credit, 2);
END$$

DROP FUNCTION IF EXISTS `fn_service_duration`$$
CREATE FUNCTION `fn_service_duration`(`p_appointedDate` DATE
) RETURNS varchar(20) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci
    NO SQL
BEGIN
    DECLARE v_years INT DEFAULT 0;
    DECLARE v_months INT DEFAULT 0;
    DECLARE v_days INT DEFAULT 0;
    DECLARE v_tempDate DATE;

    IF p_appointedDate IS NULL THEN
        RETURN NULL;
    END IF;

    SET v_years =
        TIMESTAMPDIFF(
            YEAR,
            p_appointedDate,
            CURDATE()
        );

    SET v_tempDate =
        DATE_ADD(
            p_appointedDate,
            INTERVAL v_years YEAR
        );

    IF v_tempDate > CURDATE() THEN

        SET v_years = v_years - 1;

        SET v_tempDate =
            DATE_ADD(
                p_appointedDate,
                INTERVAL v_years YEAR
            );

    END IF;

    SET v_months =
        TIMESTAMPDIFF(
            MONTH,
            v_tempDate,
            CURDATE()
        );

    SET v_tempDate =
        DATE_ADD(
            v_tempDate,
            INTERVAL v_months MONTH
        );

    IF v_tempDate > CURDATE() THEN

        SET v_months = v_months - 1;

        SET v_tempDate =
            DATE_ADD(
                v_tempDate,
                INTERVAL v_months MONTH
            );

    END IF;

    SET v_days =
        DATEDIFF(
            CURDATE(),
            v_tempDate
        );

    RETURN CONCAT(
        LPAD(v_years, 2, '0'),
        ':',
        LPAD(v_months, 2, '0'),
        ':',
        LPAD(v_days, 2, '0')
    );
END$$

DROP FUNCTION IF EXISTS `fn_session_hours`$$
CREATE FUNCTION `fn_session_hours`(`p_sessionID` VARCHAR(20)
) RETURNS decimal(5,2)
    READS SQL DATA
    DETERMINISTIC
BEGIN
    DECLARE v_hours DECIMAL(5,2);

    SELECT ROUND(
        TIMESTAMPDIFF(
            MINUTE,
            CONCAT(CURDATE(), ' ', startTime),
            CONCAT(CURDATE(), ' ', endTime)
        ) / 60,
        2
    )
    INTO v_hours
    FROM course_session
    WHERE sessionID = p_sessionID;

    RETURN IFNULL(v_hours, 0);
END$$

DELIMITER ;

-- ============================================================
-- NUREEN VIEWS (owner definitions retained; adjusted only where canonical schema requires)
-- ============================================================

-- VIEW `v_audit_log_display` from Nureen / nureen_latest(1).sql
DROP VIEW IF EXISTS `v_audit_log_display`;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY DEFINER */
/*!50001 VIEW `v_audit_log_display` AS select `audit_log`.`userName` AS `userName`,`audit_log`.`actionType` AS `changeType`,`audit_log`.`newValue` AS `newValue`,`audit_log`.`actionDate` AS `dateTime` from `audit_log` order by `audit_log`.`actionDate` desc */;

-- VIEW `v_certificate_eligibility` from Nureen / nureen_latest(1).sql
DROP VIEW IF EXISTS `v_certificate_eligibility`;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY DEFINER */
/*!50001 VIEW `v_certificate_eligibility` AS select `cp`.`participantID` AS `participantID`,`cp`.`participantName` AS `participantName`,`cp`.`participantType` AS `participantType`,`cp`.`courseID` AS `courseID`,`c`.`courseName` AS `courseName`,`cp`.`isFeedbackCompleted` AS `isFeedbackCompleted`,case when `cp`.`isFeedbackCompleted` = 1 and !exists(select 1 from `course_session` `cs` where `cs`.`courseID` = `cp`.`courseID` and !exists(select 1 from `attendance` `a` where `cp`.`participantType` = 'teacher' and `a`.`teacher_id` = `cp`.`teacherID` and `a`.`session_id` = `cs`.`sessionID` and `a`.`attendance_status` = 'approved' union all select 1 from `attendance_staff` `ast` where `cp`.`participantType` = 'staff' and `ast`.`staffID` = `cp`.`staffID` and `ast`.`session_id` = `cs`.`sessionID` and `ast`.`attendance_status` = 'approved' union all select 1 from `attendance_guru_baru` `agb` where `cp`.`participantType` = 'new_teacher' and `agb`.`gn_id` = `cp`.`gn_id` and `agb`.`session_id` = `cs`.`sessionID` and `agb`.`attendance_status` = 'approved' union all select 1 from `attendance_outsider` `ao` where `cp`.`participantType` = 'public' and `ao`.`outsider_id` = `cp`.`outsider_id` and `ao`.`session_id` = `cs`.`sessionID` and `ao`.`attendance_status` = 'approved' limit 1) limit 1) then 'Eligible' else 'Not Eligible' end AS `certificateStatus` from (`course_participant` `cp` join `course` `c` on(`cp`.`courseID` = `c`.`courseID`)) */;

-- VIEW `v_teacher_current_school` from Nureen / nureen_latest(1).sql
DROP VIEW IF EXISTS `v_teacher_current_school`;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY DEFINER */
/*!50001 VIEW `v_teacher_current_school` AS select `t`.`teacherID` AS `teacherID`,(select `a`.`schoolID` from `assign` `a` where `a`.`teacherID` = `t`.`teacherID` order by case when lcase(trim(coalesce(`a`.`status`,''))) in ('aktif','active') then 0 else 1 end,`a`.`assignDate` desc,`a`.`schoolID` limit 1) AS `schoolID` from `teacher` `t` */;

-- VIEW `v_course_participant_details` from Nureen / nureen_latest(1).sql
DROP VIEW IF EXISTS `v_course_participant_details`;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY DEFINER */
/*!50001 VIEW `v_course_participant_details` AS select `cp`.`participantID` AS `participantID`,`cp`.`participantType` AS `participantType`,`cp`.`participantName` AS `participantName`,`cp`.`courseID` AS `courseID`,`c`.`courseName` AS `courseName`,case when `cp`.`participantType` = 'teacher' then `s`.`schoolName` when `cp`.`participantType` = 'staff' then `se`.`department` when `cp`.`participantType` = 'new_teacher' then `sg`.`schoolName` when `cp`.`participantType` = 'public' then coalesce(`o`.`organization`,'Public') end AS `respondentGroup`,`cp`.`RSVPStatus` AS `RSVPStatus`,`cp`.`isFeedbackCompleted` AS `isFeedbackCompleted`,`cp`.`replacementStatus` AS `replacementStatus` from ((((((((`course_participant` `cp` join `course` `c` on(`cp`.`courseID` = `c`.`courseID`)) left join `teacher` `t` on(`cp`.`teacherID` = `t`.`teacherID`)) left join `v_teacher_current_school` `tcs` on(`t`.`teacherID` = `tcs`.`teacherID`)) left join `school` `s` on(`tcs`.`schoolID` = `s`.`schoolID`)) left join `staff_edu` `se` on(`cp`.`staffID` = `se`.`staffID`)) left join `guru_new` `gn` on(`cp`.`gn_id` = `gn`.`gn_id`)) left join `school` `sg` on(`gn`.`schoolID` = `sg`.`schoolID`)) left join `outsider` `o` on(`cp`.`outsider_id` = `o`.`outsider_id`)) */;

-- VIEW `v_course_session_overview` from Nureen / nureen_latest(1).sql
DROP VIEW IF EXISTS `v_course_session_overview`;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY DEFINER */
/*!50001 VIEW `v_course_session_overview` AS select `c`.`courseID` AS `courseID`,`c`.`courseName` AS `courseName`,`c`.`courseCategory` AS `courseCategory`,`c`.`targetAudience` AS `targetAudiences`,`c`.`otherTargetAudience` AS `otherTargetAudience`,`c`.`staffAttendeeIDs` AS `staffAttendeeIDs`,`c`.`courseType` AS `courseType`,`c`.`mode` AS `mode`,`c`.`status` AS `courseStatus`,`cs`.`sessionID` AS `sessionID`,`cs`.`sessionDate` AS `sessionDate`,`cs`.`sessionName` AS `sessionName`,`cs`.`startTime` AS `startTime`,`cs`.`endTime` AS `endTime`,`cs`.`location` AS `location`,group_concat(distinct `t`.`trainerName` order by `t`.`trainerName` ASC separator ', ') AS `trainerNames` from (((`course` `c` join `course_session` `cs` on(`c`.`courseID` = `cs`.`courseID`)) left join `session_trainer` `st` on(`cs`.`sessionID` = `st`.`sessionID`)) left join `trainer` `t` on(`st`.`trainerID` = `t`.`trainerID`)) group by `c`.`courseID`,`c`.`courseName`,`c`.`courseCategory`,`c`.`targetAudience`,`c`.`otherTargetAudience`,`c`.`staffAttendeeIDs`,`c`.`courseType`,`c`.`mode`,`c`.`status`,`cs`.`sessionID`,`cs`.`sessionDate`,`cs`.`sessionName`,`cs`.`startTime`,`cs`.`endTime`,`cs`.`location` */;

-- VIEW `v_feedback_category_average` from Nureen / nureen_latest(1).sql
DROP VIEW IF EXISTS `v_feedback_category_average`;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY DEFINER */
/*!50001 VIEW `v_feedback_category_average` AS select `c`.`courseID` AS `courseID`,`c`.`courseName` AS `courseName`,`ff`.`formID` AS `formID`,`ff`.`feedbackType` AS `feedbackType`,`cs`.`sessionID` AS `sessionID`,`cs`.`sessionDate` AS `sessionDate`,`fc`.`categoryID` AS `categoryID`,`fc`.`categoryName` AS `categoryName`,round(avg(`fr`.`rating`),2) AS `averageRating` from (((((`feedback_response` `fr` join `feedback_question` `fq` on(`fr`.`questionID` = `fq`.`questionID`)) join `feedback_category` `fc` on(`fq`.`categoryID` = `fc`.`categoryID`)) join `feedback_form` `ff` on(`fc`.`formID` = `ff`.`formID`)) left join `course_session` `cs` on(`ff`.`sessionID` = `cs`.`sessionID`)) join `course` `c` on(coalesce(`ff`.`courseID`,`cs`.`courseID`) = `c`.`courseID`)) where `fq`.`questionType` = 'rating' group by `c`.`courseID`,`c`.`courseName`,`ff`.`formID`,`ff`.`feedbackType`,`cs`.`sessionID`,`cs`.`sessionDate`,`fc`.`categoryID`,`fc`.`categoryName` */;

-- VIEW `v_staff_credit_hour` from Nureen / nureen_latest(1).sql
DROP VIEW IF EXISTS `v_staff_credit_hour`;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY DEFINER */
/*!50001 VIEW `v_staff_credit_hour` AS select `se`.`staffID` AS `staffID`,`se`.`staffName` AS `staffName`,year(curdate()) AS `creditYear`,least(10,coalesce(`tb`.`tarbiahCreditHour`,0)) AS `tarbiahCreditHour`,least(30,coalesce(`tc`.`trainingCreditHour`,0)) AS `trainingCreditHour`,least(40,least(10,coalesce(`tb`.`tarbiahCreditHour`,0)) + least(30,coalesce(`tc`.`trainingCreditHour`,0))) AS `totalCreditHour` from ((`staff_edu` `se` left join (select `sta`.`staffID` AS `staffID`,round(sum(case when `t`.`end_time` > `t`.`start_time` then time_to_sec(timediff(`t`.`end_time`,`t`.`start_time`)) / 3600 else 0 end),2) AS `tarbiahCreditHour` from (`staff_tarbiah_attendance` `sta` join `tarbiah` `t` on(`t`.`tarbiah_id` = `sta`.`tarbiah_id`)) where `sta`.`attendance_status` = 'approved' and year(`t`.`session_date`) = year(curdate()) group by `sta`.`staffID`) `tb` on(`tb`.`staffID` = `se`.`staffID`)) left join (select `ast`.`staffID` AS `staffID`,round(sum(`fn_calculate_credit_hour`(`ast`.`staffID`,`cs`.`courseID`,case when `cs`.`endTime` > `cs`.`startTime` then time_to_sec(timediff(`cs`.`endTime`,`cs`.`startTime`)) / 3600 else coalesce(`ast`.`hours_ladap`,0) end)),2) AS `trainingCreditHour` from (`attendance_staff` `ast` join `course_session` `cs` on(`cs`.`sessionID` = `ast`.`session_id`)) where `ast`.`attendance_status` = 'approved' and year(`cs`.`sessionDate`) = year(curdate()) group by `ast`.`staffID`) `tc` on(`tc`.`staffID` = `se`.`staffID`)) */;

-- VIEW `v_staff_feedback_summary` from Nureen / nureen_latest(1).sql
DROP VIEW IF EXISTS `v_staff_feedback_summary`;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY DEFINER */
/*!50001 VIEW `v_staff_feedback_summary` AS select `se`.`staffID` AS `staffID`,`se`.`staffName` AS `staffName`,`c`.`courseID` AS `courseID`,`c`.`courseName` AS `courseName`,`ff`.`formID` AS `formID`,`fc`.`categoryName` AS `categoryName`,round(avg(`fr`.`rating`),2) AS `averageRating`,count(`fr`.`responseID`) AS `totalResponses` from ((((((`feedback_response` `fr` join `staff_edu` `se` on(`fr`.`staffID` = `se`.`staffID`)) join `feedback_question` `fq` on(`fr`.`questionID` = `fq`.`questionID`)) join `feedback_category` `fc` on(`fq`.`categoryID` = `fc`.`categoryID`)) join `feedback_form` `ff` on(`fc`.`formID` = `ff`.`formID`)) left join `course_session` `cs` on(`ff`.`sessionID` = `cs`.`sessionID`)) join `course` `c` on(coalesce(`ff`.`courseID`,`cs`.`courseID`) = `c`.`courseID`)) where `fr`.`responseType` = 'staff_edu' group by `se`.`staffID`,`se`.`staffName`,`c`.`courseID`,`c`.`courseName`,`ff`.`formID`,`fc`.`categoryName` */;

-- VIEW `v_trainer_rating` from Nureen / nureen_latest(1).sql
DROP VIEW IF EXISTS `v_trainer_rating`;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY DEFINER */
/*!50001 VIEW `v_trainer_rating` AS select `t`.`trainerID` AS `trainerID`,`t`.`trainerName` AS `trainerName`,`c`.`courseID` AS `courseID`,`c`.`courseName` AS `courseName`,`cs`.`sessionID` AS `sessionID`,`cs`.`sessionDate` AS `sessionDate`,round(avg(`fr`.`rating`),2) AS `trainerAverageRating` from (((((((`feedback_response` `fr` join `feedback_question` `fq` on(`fr`.`questionID` = `fq`.`questionID`)) join `feedback_category` `fc` on(`fq`.`categoryID` = `fc`.`categoryID`)) join `feedback_form` `ff` on(`fc`.`formID` = `ff`.`formID`)) join `course_session` `cs` on(`ff`.`sessionID` = `cs`.`sessionID`)) join `course` `c` on(`cs`.`courseID` = `c`.`courseID`)) join `session_trainer` `st` on(`cs`.`sessionID` = `st`.`sessionID`)) join `trainer` `t` on(`st`.`trainerID` = `t`.`trainerID`)) where `fq`.`questionType` = 'rating' and `ff`.`feedbackType` = 'participant' and `fc`.`categoryName` like '%Trainer%' group by `t`.`trainerID`,`t`.`trainerName`,`c`.`courseID`,`c`.`courseName`,`cs`.`sessionID`,`cs`.`sessionDate` */;


-- ============================================================
-- NUREEN STORED PROCEDURES (from supplied stored procedure/functions file)
-- Environment-specific DEFINER removed for portable import.
-- ============================================================
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_add_approved_participant`$$
CREATE PROCEDURE `sp_add_approved_participant`(IN `p_participantID` VARCHAR(20), IN `p_participantType` VARCHAR(20), IN `p_sourceID` VARCHAR(20), IN `p_courseID` VARCHAR(20))
BEGIN
    DECLARE v_rows INT DEFAULT 0;
    DECLARE v_type VARCHAR(20);

    SET v_type = LOWER(TRIM(p_participantType));


    -- =========================
    -- TEACHER
    -- =========================

    IF v_type = 'teacher' THEN

        INSERT INTO course_participant (
            participantID,
            participantType,
            participantName,
            organisationName,
            ICNumber,
            phoneNumber,
            email,
            paymentProof,
            RSVPStatus,
            isFeedbackCompleted,
            replacementStatus,
            replacedByName,
            replacementReason,
            courseID,
            gn_id,
            outsider_id,
            teacherID,
            staffID
        )
        SELECT
            p_participantID,
            'teacher',
            t.teacherName,
            COALESCE(s.schoolName, 'School'),
            t.ICNumber,
            t.phoneNumber,
            t.email,
            NULL,
            'Will come',
            0,
            'original',
            NULL,
            NULL,
            et.course_id,
            NULL,
            NULL,
            t.teacherID,
            NULL
        FROM enroll_teacher et
        JOIN teacher t
            ON et.teacherID = t.teacherID
        LEFT JOIN v_teacher_current_school tcs
            ON t.teacherID = tcs.teacherID
        LEFT JOIN school s
            ON tcs.schoolID = s.schoolID
        WHERE et.teacherID = (p_sourceID COLLATE utf8mb4_unicode_ci)
          AND et.course_id = p_courseID
          AND et.status = 'approved';


    -- =========================
    -- STAFF
    -- =========================

    ELSEIF v_type = 'staff' THEN

        INSERT INTO course_participant (
            participantID,
            participantType,
            participantName,
            organisationName,
            ICNumber,
            phoneNumber,
            email,
            paymentProof,
            RSVPStatus,
            isFeedbackCompleted,
            replacementStatus,
            replacedByName,
            replacementReason,
            courseID,
            gn_id,
            outsider_id,
            teacherID,
            staffID
        )
        SELECT
            p_participantID,
            'staff',
            se.staffName,
            se.department,
            se.ICNumber,
            se.phoneNumber,
            se.email,
            NULL,
            'Will come',
            0,
            'original',
            NULL,
            NULL,
            c.courseID,
            NULL,
            NULL,
            NULL,
            se.staffID
        FROM staff_edu se
        JOIN course c
            ON c.courseID = p_courseID
        WHERE se.staffID = (p_sourceID COLLATE utf8mb4_unicode_ci)

          AND FIND_IN_SET(
              'staff',
              c.targetAudience
          ) > 0

          AND FIND_IN_SET(
              se.staffID,
              REPLACE(
                  COALESCE(
                      c.staffAttendeeIDs,
                      ''
                  ),
                  ' ',
                  ''
              )
          ) > 0

          AND LOWER(
              COALESCE(
                  se.status,
                  'active'
              )
          ) = 'active';


    -- =========================
    -- NEW TEACHER
    -- =========================

    ELSEIF v_type = 'new_teacher' THEN

        INSERT INTO course_participant (
            participantID,
            participantType,
            participantName,
            organisationName,
            ICNumber,
            phoneNumber,
            email,
            paymentProof,
            RSVPStatus,
            isFeedbackCompleted,
            replacementStatus,
            replacedByName,
            replacementReason,
            courseID,
            gn_id,
            outsider_id,
            teacherID,
            staffID
        )
        SELECT
            p_participantID,
            'new_teacher',
            gn.gn_name,
            s.schoolName,
            gn.ic_number,
            gn.phone_number,
            gn.email,
            NULL,
            'Will come',
            0,
            'original',
            NULL,
            NULL,
            egb.course_id,
            gn.gn_id,
            NULL,
            NULL,
            NULL
        FROM enroll_guru_baru egb
        JOIN guru_new gn
            ON egb.gn_id = gn.gn_id
        LEFT JOIN school s
            ON gn.schoolID = s.schoolID
        WHERE egb.gn_id = (p_sourceID COLLATE utf8mb4_unicode_ci)
          AND egb.course_id = p_courseID
          AND egb.status IN (
              'approved',
              'auto_enrolled'
          );


    -- =========================
    -- PUBLIC
    -- =========================

    ELSEIF v_type = 'public' THEN

        INSERT INTO course_participant (
            participantID,
            participantType,
            participantName,
            organisationName,
            ICNumber,
            phoneNumber,
            email,
            paymentProof,
            RSVPStatus,
            isFeedbackCompleted,
            replacementStatus,
            replacedByName,
            replacementReason,
            courseID,
            gn_id,
            outsider_id,
            teacherID,
            staffID
        )
        SELECT
            p_participantID,
            'public',
            o.name,
            COALESCE(
                o.organization,
                'Public'
            ),
            o.ICNumber,
            o.phoneNumber,
            u.email,
            NULL,
            'Will come',
            0,
            'original',
            NULL,
            NULL,
            eo.course_id,
            NULL,
            o.outsider_id,
            NULL,
            NULL
        FROM enroll_outsider eo
        JOIN outsider o
            ON eo.outsider_id = o.outsider_id
        LEFT JOIN users u
            ON o.user_id = u.id
        WHERE eo.outsider_id =
              CAST(
                  p_sourceID
                  AS UNSIGNED
              )
          AND eo.course_id = p_courseID
          AND eo.status = 'approved';


    ELSE

        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT =
            'Unsupported participant type. Use teacher, staff, new_teacher, or public.';

    END IF;


    SET v_rows = ROW_COUNT();


    IF v_rows = 0 THEN

        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT =
            'No matching approved source record was found for the participant.';

    END IF;

END$$

DROP PROCEDURE IF EXISTS `sp_add_staff_pic_participant`$$
CREATE PROCEDURE `sp_add_staff_pic_participant`(
    IN `p_participantID` VARCHAR(20),
    IN `p_staffID` VARCHAR(20),
    IN `p_courseID` VARCHAR(20)
)
BEGIN
    INSERT INTO course_participant (
        participantID, participantType, participantName, organisationName,
        ICNumber, phoneNumber, email, paymentProof, RSVPStatus,
        isFeedbackCompleted, replacementStatus, replacedByName,
        replacementReason, courseID, gn_id, outsider_id, teacherID, staffID
    )
    SELECT
        p_participantID, 'staff', se.staffName, se.department,
        se.ICNumber, se.phoneNumber, se.email, NULL, 'Will come',
        0, 'original', NULL, NULL, c.courseID, NULL, NULL, NULL, se.staffID
    FROM staff_edu se
    JOIN course c ON c.courseID = p_courseID
    WHERE se.staffID = p_staffID
      AND FIND_IN_SET(
            se.staffID,
            REPLACE(COALESCE(c.staffAttendeeIDs, ''), ' ', '')
          ) > 0
      AND LOWER(COALESCE(se.status, 'active')) = 'active'
      AND NOT EXISTS (
          SELECT 1
          FROM course_participant cp
          WHERE cp.courseID = p_courseID
            AND cp.staffID = p_staffID
            AND LOWER(cp.participantType) = 'staff'
      );
END$$

DROP PROCEDURE IF EXISTS `sp_update_feedback_completed`$$
CREATE PROCEDURE `sp_update_feedback_completed`(
    IN `p_participantID` VARCHAR(20)
)
BEGIN
    DECLARE v_courseID VARCHAR(20);
    DECLARE v_requiredQuestions INT DEFAULT 0;
    DECLARE v_answeredQuestions INT DEFAULT 0;


    SELECT courseID
    INTO v_courseID
    FROM course_participant
    WHERE participantID = p_participantID;


    IF v_courseID IS NULL THEN

        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT =
            'Participant was not found.';

    END IF;


    SELECT COUNT(*)
    INTO v_requiredQuestions
    FROM feedback_question fq
    JOIN feedback_category fc
        ON fq.categoryID = fc.categoryID
    JOIN feedback_form ff
        ON fc.formID = ff.formID
    LEFT JOIN course_session cs
        ON ff.sessionID = cs.sessionID
    WHERE fq.isRequired = 1
      AND ff.feedbackType = 'participant'
      AND (
          ff.courseID = v_courseID
          OR cs.courseID = v_courseID
      );


    SELECT COUNT(
        DISTINCT fr.questionID
    )
    INTO v_answeredQuestions
    FROM feedback_response fr
    JOIN feedback_question fq
        ON fr.questionID = fq.questionID
    JOIN feedback_category fc
        ON fq.categoryID = fc.categoryID
    JOIN feedback_form ff
        ON fc.formID = ff.formID
    LEFT JOIN course_session cs
        ON ff.sessionID = cs.sessionID
    WHERE fr.participantID = p_participantID
      AND fq.isRequired = 1
      AND ff.feedbackType = 'participant'
      AND (
          ff.courseID = v_courseID
          OR cs.courseID = v_courseID
      );


    UPDATE course_participant

    SET isFeedbackCompleted =
        (
            v_requiredQuestions > 0
            AND
            v_answeredQuestions >=
            v_requiredQuestions
        )

    WHERE participantID =
        p_participantID;

END$$

DROP PROCEDURE IF EXISTS `sp_update_staff_credit_hour`$$
CREATE PROCEDURE `sp_update_staff_credit_hour`(IN `p_staffID` VARCHAR(20))
BEGIN
    DECLARE v_year INT DEFAULT YEAR(CURDATE());
    DECLARE v_training DECIMAL(7,2) DEFAULT 0;
    DECLARE v_tarbiah DECIMAL(7,2) DEFAULT 0;
    DECLARE v_total DECIMAL(7,2) DEFAULT 0;

    SELECT LEAST(30, IFNULL(SUM(
        fn_calculate_credit_hour(
            ast.staffID,
            cs.courseID,
            CASE
                WHEN cs.endTime > cs.startTime
                THEN TIME_TO_SEC(TIMEDIFF(cs.endTime, cs.startTime)) / 3600
                ELSE COALESCE(ast.hours_ladap, 0)
            END
        )
    ), 0))
    INTO v_training
    FROM attendance_staff ast
    JOIN course_session cs ON cs.sessionID = ast.session_id
    WHERE ast.staffID = p_staffID
      AND ast.attendance_status = 'approved'
      AND YEAR(cs.sessionDate) = v_year;

    SELECT LEAST(10, IFNULL(SUM(
        CASE
            WHEN t.end_time > t.start_time
            THEN TIME_TO_SEC(TIMEDIFF(t.end_time, t.start_time)) / 3600
            ELSE 0
        END
    ), 0))
    INTO v_tarbiah
    FROM staff_tarbiah_attendance sta
    JOIN tarbiah t ON t.tarbiah_id = sta.tarbiah_id
    WHERE sta.staffID = p_staffID
      AND sta.attendance_status = 'approved'
      AND YEAR(t.session_date) = v_year;

    SET v_total = LEAST(40, v_training + v_tarbiah);

    UPDATE staff_edu
       SET credit_hour = ROUND(v_total, 2)
     WHERE staffID = p_staffID;
END$$

DROP PROCEDURE IF EXISTS `sp_verify_attendance`$$
CREATE PROCEDURE `sp_verify_attendance`(
    IN `p_participantType` VARCHAR(20),
    IN `p_attendanceID` BIGINT UNSIGNED,
    IN `p_approvalStatus` VARCHAR(20),
    IN `p_approvedByStaff` VARCHAR(20),
    IN `p_rejectionReason` TEXT
)
BEGIN

    IF p_approvalStatus NOT IN (
        'approved',
        'rejected'
    ) THEN

        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT =
            'Approval status must be approved or rejected.';

    END IF;


    -- =========================
    -- TEACHER
    -- =========================

    IF LOWER(p_participantType) = 'teacher' THEN

        UPDATE attendance

        SET attendance_status =
                p_approvalStatus,

            approvedByStaff =
                p_approvedByStaff,

            approved_at =
                NOW(),

            rejection_reason =
                p_rejectionReason,

            timestamp_status =
                IF(
                    p_approvalStatus = 'approved',
                    1.00,
                    0.00
                ),

            updated_at =
                NOW()

        WHERE attendance_id =
              p_attendanceID;


    -- =========================
    -- STAFF
    -- =========================

    ELSEIF LOWER(p_participantType) = 'staff' THEN

        UPDATE attendance_staff

        SET attendance_status =
                p_approvalStatus,

            approvedByStaff =
                p_approvedByStaff,

            approved_at =
                NOW(),

            rejection_reason =
                p_rejectionReason,

            timestamp_status =
                IF(
                    p_approvalStatus = 'approved',
                    1.00,
                    0.00
                ),

            updated_at =
                NOW()

        WHERE attendanceStaff_id =
              p_attendanceID;


    -- =========================
    -- NEW TEACHER
    -- =========================

    ELSEIF LOWER(p_participantType) = 'new_teacher' THEN

        UPDATE attendance_guru_baru

        SET attendance_status =
                p_approvalStatus,

            approvedByStaff =
                p_approvedByStaff,

            approved_at =
                NOW(),

            rejection_reason =
                p_rejectionReason,

            timestamp_status =
                IF(
                    p_approvalStatus = 'approved',
                    1.00,
                    0.00
                ),

            updated_at =
                NOW()

        WHERE attendGuruBaru_id =
              p_attendanceID;


    -- =========================
    -- PUBLIC
    -- =========================

    ELSEIF LOWER(p_participantType) = 'public' THEN

        UPDATE attendance_outsider

        SET attendance_status =
                p_approvalStatus,

            approvedByStaff =
                p_approvedByStaff,

            approved_at =
                NOW(),

            rejection_reason =
                p_rejectionReason,

            timestamp_status =
                IF(
                    p_approvalStatus = 'approved',
                    1.00,
                    0.00
                ),

            updated_at =
                NOW()

        WHERE attendOutsider_id =
              p_attendanceID;


    ELSE

        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT =
            'Unsupported participant type.';

    END IF;

END$$

DELIMITER ;

-- ============================================================
-- DATA
-- ============================================================
-- Intentionally empty in this phase. INSERT migration/reconciliation will be
-- performed only after the canonical schema has been reviewed and approved.

SET UNIQUE_CHECKS = 1;
SET FOREIGN_KEY_CHECKS = 1;
