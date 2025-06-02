<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Check if record ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "Record ID not provided";
    header("Location: medical_records.php");
    exit;
}

$record_id = $_GET['id'];

// Get the record to edit
$record_stmt = $conn->prepare("SELECT mr.*, p.Name AS PatientName 
                             FROM medical_records mr
                             JOIN patients p ON mr.PatientID = p.PatientID
                             WHERE mr.RecordID = ?");
$record_stmt->execute([$record_id]);
$record = $record_stmt->fetch();

if (!$record) {
    $_SESSION['error'] = "Medical record not found";
    header("Location: medical_records.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $diagnosis = trim($_POST['diagnosis']);
    $treatment = trim($_POST['treatment']);
    $notes = trim($_POST['notes']);
    
    try {
        $stmt = $conn->prepare("UPDATE medical_records SET 
                               Diagnosis = ?, 
                               Treatment = ?, 
                               Notes = ? 
                               WHERE RecordID = ?");
        $stmt->execute([$diagnosis, $treatment, $notes, $record_id]);
        
        $_SESSION['message'] = "Medical record updated successfully!";
        header("Location: medical_records.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating medical record: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Keep the existing head content -->
    ...
</head>
<body>
    <div class="dashboard">
        <!-- Include your sidebar -->
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <div class="header">
                <h1>Edit Medical Record</h1>
                <div class="user-actions">
                    <a href="medical_records.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Records
                    </a>
                </div>
            </div>
            
            <!-- Display error messages -->
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <div class="form-container">
                <form method="POST" action="edit_medical_record.php?id=<?php echo $record_id; ?>">
                    <div class="patient-info">
                        <strong>Patient:</strong> <?php echo htmlspecialchars($record['PatientName']); ?><br>
                        <strong>Record Date:</strong> <?php echo date('M d, Y H:i', strtotime($record['RecordDate'])); ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="diagnosis">Diagnosis *</label>
                        <textarea id="diagnosis" name="diagnosis" required><?php echo htmlspecialchars($record['Diagnosis']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="treatment">Treatment *</label>
                        <textarea id="treatment" name="treatment" required><?php echo htmlspecialchars($record['Treatment']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes"><?php echo htmlspecialchars($record['Notes']); ?></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <a href="medical_records.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn">Update Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>