<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'doctor' && $_SESSION['role'] !== 'nurse')) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid patient ID.";
    header("Location: patients.php");
    exit;
}

$patient_id = intval($_GET['id']);

// In view_patient.php, replace the patient access check for doctors with:
if ($role === 'doctor') {
    $stmt = $conn->prepare("SELECT DISTINCT p.* 
                            FROM patients p
                            LEFT JOIN appointments a ON p.PatientID = a.PatientID
                            LEFT JOIN medical_records mr ON p.PatientID = mr.PatientID
                            WHERE p.PatientID = ? AND (a.DoctorID = ? OR mr.DoctorID = ?)");
    $stmt->bind_param("iii", $patient_id, $user_id, $user_id);
} else {
    $stmt = $conn->prepare("SELECT * FROM patients WHERE PatientID = ?");
    $stmt->bind_param("i", $patient_id);
}

$stmt->execute();
$result = $stmt->get_result();
$patient = $result->fetch_assoc();

if (!$patient) {
    $_SESSION['error'] = "Patient not found or access denied.";
    header("Location: patients.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Patient Details - Binamira Clinic</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f8fa;
            margin: 0;
            padding: 20px;
        }

        .card {
            max-width: 700px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            padding: 30px 40px;
        }

        .card h2 {
            font-size: 24px;
            font-weight: 600;
            color: #222;
            margin-bottom: 25px;
            text-align: center;
        }

        .detail-row {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .icon {
            width: 30px;
            text-align: center;
            margin-right: 15px;
            color: #5a5a5a;
        }

        .label {
            flex: 0 0 130px;
            font-weight: 500;
            color: #555;
        }

        .value {
            color: #333;
            flex: 1;
        }

        .back-btn {
            display: inline-block;
            margin-top: 30px;
            background-color: #e63946;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 6px;
            transition: background-color 0.3s ease;
        }

        .back-btn:hover {
            background-color: #c9323f;
        }

        @media (max-width: 600px) {
            .card {
                padding: 20px;
            }

            .label {
                flex: 0 0 100px;
            }

            .detail-row {
                flex-direction: column;
                align-items: flex-start;
            }

            .icon {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="card">
        <h2><i class="fas fa-user-circle"></i> Patient Details</h2>

        <div class="detail-row">
            <div class="icon"><i class="fas fa-id-badge"></i></div>
            <div class="label">Full Name:</div>
            <div class="value"><?php echo htmlspecialchars($patient['Name']); ?></div>
        </div>

        <div class="detail-row">
            <div class="icon"><i class="fas fa-venus-mars"></i></div>
            <div class="label">Gender:</div>
            <div class="value"><?php echo htmlspecialchars($patient['Gender']); ?></div>
        </div>

        <div class="detail-row">
            <div class="icon"><i class="fas fa-birthday-cake"></i></div>
            <div class="label">Birth Date:</div>
            <div class="value"><?php echo date('F d, Y', strtotime($patient['BirthDate'])); ?></div>
        </div>

        <div class="detail-row">
            <div class="icon"><i class="fas fa-hourglass-half"></i></div>
            <div class="label">Age:</div>
            <div class="value"><?php echo htmlspecialchars($patient['Age']); ?> years</div>
        </div>

        <div class="detail-row">
            <div class="icon"><i class="fas fa-phone-alt"></i></div>
            <div class="label">Phone:</div>
            <div class="value"><?php echo htmlspecialchars($patient['Phone']); ?></div>
        </div>

        <div class="detail-row">
            <div class="icon"><i class="fas fa-envelope"></i></div>
            <div class="label">Email:</div>
            <div class="value"><?php echo htmlspecialchars($patient['Email']); ?></div>
        </div>

        <a href="patients.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Patients</a>
    </div>
</body>
</html>
