<?php
/**
 * Configuration file for Binamira Clinic application
 * 
 * Supports environment variables for cloud deployment (e.g. Vercel)
 * with fallback to local defaults.
 */

// Email Configuration
define('MAIL_HOST', getenv('MAIL_HOST') ?: 'smtp.gmail.com');
define('MAIL_PORT', (int)(getenv('MAIL_PORT') ?: 587));
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: 'binamiramedicalclinic1@gmail.com');
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: 'twwromxunhsnxits');
define('MAIL_ENCRYPTION', getenv('MAIL_ENCRYPTION') ?: 'tls');
define('MAIL_FROM_ADDRESS', getenv('MAIL_FROM_ADDRESS') ?: 'binamiramedicalclinic1@gmail.com');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'Binamira Clinic');

// Application Settings
define('APP_NAME', 'Binamira Clinic');
define('APP_URL', getenv('APP_URL') ?: 'https://www.bhamiraclinic.com');
define('APP_DEBUG', getenv('APP_DEBUG') === 'true');

// Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: (getenv('MYSQLHOST') ?: 'localhost'));
define('DB_USER', getenv('DB_USER') ?: (getenv('MYSQLUSER') ?: 'root'));
define('DB_PASS', getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : (getenv('DB_PASS') !== false ? getenv('DB_PASS') : (getenv('MYSQLPASSWORD') !== false ? getenv('MYSQLPASSWORD') : '')));
define('DB_NAME', getenv('DB_NAME') ?: (getenv('MYSQLDATABASE') ?: 'binamira_clinic'));
define('DB_PORT', (int)(getenv('DB_PORT') ?: (getenv('MYSQLPORT') ?: 3306)));

// Timezone Setting
date_default_timezone_set('Asia/Manila');

// File Upload Settings
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']);