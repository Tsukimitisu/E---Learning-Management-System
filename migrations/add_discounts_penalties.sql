-- Migration: Add Discounts and Penalties tables
-- Date: 2026-03-01

-- Discounts: Reduce tuition fee. Has start and end dates (validity window).
CREATE TABLE IF NOT EXISTS `tuition_discounts` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL COMMENT 'e.g., Early Bird Discount, Scholarship Discount',
  `discount_type` enum('percentage','fixed') NOT NULL DEFAULT 'percentage' COMMENT 'percentage = % off tuition, fixed = flat amount off',
  `value` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Percentage (0-100) or fixed amount',
  `start_date` date NOT NULL COMMENT 'Discount validity start date',
  `end_date` date NOT NULL COMMENT 'Discount validity end date',
  `academic_year_id` int(10) UNSIGNED DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_discount_dates` (`start_date`, `end_date`),
  KEY `idx_discount_active` (`is_active`),
  KEY `idx_discount_ay` (`academic_year_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Penalties: Added to tuition fee. Has start date only (applies from that date onward).
CREATE TABLE IF NOT EXISTS `tuition_penalties` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL COMMENT 'e.g., Late Enrollment Penalty, Re-enrollment Fee',
  `penalty_type` enum('percentage','fixed') NOT NULL DEFAULT 'fixed' COMMENT 'percentage = % added to tuition, fixed = flat amount added',
  `value` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Percentage (0-100) or fixed amount',
  `start_date` date NOT NULL COMMENT 'Penalty applies from this date onward',
  `academic_year_id` int(10) UNSIGNED DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_penalty_start` (`start_date`),
  KEY `idx_penalty_active` (`is_active`),
  KEY `idx_penalty_ay` (`academic_year_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
