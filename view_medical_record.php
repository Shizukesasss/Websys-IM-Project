<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in and is a doctor or nurse
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'doctor' && $_SESSION['role'] !== 'nurse')) {
    header("Location: login.php");
    exit;
}

// Check if record ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "Record ID not provided";
    header("Location: medical_records.php");
    exit;
}

$record_id = intval($_GET['id']);

// Get the medical record details
$stmt = $conn->prepare("SELECT mr.*, p.Name AS PatientName, u.Name AS DoctorName 
                       FROM medical_records mr
                       JOIN patients p ON mr.PatientID = p.PatientID
                       JOIN users u ON mr.DoctorID = u.UserID
                       WHERE mr.RecordID = ?");
$stmt->bind_param("i", $record_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Medical record not found";
    header("Location: medical_records.php");
    exit;
}

$record = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Medical Record - Bhamira Clinic</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            margin: 0;
            padding: 0;
            color: #333;
        }
        
        .dashboard {
            display: flex;
            min-height: 100vh;
        }
        
        .main-content {
            flex: 1;
            padding: 30px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 28px;
            color: #2c3e50;
            margin: 0;
        }
        
        .record-container {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .record-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .record-title {
            font-size: 24px;
            color: #2c3e50;
            margin: 0;
            font-weight: 600;
        }
        
        .record-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .meta-item {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            font-size: 14px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .meta-item strong {
            display: block;
            color: #7f8c8d;
            font-size: 13px;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .record-section {
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 18px;
            color: #2c3e50;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 1px solid #eee;
            font-weight: 500;
        }
        
        .section-content {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            white-space: pre-wrap;
            line-height: 1.6;
            font-size: 15px;
        }
        
        .record-tag {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            text-transform: capitalize;
        }
        
        .tag-checkup {
            background-color: rgba(42, 157, 143, 0.1);
            color: #2a9d8f;
        }
        
        .tag-treatment {
            background-color: rgba(247, 127, 0, 0.1);
            color: #f77f00;
        }
        
        .tag-diagnosis {
            background-color: rgba(0, 124, 196, 0.1);
            color: #007cc4;
        }
        
        .tag-emergency {
            background-color: rgba(217, 4, 41, 0.1);
            color: #d90429;
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background-color:rgb(226, 44, 44);
            color: white;
            border-radius: 6px;
            text-decoration: none;
            transition: background-color 0.3s;
            font-size: 14px;
            font-weight: 500;
        }
        
        .back-btn:hover {
            background-color: rgb(189, 55, 55);
            color: white;
        }
        
        @media (max-width: 768px) {
            .record-meta {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .record-container {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1>Medical Record Details</h1>
                <div class="user-actions">
                    <a href="medical_records.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i> Back to Records
                    </a>
                </div>
            </div>
            
            <div class="record-container">
                <div class="record-header">
                    <h2 class="record-title">Medical Record #<?php echo $record_id; ?></h2>
                    <span class="record-tag tag-<?php echo strtolower($record['RecordType']); ?>">
                        <?php echo htmlspecialchars($record['RecordType']); ?>
                    </span>
                </div>
                
                <div class="record-meta">
                    <div class="meta-item">
                        <strong>Patient</strong>
                        <?php echo htmlspecialchars($record['PatientName']); ?>
                    </div>
                    <div class="meta-item">
                        <strong>Doctor</strong>
                        <?php echo htmlspecialchars($record['DoctorName']); ?>
                    </div>
                    <div class="meta-item">
                        <strong>Date</strong>
                        <?php echo date('M d, Y H:i', strtotime($record['RecordDate'])); ?>
                    </div>
                </div>
                
                <div class="record-section">
                    <h3 class="section-title">Diagnosis</h3>
                    <div class="section-content">
                        <?php echo nl2br(htmlspecialchars($record['Diagnosis'])); ?>
                    </div>
                </div>
                
                <div class="record-section">
                    <h3 class="section-title">Treatment Plan</h3>
                    <div class="section-content">
                        <?php echo nl2br(htmlspecialchars($record['Treatment'])); ?>
                    </div>
                </div>
                
                <?php if (!empty($record['Notes'])): ?>
                <div class="record-section">
                    <h3 class="section-title">Additional Notes</h3>
                    <div class="section-content">
                        <?php echo nl2br(htmlspecialchars($record['Notes'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>