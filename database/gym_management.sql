-- =======================================================
-- Database Schema: Gym Management System (gym_management)
-- Technologies: MySQL (utf8mb4)
-- =======================================================

CREATE DATABASE IF NOT EXISTS `gym_management` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `gym_management`;


CREATE TABLE IF NOT EXISTS admin_login_otps (
    email VARCHAR(150) NOT NULL PRIMARY KEY,
    otp_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    last_sent_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_login_otps_expiry (expires_at)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


SHOW TABLES LIKE 'admin_login_otps';



-- Disable foreign key checks for clean recreation
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `member_workouts`;
DROP TABLE IF EXISTS `workout_exercises`;
DROP TABLE IF EXISTS `workout_plans`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `attendance`;
DROP TABLE IF EXISTS `memberships`;
DROP TABLE IF EXISTS `membership_plans`;
DROP TABLE IF EXISTS `trainers`;
DROP TABLE IF EXISTS `members`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------
-- 1. Table structure for table `users`
-- --------------------------------------------------------
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'member') NOT NULL DEFAULT 'member',
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_users_email` (`email`),
  INDEX `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 2. Table structure for table `members`
-- --------------------------------------------------------
CREATE TABLE `members` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `member_code` VARCHAR(30) NOT NULL UNIQUE,
  `full_name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(25) NOT NULL,
  `gender` ENUM('Male', 'Female', 'Other') NOT NULL,
  `dob` DATE DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `emergency_contact` VARCHAR(50) DEFAULT NULL,
  `blood_group` VARCHAR(10) DEFAULT NULL,
  `profile_image` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('active', 'inactive', 'expired') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_members_code` (`member_code`),
  INDEX `idx_members_email` (`email`),
  INDEX `idx_members_status` (`status`),
  CONSTRAINT `fk_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 3. Table structure for table `trainers`
-- --------------------------------------------------------
CREATE TABLE `trainers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `phone` VARCHAR(25) NOT NULL,
  `gender` ENUM('Male', 'Female', 'Other') NOT NULL,
  `specialization` VARCHAR(150) NOT NULL,
  `experience_years` INT UNSIGNED NOT NULL DEFAULT 1,
  `salary` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `bio` TEXT DEFAULT NULL,
  `profile_image` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_trainers_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 4. Table structure for table `membership_plans`
-- --------------------------------------------------------
CREATE TABLE `membership_plans` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `plan_name` VARCHAR(100) NOT NULL,
  `duration_days` INT UNSIGNED NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_plans_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 5. Table structure for table `memberships`
-- --------------------------------------------------------
CREATE TABLE `memberships` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_id` INT UNSIGNED NOT NULL,
  `plan_id` INT UNSIGNED NOT NULL,
  `start_date` DATE NOT NULL,
  `expiry_date` DATE NOT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `paid_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('active', 'expired', 'pending', 'cancelled') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_memberships_member` (`member_id`),
  INDEX `idx_memberships_dates` (`start_date`, `expiry_date`),
  INDEX `idx_memberships_status` (`status`),
  CONSTRAINT `fk_memberships_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_memberships_plan` FOREIGN KEY (`plan_id`) REFERENCES `membership_plans` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 6. Table structure for table `attendance`
-- --------------------------------------------------------
CREATE TABLE `attendance` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_id` INT UNSIGNED NOT NULL,
  `attendance_date` DATE NOT NULL,
  `check_in_time` TIME NOT NULL,
  `check_out_time` TIME DEFAULT NULL,
  `notes` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_member_attendance_date` (`member_id`, `attendance_date`),
  INDEX `idx_attendance_date` (`attendance_date`),
  CONSTRAINT `fk_attendance_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 7. Table structure for table `payments`
