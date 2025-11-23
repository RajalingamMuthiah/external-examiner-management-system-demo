-- HOD & VP extension tables for availability and nominations

-- faculty_availability: records when faculty mark themselves unavailable
CREATE TABLE IF NOT EXISTS `faculty_availability` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `faculty_id` INT NOT NULL,
  `unavailable_date` DATE NOT NULL,
  `created_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  KEY (`faculty_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- examiner_nominations: nominations submitted by HODs for examiners
CREATE TABLE IF NOT EXISTS `examiner_nominations` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `dept` VARCHAR(128) DEFAULT NULL,
  `examiner_name` VARCHAR(255) NOT NULL,
  `role` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_by` INT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY (`dept`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- external_examiners: optional table for storing external examiner profiles
CREATE TABLE IF NOT EXISTS `external_examiners` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `expertise` VARCHAR(255) DEFAULT NULL,
  `dept` VARCHAR(128) DEFAULT NULL,
  `availability` DATE DEFAULT NULL,
  `past_assignments` INT DEFAULT 0,
  `status` ENUM('pending','confirmed','declined') DEFAULT 'pending',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- examiner_requests: HOD -> VP requests (if not present already)
CREATE TABLE IF NOT EXISTS `examiner_requests` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `hod_name` VARCHAR(255) DEFAULT NULL,
  `examiner` VARCHAR(255) DEFAULT NULL,
  `purpose` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('pending','approved','rejected') DEFAULT 'pending',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Notes: run this file (e.g., via mysql client) to add required tables for HOD features.
