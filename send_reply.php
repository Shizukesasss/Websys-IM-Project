<?php
session_start();
session_start();
require_once 'config.php';         // Load constants and configs
require_once 'db_connect.php';
require_once 'vendor/autoload.php';

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Check if user is logged in and is a doctor or nurse
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'doctor' && $_SESSION['role'] !== 'nurse')) {
    header("Location: login.php");
    exit;
}

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate and sanitize input
    if (
        !isset($_POST['email']) || 
        !isset($_POST['name']) || 
        !isset($_POST['reply']) || 
        empty($_POST['email']) || 
        empty($_POST['name']) || 
        empty($_POST['reply'])
    ) {
        $_SESSION['error_message'] = "All fields are required";
        header("Location: messages.php");
        exit;
    }

    $to_email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $recipient_name = htmlspecialchars($_POST['name']);
    $reply_message = htmlspecialchars($_POST['reply']);
    
    // Get staff information
    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    
    $stmt = $conn->prepare("SELECT Name FROM users WHERE UserID = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $staff = $result->fetch_assoc();
    $staff_name = $staff['Name'];
    
    // Email body HTML template
    $message = "
    <html>
    <head>
        <title>Reply from Bhamira Clinic</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #e63946; color: white; padding: 10px; text-align: center; }
            .content { padding: 20px; background-color: #f8f9fa; }
            .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Bhamira Clinic</h2>
            </div>
            <div class='content'>
                <p>Dear $recipient_name,</p>
                <p>Thank you for contacting Bhamira Clinic. In response to your inquiry:</p>
                <p>$reply_message</p>
                <p>If you have any further questions, please don't hesitate to contact us.</p>
                <p>Best regards,<br>
                $staff_name<br>
                " . ucfirst($role) . "<br>
                Bhamira Clinic</p>
            </div>
            <div class='footer'>
                <p>This is an automated message. Please do not reply directly to this email.</p>
                <p>&copy; " . date('Y') . " Bhamira Clinic. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Log the reply in the database
    $stmt = $conn->prepare("INSERT INTO message_replies (MessageEmail, StaffID, ReplyContent, ReplySent) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("sis", $to_email, $user_id, $reply_message);
    $db_success = $stmt->execute();
    
    // Create a new PHPMailer instance
    $mail = new PHPMailer(true);
    
    try {
        // SMTP configuration
        $mail->isSMTP();                              // Use SMTP protocol
        $mail->Host       = MAIL_HOST;                // Set SMTP server
        $mail->SMTPAuth   = true;                     // Enable SMTP authentication
        $mail->Username   = MAIL_USERNAME;            // SMTP username
        $mail->Password   = MAIL_PASSWORD;            // SMTP password
        $mail->SMTPSecure = MAIL_ENCRYPTION;          // Enable TLS encryption
        $mail->Port       = MAIL_PORT;                // TCP port to connect to
        
        // Email sender and recipient settings
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($to_email, $recipient_name);
        
        // Email content settings
        $mail->isHTML(true);
        $mail->Subject = 'Reply from Bhamira Clinic';
        $mail->Body    = $message;
        $mail->AltBody = strip_tags(str_replace(['<br>', '</p>'], ["\n", "\n\n"], $reply_message));
        
        // Send the email
        $mail->send();
        
        // Set success message
        $_SESSION['success_message'] = "Reply sent successfully to $recipient_name";
    } catch (Exception $e) {
        // Log the error (optional)
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        
        // Set error message for the user
        $_SESSION['error_message'] = "Failed to send email. Please try again later.";
    }
    
    // Redirect back to messages page
    header("Location: messages.php");
    exit;
} else {
    // If not POST request, redirect to messages page
    header("Location: messages.php");
    exit;
}
?>