-- --------------------------------------------------------
CREATE TABLE `payments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_id` INT UNSIGNED NOT NULL,
  `membership_id` INT UNSIGNED DEFAULT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `payment_date` DATE NOT NULL,
  `payment_method` ENUM('Cash', 'UPI', 'Card', 'Bank Transfer') NOT NULL DEFAULT 'Cash',
  `transaction_ref` VARCHAR(100) DEFAULT NULL,
  `notes` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('completed', 'pending', 'failed') NOT NULL DEFAULT 'completed',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_payments_member` (`member_id`),
  INDEX `idx_payments_date` (`payment_date`),
  INDEX `idx_payments_method` (`payment_method`),
  CONSTRAINT `fk_payments_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_payments_membership` FOREIGN KEY (`membership_id`) REFERENCES `memberships` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 8. Table structure for table `workout_plans`
-- --------------------------------------------------------
CREATE TABLE `workout_plans` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `trainer_id` INT UNSIGNED DEFAULT NULL,
  `plan_name` VARCHAR(120) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `difficulty_level` ENUM('Beginner', 'Intermediate', 'Advanced') NOT NULL DEFAULT 'Beginner',
  `goal` ENUM('Muscle Building', 'Weight Loss', 'Endurance', 'Flexibility', 'General Fitness') NOT NULL DEFAULT 'General Fitness',
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_workout_plans_trainer` (`trainer_id`),
  CONSTRAINT `fk_workout_plans_trainer` FOREIGN KEY (`trainer_id`) REFERENCES `trainers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 9. Table structure for table `workout_exercises`
-- --------------------------------------------------------
CREATE TABLE `workout_exercises` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workout_plan_id` INT UNSIGNED NOT NULL,
  `day_of_week` ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL DEFAULT 'Monday',
  `exercise_name` VARCHAR(120) NOT NULL,
  `sets` INT UNSIGNED NOT NULL DEFAULT 3,
  `reps` VARCHAR(50) NOT NULL DEFAULT '10-12',
  `rest_seconds` INT UNSIGNED NOT NULL DEFAULT 60,
  `instructions` TEXT DEFAULT NULL,
  `display_order` INT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  INDEX `idx_exercises_plan` (`workout_plan_id`),
  INDEX `idx_exercises_day` (`day_of_week`),
  CONSTRAINT `fk_workout_exercises_plan` FOREIGN KEY (`workout_plan_id`) REFERENCES `workout_plans` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 10. Table structure for table `member_workouts`
