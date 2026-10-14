-- Database setup script for Binamira Clinic
CREATE DATABASE IF NOT EXISTS `binamira_clinic` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `binamira_clinic`;

-- Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `UserID` INT AUTO_INCREMENT PRIMARY KEY,
  `Role` ENUM('doctor', 'nurse', 'admin', 'staff') NOT NULL,
  `Name` VARCHAR(100) NOT NULL,
  `Email` VARCHAR(100) NOT NULL UNIQUE,
  `Password` VARCHAR(255) NOT NULL,
  `Phone` VARCHAR(50) DEFAULT NULL,
  `ProfileImage` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Doctors Table
CREATE TABLE IF NOT EXISTS `doctors` (
  `DoctorID` INT PRIMARY KEY,
  `Specialty` VARCHAR(100) DEFAULT NULL,
  FOREIGN KEY (`DoctorID`) REFERENCES `users`(`UserID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Nurses Table
CREATE TABLE IF NOT EXISTS `nurses` (
  `NurseID` INT PRIMARY KEY,
  `Department` VARCHAR(100) DEFAULT NULL,
  FOREIGN KEY (`NurseID`) REFERENCES `users`(`UserID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Patients Table
CREATE TABLE IF NOT EXISTS `patients` (
  `PatientID` INT AUTO_INCREMENT PRIMARY KEY,
  `Name` VARCHAR(100) NOT NULL,
  `Gender` VARCHAR(20) NOT NULL,
  `BirthDate` DATE NOT NULL,
  `Age` INT NOT NULL,
  `Phone` VARCHAR(50) DEFAULT NULL,
  `Email` VARCHAR(100) UNIQUE,
  `Address` VARCHAR(255) DEFAULT NULL,
  `CreatedAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Appointments Table
CREATE TABLE IF NOT EXISTS `appointments` (
  `AppointmentID` INT AUTO_INCREMENT PRIMARY KEY,
  `PatientID` INT NOT NULL,
  `DoctorID` INT DEFAULT NULL,
  `Branch` VARCHAR(100) DEFAULT 'Main Branch',
  `ServiceType` VARCHAR(100) DEFAULT 'General Consultation',
  `AppointmentDate` DATE NOT NULL,
  `AppointmentTime` TIME NOT NULL,
  `Reason` TEXT DEFAULT NULL,
  `Status` VARCHAR(50) DEFAULT 'Pending',
  `CreatedAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`PatientID`) REFERENCES `patients`(`PatientID`) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (`DoctorID`) REFERENCES `users`(`UserID`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Medicines Master Table
CREATE TABLE IF NOT EXISTS `medicines` (
  `MedicineID` INT AUTO_INCREMENT PRIMARY KEY,
  `Name` VARCHAR(100) NOT NULL,
  `GenericName` VARCHAR(100) DEFAULT NULL,
  `Category` VARCHAR(100) DEFAULT NULL,
  `Description` TEXT DEFAULT NULL,
  `DosageForm` VARCHAR(50) DEFAULT NULL,
  `Strength` VARCHAR(50) DEFAULT NULL,
  `Manufacturer` VARCHAR(100) DEFAULT NULL,
  `CreatedAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Prescriptions Table
CREATE TABLE IF NOT EXISTS `prescriptions` (
  `PrescriptionID` INT AUTO_INCREMENT PRIMARY KEY,
  `AppointmentID` INT NOT NULL,
  `DoctorID` INT NOT NULL,
  `PatientName` VARCHAR(100) DEFAULT NULL,
  `DateIssued` DATE NOT NULL,
  `Instructions` TEXT DEFAULT NULL,
  FOREIGN KEY (`AppointmentID`) REFERENCES `appointments`(`AppointmentID`) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (`DoctorID`) REFERENCES `users`(`UserID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Prescription Medications Table
CREATE TABLE IF NOT EXISTS `prescription_medications` (
  `MedicationID` INT AUTO_INCREMENT PRIMARY KEY,
  `PrescriptionID` INT NOT NULL,
  `MedicineID` INT DEFAULT NULL,
  `MedicationName` VARCHAR(100) NOT NULL,
  `Dosage` VARCHAR(100) DEFAULT NULL,
  `Frequency` VARCHAR(100) DEFAULT NULL,
  `Duration` VARCHAR(100) DEFAULT NULL,
  `Instructions` TEXT DEFAULT NULL,
  FOREIGN KEY (`PrescriptionID`) REFERENCES `prescriptions`(`PrescriptionID`) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (`MedicineID`) REFERENCES `medicines`(`MedicineID`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Prescription Investigations Table
CREATE TABLE IF NOT EXISTS `prescription_investigations` (
  `InvestigationID` INT AUTO_INCREMENT PRIMARY KEY,
  `PrescriptionID` INT NOT NULL,
  `TestName` VARCHAR(100) NOT NULL,
  `Instructions` TEXT DEFAULT NULL,
  FOREIGN KEY (`PrescriptionID`) REFERENCES `prescriptions`(`PrescriptionID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Medical Records Table
CREATE TABLE IF NOT EXISTS `medical_records` (
  `RecordID` INT AUTO_INCREMENT PRIMARY KEY,
  `PatientID` INT NOT NULL,
  `DoctorID` INT NOT NULL,
  `RecordDate` DATE NOT NULL,
  `RecordType` VARCHAR(50) DEFAULT 'General Consultation',
  `Diagnosis` TEXT DEFAULT NULL,
  `Treatment` TEXT DEFAULT NULL,
  `Notes` TEXT DEFAULT NULL,
  `CreatedAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`PatientID`) REFERENCES `patients`(`PatientID`) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (`DoctorID`) REFERENCES `users`(`UserID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Billings Table
CREATE TABLE IF NOT EXISTS `billings` (
  `BillingID` INT AUTO_INCREMENT PRIMARY KEY,
  `AppointmentID` INT NOT NULL,
  `Amount` DECIMAL(10,2) NOT NULL DEFAULT 500.00,
  `BillingDate` DATE NOT NULL,
  `PaidStatus` VARCHAR(25) NOT NULL DEFAULT 'Pending',
  `PaymentMethod` VARCHAR(50) DEFAULT NULL,
  `PaymentDate` DATE DEFAULT NULL,
  `Notes` TEXT DEFAULT NULL,
  FOREIGN KEY (`AppointmentID`) REFERENCES `appointments`(`AppointmentID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Contact Messages Table
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `MessageID` INT AUTO_INCREMENT PRIMARY KEY,
  `FullName` VARCHAR(100) NOT NULL,
  `Phone` VARCHAR(50) NOT NULL,
  `Email` VARCHAR(100) NOT NULL,
  `Subject` VARCHAR(255) NOT NULL,
  `Message` TEXT NOT NULL,
  `DateSent` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =======================================================
-- Seed Initial Demo Data (password: password)
-- =======================================================

-- Users (Doctor & Nurse)
INSERT INTO `users` (`UserID`, `Role`, `Name`, `Email`, `Password`, `Phone`, `is_active`) VALUES
(1, 'doctor', 'Dr. Roberto Binamira', 'doctor@binamira.com', '$2y$10$YQ2R5dR5Hqx0neXjIO3VTOe31Cn/3YjKSsMD97s/dfxJJxonngsRu', '09088762024', 1),
(2, 'nurse', 'Nurse Maria Santos', 'nurse@binamira.com', '$2y$10$YQ2R5dR5Hqx0neXjIO3VTOe31Cn/3YjKSsMD97s/dfxJJxonngsRu', '09171234567', 1)
ON DUPLICATE KEY UPDATE `Name`=VALUES(`Name`);

-- Doctor Profile
INSERT INTO `doctors` (`DoctorID`, `Specialty`) VALUES
(1, 'General Medicine & Cardiology')
ON DUPLICATE KEY UPDATE `Specialty`=VALUES(`Specialty`);

-- Nurse Profile
INSERT INTO `nurses` (`NurseID`, `Department`) VALUES
(2, 'Outpatient Department')
ON DUPLICATE KEY UPDATE `Department`=VALUES(`Department`);

-- Patients
INSERT INTO `patients` (`PatientID`, `Name`, `Gender`, `BirthDate`, `Age`, `Phone`, `Email`, `Address`) VALUES
(1, 'Juan Dela Cruz', 'Male', '1990-05-15', 35, '09181234567', 'juan.delacruz@example.com', 'Bacacay, Albay'),
(2, 'Ana Marie Reyes', 'Female', '1998-11-20', 27, '09289876543', 'ana.reyes@example.com', 'Legazpi City, Albay')
ON DUPLICATE KEY UPDATE `Name`=VALUES(`Name`);

-- Medicines
INSERT INTO `medicines` (`MedicineID`, `Name`, `GenericName`, `Category`, `Description`, `DosageForm`, `Strength`, `Manufacturer`) VALUES
(1, 'Biogesic', 'Paracetamol', 'Analgesic / Antipyretic', 'Used for relief of mild to moderate pain and fever.', 'Tablet', '500mg', 'Unilab'),
(2, 'Amoxil', 'Amoxicillin', 'Antibiotic', 'Used for bacterial infections.', 'Capsule', '500mg', 'GlaxoSmithKline'),
(3, 'Neozep Forte', 'Phenylephrine HCl + Chlorphenamine Maleate + Paracetamol', 'Cold & Flu', 'Complete relief from cold symptoms and fever.', 'Tablet', '10mg/2mg/500mg', 'Unilab'),
(4, 'Alaxan FR', 'Ibuprofen + Paracetamol', 'Analgesic', 'Fast relief for muscle ache, joint pain, and headache.', 'Capsule', '200mg/325mg', 'Unilab')
ON DUPLICATE KEY UPDATE `Name`=VALUES(`Name`);

-- Appointments
INSERT INTO `appointments` (`AppointmentID`, `PatientID`, `DoctorID`, `Branch`, `ServiceType`, `AppointmentDate`, `AppointmentTime`, `Reason`, `Status`) VALUES
(1, 1, 1, 'Main Branch', 'General Consultation', CURDATE(), '09:30:00', 'Routine general checkup and blood pressure check', 'Confirmed'),
(2, 2, 1, 'Main Branch', 'Specialized Care', CURDATE(), '10:30:00', 'Chest tightness and consultation', 'Pending')
ON DUPLICATE KEY UPDATE `Status`=VALUES(`Status`);

-- Billings
INSERT INTO `billings` (`BillingID`, `AppointmentID`, `Amount`, `BillingDate`, `PaidStatus`, `PaymentMethod`, `PaymentDate`, `Notes`) VALUES
(1, 1, 650.00, CURDATE(), 'Paid', 'Cash', CURDATE(), 'Consultation fee paid in full')
ON DUPLICATE KEY UPDATE `Amount`=VALUES(`Amount`);

-- Medical Records
INSERT INTO `medical_records` (`RecordID`, `PatientID`, `DoctorID`, `RecordDate`, `RecordType`, `Diagnosis`, `Treatment`, `Notes`) VALUES
(1, 1, 1, CURDATE(), 'General Consultation', 'Mild hypertension stage 1', 'Lifestyle modification, low sodium diet, follow-up in 2 weeks', 'Patient advised to monitor daily BP.')
ON DUPLICATE KEY UPDATE `Diagnosis`=VALUES(`Diagnosis`);

-- Contact Messages
INSERT INTO `contact_messages` (`MessageID`, `FullName`, `Phone`, `Email`, `Subject`, `Message`) VALUES
(1, 'Carlos Mendoza', '09193334444', 'carlos@example.com', 'Inquiry on Laboratory Testing', 'Good day, do you have full blood chemistry tests available on Saturdays?')
ON DUPLICATE KEY UPDATE `Subject`=VALUES(`Subject`);
