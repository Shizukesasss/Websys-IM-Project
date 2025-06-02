<?php
session_start();
require_once 'db_connect.php';

// Check user login
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['doctor', 'nurse'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get user info
if ($role === 'doctor') {
    $stmt = $conn->prepare("SELECT u.*, d.Specialty FROM users u JOIN doctors d ON u.UserID = d.DoctorID WHERE u.UserID = ?");
} else {
    $stmt = $conn->prepare("SELECT u.*, n.Department FROM users u JOIN nurses n ON u.UserID = n.NurseID WHERE u.UserID = ?");
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get appointment
if (!isset($_GET['id'])) {
    echo "Appointment ID missing.";
    exit;
}

$appointment_id = $_GET['id'];

$stmt = $conn->prepare("
    SELECT a.*, p.Name AS PatientName, p.Email, p.Phone, p.BirthDate, p.Gender, 
           u.Name AS DoctorName 
    FROM appointments a 
    JOIN patients p ON a.PatientID = p.PatientID 
    LEFT JOIN users u ON a.DoctorID = u.UserID 
    WHERE a.AppointmentID = ?
");
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "Appointment not found.";
    exit;
}

$appointment = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Appointment Details - Binamira Clinic</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link rel="stylesheet" href="dashboard.css">
    <style>
    body {
        font-family: 'Poppins', sans-serif;
        background: #f2f2f2;
        margin: 0;
        padding: 60px 20px;
    }

    .appointment-details {
        background: #fff;
        padding: 40px 50px;
        max-width: 900px; /* ✅ Increased width */
        margin: auto;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }

    h2 {
        margin-top: 0;
        font-weight: 600;
        color: #222;
        font-size: 28px;
        margin-bottom: 20px;
    }

    p {
        font-size: 18px;
        color: #444;
        margin: 12px 0;
    }

    strong {
        color: #222;
    }

    a.back-link {
        display: inline-block;
        margin-top: 30px;
        color: #e63946;
        text-decoration: none;
        font-weight: 500;
        font-size: 16px;
    }

    a.back-link:hover {
        text-decoration: underline;
    }
    @media screen and (max-width: 768px) {
    .appointment-details {
        padding: 30px 20px;
        max-width: 100%;
    }
}

</style>

</head>
<body>

<div class="appointment-details">
    <h2>Appointment Details</h2>
    <p><strong>Patient:</strong> <?php echo htmlspecialchars($appointment['PatientName']); ?></p>
    <p><strong>Doctor:</strong> <?php echo htmlspecialchars($appointment['DoctorName'] ?? 'Not assigned'); ?></p>
    <p><strong>Date:</strong> <?php echo htmlspecialchars($appointment['AppointmentDate']); ?></p>
    <p><strong>Time:</strong> <?php echo htmlspecialchars(date('h:i A', strtotime($appointment['AppointmentTime']))); ?></p>
    <p><strong>Reason:</strong> <?php echo htmlspecialchars($appointment['Reason']); ?></p>
    <p><strong>Status:</strong> <?php echo htmlspecialchars($appointment['Status']); ?></p>
    <p><strong>Patient Contact:</strong> <?php echo htmlspecialchars($appointment['Email']) . ' / ' . htmlspecialchars($appointment['Phone']); ?></p>
    <p><a class="back-link" href="dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a></p>
</div>

</body>
</html>