-- --------------------------------------------------------
CREATE TABLE `member_workouts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_id` INT UNSIGNED NOT NULL,
  `workout_plan_id` INT UNSIGNED NOT NULL,
  `assigned_date` DATE NOT NULL,
  `status` ENUM('active', 'completed', 'inactive') NOT NULL DEFAULT 'active',
  `notes` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_member_workouts_member` (`member_id`),
  INDEX `idx_member_workouts_plan` (`workout_plan_id`),
  CONSTRAINT `fk_member_workouts_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_member_workouts_plan` FOREIGN KEY (`workout_plan_id`) REFERENCES `workout_plans` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- SAMPLE SEED DATA
-- Passwords:
-- Admin: Admin@123 -> $2y$10$.q8nw3FDariWS8rULTPg2u0h3Ncry5/nKbR4ILBH8D9.mXzMS.ooO
-- Member: Member@123 -> $2y$10$QQWVPhkfZenZNza7b7U9/.rT5aOvYgk//60PVZFzDAzoAUdIygfZW
-- =======================================================

-- Seed Users
INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `status`) VALUES
(1, 'System Administrator', 'admin@gym.com', '$2y$10$.q8nw3FDariWS8rULTPg2u0h3Ncry5/nKbR4ILBH8D9.mXzMS.ooO', 'admin', 'active'),
(2, 'Alex Carter', 'alex@example.com', '$2y$10$QQWVPhkfZenZNza7b7U9/.rT5aOvYgk//60PVZFzDAzoAUdIygfZW', 'member', 'active'),
(3, 'Sophia Patel', 'sophia@example.com', '$2y$10$QQWVPhkfZenZNza7b7U9/.rT5aOvYgk//60PVZFzDAzoAUdIygfZW', 'member', 'active'),
(4, 'David Miller', 'david@example.com', '$2y$10$QQWVPhkfZenZNza7b7U9/.rT5aOvYgk//60PVZFzDAzoAUdIygfZW', 'member', 'active');

-- Seed Membership Plans
INSERT INTO `membership_plans` (`id`, `plan_name`, `duration_days`, `price`, `description`, `status`) VALUES
(1, 'Monthly Starter', 30, 49.99, 'Access to gym equipment, locker room, and free fitness assessment.', 'active'),
(2, 'Quarterly Pro', 90, 129.99, 'Full gym access, 2 personal trainer sessions, nutrition advice.', 'active'),
(3, 'Half-Yearly Elite', 180, 239.99, 'All-access pass, steam room, sauna, 5 trainer sessions, guest passes.', 'active'),
(4, 'Yearly Champion', 365, 429.99, 'VIP access, unlimited classes, personal coach, free gym kit, locker.', 'active');

-- Seed Trainers
INSERT INTO `trainers` (`id`, `name`, `email`, `phone`, `gender`, `specialization`, `experience_years`, `salary`, `status`, `bio`) VALUES
(1, 'Marcus Steel', 'marcus@ironpulse.com', '+1 (555) 234-5678', 'Male', 'Strength & Hypertrophy', 8, 4500.00, 'active', 'Former powerlifter specializing in compound strength, biomechanics, and muscle building.'),
(2, 'Elena Rostova', 'elena@ironpulse.com', '+1 (555) 345-6789', 'Female', 'HIIT & Functional Conditioning', 6, 4200.00, 'active', 'Certified functional fitness and cardio coach focusing on high intensity endurance and fat loss.'),
(3, 'Kenji Sato', 'kenji@ironpulse.com', '+1 (555) 456-7890', 'Male', 'Mobility & Athletic Performance', 5, 3900.00, 'active', 'Specializes in injury prevention, mobility work, posture alignment, and calisthenics.');

-- Seed Members
INSERT INTO `members` (`id`, `user_id`, `member_code`, `full_name`, `email`, `phone`, `gender`, `dob`, `address`, `emergency_contact`, `blood_group`, `status`) VALUES
(1, 2, 'MBR-1001', 'Alex Carter', 'alex@example.com', '+1 (555) 111-2233', 'Male', '1995-04-12', '424 Elm Street, Downtown', 'Sarah Carter (+1 555-111-9988)', 'O+', 'active'),
(2, 3, 'MBR-1002', 'Sophia Patel', 'sophia@example.com', '+1 (555) 222-3344', 'Female', '1998-09-24', '789 Sunset Blvd, Westside', 'Raj Patel (+1 555-222-8877)', 'B+', 'active'),
(3, 4, 'MBR-1003', 'David Miller', 'david@example.com', '+1 (555) 333-4455', 'Male', '1990-11-05', '12 Lakeview Avenue, Northside', 'Emma Miller (+1 555-333-7766)', 'A-', 'active');

-- Seed Memberships
INSERT INTO `memberships` (`id`, `member_id`, `plan_id`, `start_date`, `expiry_date`, `total_amount`, `paid_amount`, `status`) VALUES
(1, 1, 4, CURRENT_DATE - INTERVAL 30 DAY, CURRENT_DATE + INTERVAL 335 DAY, 429.99, 429.99, 'active'),
(2, 2, 2, CURRENT_DATE - INTERVAL 45 DAY, CURRENT_DATE + INTERVAL 45 DAY, 129.99, 129.99, 'active'),
(3, 3, 1, CURRENT_DATE - INTERVAL 25 DAY, CURRENT_DATE + INTERVAL 5 DAY, 49.99, 49.99, 'active');

-- Seed Payments
INSERT INTO `payments` (`id`, `member_id`, `membership_id`, `amount`, `payment_date`, `payment_method`, `transaction_ref`, `notes`, `status`) VALUES
(1, 1, 1, 429.99, CURRENT_DATE - INTERVAL 30 DAY, 'Card', 'TXN-CARD-90812', 'Yearly Champion membership full payment', 'completed'),
(2, 2, 2, 129.99, CURRENT_DATE - INTERVAL 45 DAY, 'UPI', 'UPI-REF-44219', 'Quarterly Pro subscription via UPI', 'completed'),
(3, 3, 3, 49.99, CURRENT_DATE - INTERVAL 25 DAY, 'Cash', 'CASH-REC-0012', 'Monthly starter fee paid at reception', 'completed');

-- Seed Attendance (including today and previous days)
INSERT INTO `attendance` (`id`, `member_id`, `attendance_date`, `check_in_time`, `check_out_time`, `notes`) VALUES
(1, 1, CURRENT_DATE, '07:15:00', '08:45:00', 'Morning strength workout'),
(2, 2, CURRENT_DATE, '08:30:00', '09:30:00', 'Cardio and core session'),
(3, 1, CURRENT_DATE - INTERVAL 1 DAY, '07:20:00', '08:50:00', 'Chest and Triceps routine'),
(4, 2, CURRENT_DATE - INTERVAL 1 DAY, '17:45:00', '19:00:00', 'HIIT class'),
(5, 3, CURRENT_DATE - INTERVAL 1 DAY, '18:10:00', '19:25:00', 'Upper body session'),
(6, 1, CURRENT_DATE - INTERVAL 2 DAY, '07:05:00', '08:35:00', 'Leg day'),
(7, 3, CURRENT_DATE - INTERVAL 3 DAY, '18:00:00', '19:15:00', 'Back and Biceps');

-- Seed Workout Plans
INSERT INTO `workout_plans` (`id`, `trainer_id`, `plan_name`, `description`, `difficulty_level`, `goal`, `status`) VALUES
(1, 1, 'Iron Mass Hypertrophy', 'A focused 4-day bodybuilding split targeting maximum hypertrophy and progressive overload.', 'Intermediate', 'Muscle Building', 'active'),
(2, 2, 'Blaze HIIT & Fat Burn', 'High intensity interval conditioning designed to boost metabolic rate and strip body fat.', 'Beginner', 'Weight Loss', 'active'),
(3, 3, 'Athletic Mobility & Power', 'Explosive power, joint resilience, and functional stability for peak athletic performance.', 'Advanced', 'Endurance', 'active');

-- Seed Workout Exercises for Plan 1 (Iron Mass Hypertrophy)
INSERT INTO `workout_exercises` (`workout_plan_id`, `day_of_week`, `exercise_name`, `sets`, `reps`, `rest_seconds`, `instructions`, `display_order`) VALUES
(1, 'Monday', 'Barbell Bench Press', 4, '8-10', 90, 'Focus on controlled eccentric descent, pause briefly on chest, drive up explosively.', 1),
(1, 'Monday', 'Incline Dumbbell Press', 3, '10-12', 75, 'Set bench to 30 degrees. Squeeze upper pectorals at the top.', 2),
(1, 'Monday', 'Dumbbell Lateral Raises', 4, '12-15', 60, 'Slight forward lean, raise elbows to shoulder height.', 3),
(1, 'Monday', 'Tricep Rope Pushdowns', 3, '12-15', 60, 'Keep elbows pinned to ribs, flare ropes at full contraction.', 4),
(1, 'Wednesday', 'Barbell Deadlifts', 4, '6-8', 120, 'Hinge at hips, keep spine neutral, push the floor away.', 1),
(1, 'Wednesday', 'Lat Pulldowns (Wide Grip)', 4, '10-12', 75, 'Pull elbows towards back pockets, squeeze lats.', 2),
(1, 'Wednesday', 'Seated Cable Rows', 3, '10-12', 60, 'Maintain chest up, retract scapulae on contraction.', 3),
(1, 'Wednesday', 'Barbell Bicep Curls', 3, '10-12', 60, 'No swinging, strict curl with progressive resistance.', 4),
(1, 'Friday', 'Barbell Back Squats', 4, '8-10', 120, 'Hit parallel or deeper, keep knees tracking over toes.', 1),
(1, 'Friday', 'Romanian Deadlifts (RDL)', 3, '10-12', 90, 'Feel deep hamstring stretch, hips pushed back.', 2),
(1, 'Friday', 'Leg Press', 3, '12-15', 75, 'Deep range of motion, avoid locking knees at top.', 3),
(1, 'Friday', 'Standing Calf Raises', 4, '15-20', 45, 'Full extension at top, slow 2-second stretch at bottom.', 4);

-- Seed Workout Exercises for Plan 2 (Blaze HIIT)
INSERT INTO `workout_exercises` (`workout_plan_id`, `day_of_week`, `exercise_name`, `sets`, `reps`, `rest_seconds`, `instructions`, `display_order`) VALUES
(2, 'Tuesday', 'Kettlebell Swings', 4, '20', 45, 'Explosive hip snap, maintain strong core.', 1),
(2, 'Tuesday', 'Burpees Over Bar', 4, '15', 45, 'Chest to deck, jump over barbell with rhythm.', 2),
(2, 'Tuesday', 'Box Jumps', 4, '12', 45, 'Land softly with bent knees, step down.', 3),
(2, 'Tuesday', 'Plank Hold', 3, '60 sec', 30, 'Full body tension, glutes and abs tight.', 4),
(2, 'Thursday', 'Battle Ropes Waves', 4, '30 sec', 45, 'High speed alternating waves in athletic stance.', 1),
(2, 'Thursday', 'Rowing Machine Sprint', 4, '250m', 60, 'Maximum power strokes at high damper setting.', 2),
(2, 'Thursday', 'Mountain Climbers', 4, '40 sec', 45, 'Fast knees to chest, keep hips level.', 3);

-- Assign Workouts to Members
INSERT INTO `member_workouts` (`id`, `member_id`, `workout_plan_id`, `assigned_date`, `status`, `notes`) VALUES
(1, 1, 1, CURRENT_DATE - INTERVAL 30 DAY, 'active', 'Focus on progressive overload on bench press and squats.'),
(2, 2, 2, CURRENT_DATE - INTERVAL 45 DAY, 'active', 'Hydrate well and maintain high intensity on intervals.');
