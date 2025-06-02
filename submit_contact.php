<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$host = "localhost";
$user = "root";
$pass = "";  // Your MySQL password here if you have one
$db = "binamira_clinic";

// Create connection
$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die(json_encode([
        'success' => false,
        'message' => "Database connection failed: " . $conn->connect_error
    ]));
}

// Get and sanitize form data
$full_name = $conn->real_escape_string($_POST['name'] ?? '');
$phone = $conn->real_escape_string($_POST['phone'] ?? '');
$email = $conn->real_escape_string($_POST['email'] ?? '');
$subject = $conn->real_escape_string($_POST['subject'] ?? '');
$message = $conn->real_escape_string($_POST['message'] ?? '');

// Validate inputs
$errors = [];
if (empty($full_name)) $errors[] = "Full name is required";
if (empty($phone)) $errors[] = "Phone number is required";
if (empty($email)) $errors[] = "Email address is required";
if (empty($subject)) $errors[] = "Subject is required";
if (empty($message)) $errors[] = "Message is required";

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Please enter a valid email address";
}

if (!preg_match('/^[0-9\-\+\(\)\s]{7,20}$/', $phone)) {
    $errors[] = "Please enter a valid phone number (digits and +-() only)";
}

if (!empty($errors)) {
    die(json_encode([
        'success' => false,
        'message' => implode("<br>", $errors)
    ]));
}

// Insert into database
$sql = "INSERT INTO contact_messages (FullName, Phone, Email, Subject, Message) VALUES (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die(json_encode([
        'success' => false,
        'message' => "Database error: " . $conn->error
    ]));
}

$stmt->bind_param("sssss", $full_name, $phone, $email, $subject, $message);

if ($stmt->execute()) {
    $response = [
        'success' => true,
        'message' => "Thank you for your message! We'll respond within 24 hours."
    ];
} else {
    $response = [
        'success' => false,
        'message' => "Failed to send message: " . $stmt->error
    ];
}

$stmt->close();
$conn->close();

header('Content-Type: application/json');
echo json_encode($response);
?>