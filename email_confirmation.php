<?php
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
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['appointment_id'])) {
    // Get appointment details
    $appointment_id = intval($_POST['appointment_id']);
    
    $stmt = $conn->prepare("
        SELECT a.*, p.Name as PatientName, p.Email as PatientEmail, p.Phone,
               u.Name as DoctorName, u.Email as DoctorEmail
        FROM appointments a 
        JOIN patients p ON a.PatientID = p.PatientID 
        LEFT JOIN users u ON a.DoctorID = u.UserID 
        WHERE a.AppointmentID = ?
    ");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $appointment = $stmt->get_result()->fetch_assoc();
    
    if (!$appointment) {
        $_SESSION['error_message'] = "Appointment not found";
        header("Location: dashboard.php");
        exit;
    }

    // Format date and time
    $appointment_date = date('F j, Y', strtotime($appointment['AppointmentDate']));
    $appointment_time = date('h:i A', strtotime($appointment['AppointmentTime']));
    
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
        <title>Appointment Confirmation - Binamira Clinic</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #e63946; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background-color: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
            .appointment-card { background-color: white; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .detail-row { margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #eee; }
            .label { font-weight: bold; color: #e63946; display: inline-block; width: 150px; }
            .footer { margin-top: 30px; text-align: center; color: #666; font-size: 14px; }
            .status-badge { display: inline-block; padding: 5px 10px; border-radius: 20px; font-weight: bold; background-color: #4CAF50; color: white; }
            .reminder-box { background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Binamira Clinic</h1>
                <h2>Appointment Confirmation</h2>
            </div>
            
            <div class='content'>
                <p>Dear {$appointment['PatientName']},</p>
                
                <p>Thank you for choosing Binamira Clinic for your healthcare needs. We are pleased to confirm your upcoming appointment.</p>
                
                <div class='appointment-card'>
                    <h3 style='margin-top: 0; color: #e63946;'>Appointment Details</h3>
                    
                    <div class='detail-row'>
                        <span class='label'>Patient Name:</span>
                        {$appointment['PatientName']}
                    </div>
                    
                    <div class='detail-row'>
                        <span class='label'>Appointment Date:</span>
                        $appointment_date
                    </div>
                    
                    <div class='detail-row'>
                        <span class='label'>Appointment Time:</span>
                        $appointment_time
                    </div>
                    
                    <div class='detail-row'>
                        <span class='label'>Doctor:</span>
                        " . ($appointment['DoctorName'] ?? 'To be assigned') . "
                    </div>
                    
                    <div class='detail-row'>
                        <span class='label'>Branch:</span>
                        {$appointment['Branch']}
                    </div>
                    
                    <div class='detail-row'>
                        <span class='label'>Service Type:</span>
                        {$appointment['ServiceType']}
                    </div>
                    
                    <div class='detail-row'>
                        <span class='label'>Reason:</span>
                        {$appointment['Reason']}
                    </div>
                    
                    <div class='detail-row'>
                        <span class='label'>Status:</span>
                        <span class='status-badge'>CONFIRMED</span>
                    </div>
                </div>
                
                <div class='reminder-box'>
                    <h4 style='margin-top: 0; color: #856404;'>Important Reminders:</h4>
                    <ul style='color: #856404; padding-left: 20px;'>
                        <li>Please arrive 15 minutes before your scheduled appointment time</li>
                        <li>Bring your valid ID and any relevant medical documents</li>
                        <li>If you need to cancel or reschedule, please contact us at least 24 hours in advance</li>
                        <li>Wear a face mask when visiting the clinic</li>
                    </ul>
                </div>
                
                <p>Your booking is confirmed. Please visit Binamira Clinic at your scheduled time. We look forward to serving you.</p>
                
                <p>If you have any questions or need to make changes to your appointment, please contact us at:</p>
                <p>
                    <strong>Phone:</strong> (052) 480-1234<br>
                    <strong>Email:</strong> info@binamiraclinic.com<br>
                    <strong>Address:</strong> Binamira Clinic, Bacacay, Albay, Philippines
                </p>
                
                <div class='footer'>
                    <p>Best regards,<br>
                    $staff_name<br>
                    " . ucfirst($role) . "<br>
                    Binamira Clinic</p>
                    
                    <p><em>This is an automated message. Please do not reply directly to this email.</em></p>
                    <p>&copy; " . date('Y') . " Binamira Clinic. All rights reserved.</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Create a new PHPMailer instance
    $mail = new PHPMailer(true);
    
    try {
        // SMTP configuration
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        $mail->Port       = MAIL_PORT;
        
        // Email sender and recipient settings
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($appointment['PatientEmail'], $appointment['PatientName']);
        
        // Email content settings
        $mail->isHTML(true);
        $mail->Subject = 'Appointment Confirmation - Binamira Clinic';
        $mail->Body    = $message;
        
        // Plain text version for non-HTML email clients
        $mail->AltBody = "Dear {$appointment['PatientName']},\n\n" .
            "Your appointment at Binamira Clinic has been confirmed.\n\n" .
            "Appointment Details:\n" .
            "Date: $appointment_date\n" .
            "Time: $appointment_time\n" .
            "Branch: {$appointment['Branch']}\n" .
            "Service: {$appointment['ServiceType']}\n" .
            "Reason: {$appointment['Reason']}\n\n" .
            "Please arrive 15 minutes before your scheduled time.\n\n" .
            "Best regards,\n" .
            "$staff_name\n" .
            ucfirst($role) . "\n" .
            "Binamira Clinic";
        
        // Send the email
        $mail->send();
        
        // Update appointment status to Confirmed
        $update_stmt = $conn->prepare("UPDATE appointments SET Status = 'Confirmed' WHERE AppointmentID = ?");
        $update_stmt->bind_param("i", $appointment_id);
        $update_stmt->execute();
        
        $_SESSION['success_message'] = "Appointment confirmed and confirmation email sent to {$appointment['PatientName']}";
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        $_SESSION['error_message'] = "Failed to send confirmation email. Please try again later.";
    }
    
    header("Location: dashboard.php");
    exit;
} else {
    // If not POST request or missing appointment_id, redirect to dashboard
    header("Location: dashboard.php");
    exit;
}
?>