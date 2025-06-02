<?php
session_start();
require_once __DIR__ . '/db_connect.php';

// Clear any previous error
unset($_SESSION['login_error']);

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    $_SESSION['login_error'] = "Please fill in all fields.";
    header("Location: login.php");
    exit();
}

$sql = "SELECT UserID AS id, role, name, email, password FROM users WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $row = $result->fetch_assoc();

    if (password_verify($password, $row['password'])) {
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['name'] = $row['name'];
        $_SESSION['role'] = $row['role'];
        $_SESSION['employee_authenticated'] = true;
        
        // Clear any login error if successful
        unset($_SESSION['login_error']);
        header("Location: dashboard.php");
        exit();
    } else {
        $_SESSION['login_error'] = "Incorrect password. Please try again.";
    }
} else {
    $_SESSION['login_error'] = "No user found with that email.";
}

// Always redirect back to login page
header("Location: login.php");
exit();
?>