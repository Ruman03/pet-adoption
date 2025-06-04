-- Extended schema for Pet Adoption System

-- Users Table (already exists but needs some updates)
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `full_name` VARCHAR(100),
  `phone` VARCHAR(20),
  `address` TEXT,
  `profile_image` VARCHAR(255),
  `role` ENUM('adopter', 'staff', 'admin', 'vet', 'volunteer') NOT NULL DEFAULT 'adopter',
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Shelters Table
DROP TABLE IF EXISTS `shelters`;
CREATE TABLE `shelters` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `address` TEXT,
  `phone` VARCHAR(20),
  `email` VARCHAR(100),
  `website` VARCHAR(255),
  `operating_hours` VARCHAR(255),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Pets Table (updated)
DROP TABLE IF EXISTS `pets`;
CREATE TABLE `pets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `species` VARCHAR(50),
  `breed` VARCHAR(50),
  `age` INT,
  `gender` ENUM('male', 'female', 'unknown') DEFAULT 'unknown',
  `description` TEXT,
  `status` ENUM('available', 'adopted', 'fostered', 'pending') DEFAULT 'available',
  `shelter_id` INT,
  `added_by_staff_id` INT,
  `added_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `image_url` VARCHAR(255),
  FOREIGN KEY (`added_by_staff_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`shelter_id`) REFERENCES `shelters`(`id`) ON DELETE SET NULL
);

-- Applications Table (updated)
DROP TABLE IF EXISTS `applications`;
CREATE TABLE `applications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `pet_id` INT NOT NULL,
  `status` ENUM('pending', 'approved', 'rejected', 'withdrawn') DEFAULT 'pending',
  `application_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `notes` TEXT,
  `approved_by` INT,
  `approved_at` TIMESTAMP NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`pet_id`) REFERENCES `pets`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

-- Medical Records Table (updated)
DROP TABLE IF EXISTS `medical_records`;
CREATE TABLE `medical_records` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pet_id` INT NOT NULL,
  `vet_id` INT,
  `record_date` DATE NOT NULL,
  `record_type` ENUM('vaccination', 'checkup', 'surgery', 'medication', 'other') DEFAULT 'checkup',
  `details` TEXT,
  `next_due_date` DATE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`pet_id`) REFERENCES `pets`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`vet_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

-- Foster Records Table (updated)
DROP TABLE IF EXISTS `foster_records`;
CREATE TABLE `foster_records` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pet_id` INT NOT NULL,
  `foster_parent_id` INT NOT NULL,
  `application_date` DATE NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE,
  `status` ENUM('pending', 'approved', 'rejected', 'active', 'completed', 'cancelled') DEFAULT 'pending',
  `notes` TEXT,
  `approved_by` INT,
  `approved_at` TIMESTAMP NULL,
  FOREIGN KEY (`pet_id`) REFERENCES `pets`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`foster_parent_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

-- Volunteer Tasks Table (updated)
DROP TABLE IF EXISTS `volunteer_tasks`;
CREATE TABLE `volunteer_tasks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `shelter_id` INT,
  `required_skills` TEXT,
  `urgency` ENUM('low', 'medium', 'high') DEFAULT 'medium',
  `task_date` DATETIME,
  `status` ENUM('open', 'assigned', 'in_progress', 'completed', 'cancelled') DEFAULT 'open',
  `created_by` INT,
  `assigned_to` INT,
  `assigned_at` TIMESTAMP NULL,
  `completed_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`shelter_id`) REFERENCES `shelters`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

-- Volunteer Applications Table
DROP TABLE IF EXISTS `volunteer_applications`;
CREATE TABLE `volunteer_applications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `application_type` ENUM('animal_care', 'event_support', 'administrative', 'transportation', 'other') NOT NULL,
  `availability` TEXT,
  `experience` TEXT,
  `skills` TEXT,
  `motivation` TEXT,
  `emergency_contact_name` VARCHAR(100),
  `emergency_contact_phone` VARCHAR(20),
  `status` ENUM('pending', 'approved', 'rejected', 'interview_scheduled') DEFAULT 'pending',
  `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `reviewed_by` INT,
  `reviewed_at` TIMESTAMP NULL,
  `notes` TEXT,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

-- Training Modules Table
DROP TABLE IF EXISTS `training_modules`;
CREATE TABLE `training_modules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `content` TEXT,
  `duration_minutes` INT,
  `required_for_roles` JSON, -- Store array of roles
  `is_mandatory` BOOLEAN DEFAULT FALSE,
  `created_by` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

