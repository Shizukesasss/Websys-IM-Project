<?php
session_start();

// Hardcoded credentials (for demo only - use database in production)
$valid_username = "clinic_staff";
$valid_password = "secure123";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = $_POST["username"] ?? "";
    $password = $_POST["password"] ?? "";

    if ($username === $valid_username && $password === $valid_password) {
        // Set a session flag (not a full role)
        $_SESSION["is_clinic_staff"] = true;
        header("Location: login.php"); // Redirect to doctor/nurse login
        exit();
    } else {
        $_SESSION['employee_login_error'] = "Invalid credentials";
        header("Location: employee_access.php");
        exit();
    }
} else {
    // Block direct access
    header("Location: employee_access.php");
    exit();
}
?>