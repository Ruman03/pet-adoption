-- Users Table
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL, -- Store hashed passwords
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `full_name` VARCHAR(100),
  `role` ENUM('adopter', 'staff', 'admin', 'vet', 'volunteer') NOT NULL DEFAULT 'adopter',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Pets Table
CREATE TABLE `pets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `species` VARCHAR(50),
  `breed` VARCHAR(50),
  `age` INT,
  `gender` ENUM('male', 'female', 'unknown') DEFAULT 'unknown',
  `description` TEXT,
  `status` ENUM('available', 'adopted', 'fostered', 'pending') DEFAULT 'available',
  `shelter_id` INT, -- Foreign key to a potential shelters table
  `added_by_staff_id` INT, -- Foreign key to users table (staff)
  `added_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `image_url` VARCHAR(255), -- URL or path to pet image
  FOREIGN KEY (`added_by_staff_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
  -- If you have a shelters table:
  -- FOREIGN KEY (`shelter_id`) REFERENCES `shelters`(`id`) ON DELETE SET NULL
);

-- Applications Table
CREATE TABLE `applications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL, -- Adopter
  `pet_id` INT NOT NULL,
  `status` ENUM('pending', 'approved', 'rejected', 'withdrawn') DEFAULT 'pending',
  `application_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `notes` TEXT,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`pet_id`) REFERENCES `pets`(`id`) ON DELETE CASCADE
);

-- Medical Records Table
CREATE TABLE `medical_records` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pet_id` INT NOT NULL,
  `vet_id` INT, -- User with 'vet' role
  `record_date` DATE NOT NULL,
  `condition_notes` TEXT,
  `treatment_given` TEXT,
  `vaccinations` TEXT, -- Could be a JSON or a separate table for more detail
  `next_checkup_date` DATE,
  FOREIGN KEY (`pet_id`) REFERENCES `pets`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`vet_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

-- Foster Records Table
CREATE TABLE `foster_records` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pet_id` INT NOT NULL,
  `foster_parent_id` INT NOT NULL, -- User with 'adopter' or a specific 'foster' role
  `start_date` DATE NOT NULL,
  `end_date` DATE,
  `notes` TEXT,
  FOREIGN KEY (`pet_id`) REFERENCES `pets`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`foster_parent_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- Volunteer Tasks Table
CREATE TABLE `volunteer_tasks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_name` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `assigned_to_volunteer_id` INT,
  `status` ENUM('open', 'assigned', 'in_progress', 'completed') DEFAULT 'open',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `due_date` DATE,
  FOREIGN KEY (`assigned_to_volunteer_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

-- Shelters Table (Optional, if you manage multiple shelters)
CREATE TABLE `shelters` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `address` VARCHAR(255),
  `contact_info` VARCHAR(100)
);

-- You might also need tables for:
-- Volunteer Schedules, Training Sessions, Supply Requests (for fosters), Reports data, etc.
-- This schema is a starting point.