-- User Training Progress Table
DROP TABLE IF EXISTS `user_training_progress`;
CREATE TABLE `user_training_progress` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `training_module_id` INT NOT NULL,
  `status` ENUM('not_started', 'in_progress', 'completed') DEFAULT 'not_started',
  `started_at` TIMESTAMP NULL,
  `completed_at` TIMESTAMP NULL,
  `score` DECIMAL(5,2), -- For assessments
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`training_module_id`) REFERENCES `training_modules`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `user_training_unique` (`user_id`, `training_module_id`)
);

-- Appointments/Schedules Table
DROP TABLE IF EXISTS `appointments`;
CREATE TABLE `appointments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `type` ENUM('vet_checkup', 'vaccination', 'surgery', 'grooming', 'adoption_meetup', 'volunteer_shift', 'other') NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `user_id` INT,
  `pet_id` INT,
  `shelter_id` INT,
  `scheduled_date` DATETIME NOT NULL,
  `duration_minutes` INT DEFAULT 60,
  `status` ENUM('scheduled', 'confirmed', 'completed', 'cancelled', 'no_show') DEFAULT 'scheduled',
  `notes` TEXT,
  `created_by` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`pet_id`) REFERENCES `pets`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`shelter_id`) REFERENCES `shelters`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

-- Supply Requests Table (for foster care)
DROP TABLE IF EXISTS `supply_requests`;
CREATE TABLE `supply_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `requester_id` INT NOT NULL,
  `pet_id` INT,
  `item_name` VARCHAR(255) NOT NULL,
  `quantity` INT NOT NULL,
  `urgency` ENUM('low', 'medium', 'high') DEFAULT 'medium',
  `description` TEXT,
  `status` ENUM('requested', 'approved', 'fulfilled', 'denied') DEFAULT 'requested',
  `requested_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `fulfilled_at` TIMESTAMP NULL,
  `notes` TEXT,
  FOREIGN KEY (`requester_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`pet_id`) REFERENCES `pets`(`id`) ON DELETE SET NULL
);

-- Favorites Table (for users to save favorite pets)
DROP TABLE IF EXISTS `favorites`;
CREATE TABLE `favorites` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `pet_id` INT NOT NULL,
  `added_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`pet_id`) REFERENCES `pets`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `user_pet_favorite` (`user_id`, `pet_id`)
);

-- Notifications Table
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `type` ENUM('application_update', 'appointment_reminder', 'task_assigned', 'general') NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` BOOLEAN DEFAULT FALSE,
  `related_id` INT, -- ID of related record (application, appointment, etc.)
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- Insert sample data
INSERT INTO `users` (`username`, `password`, `email`, `full_name`, `role`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@petadoption.com', 'System Administrator', 'admin'),
('john_staff', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'john@petadoption.com', 'John Smith', 'staff'),
('dr_sarah', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'sarah@petvet.com', 'Dr. Sarah Wilson', 'vet'),
('volunteer1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'volunteer@example.com', 'Jane Volunteer', 'volunteer');

INSERT INTO `shelters` (`name`, `address`, `phone`, `email`) VALUES
('Main Shelter', '123 Pet Street, Animal City, AC 12345', '(555) 123-4567', 'contact@mainshelter.org'),
('Rescue Center', '456 Rescue Ave, Pet Town, PT 67890', '(555) 987-6543', 'info@rescuecenter.org');
