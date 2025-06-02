<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$name = $_POST['name'];
$email = $_POST['email'];
$phone = $_POST['phone'] ?? null;

// Handle profile image upload
$profile_image = null;
if (!empty($_FILES['profile_image']['name'])) {
    $target_dir = "uploads/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    // Generate unique filename to prevent overwrites
    $file_extension = pathinfo($_FILES["profile_image"]["name"], PATHINFO_EXTENSION);
    $new_filename = uniqid() . '.' . $file_extension;
    $target_file = $target_dir . $new_filename;
    
    // Validate file type and size
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
    $max_size = 2 * 1024 * 1024; // 2MB
    
    if (in_array(strtolower($file_extension), $allowed_types) && 
        $_FILES["profile_image"]["size"] <= $max_size) {
        if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
            $profile_image = $target_file;
        } else {
            // Handle upload error
            $_SESSION['error'] = "Failed to upload image";
            header("Location: profile_settings.php");
            exit;
        }
    } else {
        $_SESSION['error'] = "Invalid file type or size (max 2MB)";
        header("Location: profile_settings.php");
        exit;
    }
}

try {
    // Update query
    $sql = "UPDATE users SET Name = ?, Email = ?, Phone = ?" . 
           ($profile_image ? ", ProfileImage = ?" : "") . 
           " WHERE UserID = ?";
    $stmt = $conn->prepare($sql);

    if ($profile_image) {
        $stmt->bind_param("ssssi", $name, $email, $phone, $profile_image, $user_id);
    } else {
        $stmt->bind_param("sssi", $name, $email, $phone, $user_id);
    }

    if ($stmt->execute()) {
        // Update session variables
        $_SESSION['name'] = $name;
        $_SESSION['email'] = $email;
        
        $_SESSION['success'] = "Profile updated successfully";
    } else {
        $_SESSION['error'] = "Error updating profile: " . $conn->error;
    }
    
    $stmt->close();
} catch (Exception $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
}

header("Location: dashboard.php");
exit;
?>