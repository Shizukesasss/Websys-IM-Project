<?php
/**
 * Configuration file for Bhamira Clinic application
 * 
 * This file contains configuration settings for the application,
 * including email settings, database connections, etc.
 */

// Email Configuration
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'binamiramedicalclinic1@gmail.com');
define('MAIL_PASSWORD', 'twwromxunhsnxits');  // no spaces!
define('MAIL_ENCRYPTION', 'tls');
define('MAIL_FROM_ADDRESS', 'binamiramedicalclinic1@gmail.com');
define('MAIL_FROM_NAME', 'Bhamira Clinic');


// Application Settings
define('APP_NAME', 'Bhamira Clinic');
define('APP_URL', 'https://www.bhamiraclinic.com'); // Replace with your actual URL
define('APP_DEBUG', false);                        // Set to true for development

// Database Configuration
// These should match your db_connect.php file
define('DB_HOST', 'localhost');
define('DB_USER', 'dbuser');      // Replace with your actual database username
define('DB_PASS', 'dbpassword');  // Replace with your actual database password
define('DB_NAME', 'clinic_db');   // Replace with your actual database name

// Timezone Setting
date_default_timezone_set('UTC'); // Adjust to your timezone if needed

// File Upload Settings
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']);