<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Get list of patients for dropdown
$patients = [];
try {
    if ($_SESSION['role'] === 'doctor') {
        $patients_stmt = $conn->prepare("SELECT p.PatientID, p.Name 
                                            FROM patients p
                                            JOIN appointments a ON p.PatientID = a.PatientID
                                            WHERE a.DoctorID = ?
                                            GROUP BY p.PatientID
                                            ORDER BY p.Name");
        $patients_stmt->execute([$_SESSION['user_id']]);
    } else {
        $patients_stmt = $conn->prepare("SELECT PatientID, Name FROM patients ORDER BY Name");
        $patients_stmt->execute();
    }
    $patients = $patients_stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching patients: " . $e->getMessage();
}

// Get list of doctors for nurse role
$doctors = [];
if ($_SESSION['role'] === 'nurse') {
    try {
        $doctors_stmt = $conn->prepare("SELECT u.UserID, u.Name 
                                           FROM users u 
                                           JOIN doctors d ON u.UserID = d.DoctorID
                                           ORDER BY u.Name");
        $doctors_stmt->execute();
        $doctors = $doctors_stmt->fetchAll();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error fetching doctors: " . $e->getMessage();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = $_POST['patient_id'];
    $doctor_id = $_SESSION['role'] === 'nurse' ? $_POST['doctor_id'] : $_SESSION['user_id'];
    $record_date = $_POST['record_date'];
    $record_type = $_POST['record_type'];
    $diagnosis = trim($_POST['diagnosis']);
    $treatment = trim($_POST['treatment']);
    $notes = trim($_POST['notes']);
    
    try {
        $stmt = $conn->prepare("INSERT INTO medical_records 
                                    (PatientID, DoctorID, RecordDate, RecordType, Diagnosis, Treatment, Notes) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $patient_id, 
            $doctor_id, 
            $record_date, 
            $record_type, 
            $diagnosis, 
            $treatment, 
            $notes
        ]);
        
        $_SESSION['message'] = "Medical record added successfully!";
        header("Location: medical_records.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error adding medical record: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Medical Record - Bhamira Clinic</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css"> <style>
      .form-container {
        background: white;
        border-radius: 8px;
        padding: 20px;
        margin-top: 20px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
      }
      .form-group {
        margin-bottom: 15px;
      }
      .form-group label {
        display: block;
        margin-bottom: 5px;
        color: #555;
        font-weight: bold;
        font-size: 14px;
      }
      .form-group input, .form-group select, .form-group textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 14px;
        box-sizing: border-box;
      }
      .form-group textarea {
        resize: vertical;
        height: 100px;
      }
      .form-actions {
        margin-top: 20px;
        text-align: right;
      }
      .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 14px;
        font-weight: bold;
        transition: background-color 0.3s ease;
      }
      .btn-secondary {
        background-color: #e0e0e0;
        color: #333;
      }
      .btn-secondary:hover {
        background-color: #ccc;
      }
      .btn-primary {
        background-color: #007bff;
        color: white;
      }
      .btn-primary:hover {
        background-color: #0056b3;
      }
      .alert {
        padding: 15px;
        margin-bottom: 20px;
        border: 1px solid transparent;
        border-radius: 5px;
        font-size: 14px;
      }
      .alert-danger {
        background-color: #f8d7da;
        color: #721c24;
        border-color: #f5c6cb;
      }
      .alert-success {
        background-color: #d4edda;
        color: #155724;
        border-color: #c3e6cb;
      }
    </style>
</head>
<body>
    <div class="dashboard">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <div class="header">
                <h1>Add Medical Record</h1>
                <div class="user-actions">
                    <a href="medical_records.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Records
                    </a>
                </div>
            </div>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <div class="form-container">
                <form method="POST" action="add_medical_record.php">
                    <div class="form-group">
                        <label for="patient_id">Patient *</label>
                        <select id="patient_id" name="patient_id" required>
                            <option value="">Select Patient</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['PatientID']; ?>">
                                    <?php echo htmlspecialchars($patient['Name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if ($_SESSION['role'] === 'nurse'): ?>
                    <div class="form-group">
                        <label for="doctor_id">Doctor *</label>
                        <select id="doctor_id" name="doctor_id" required>
                            <option value="">Select Doctor</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['UserID']; ?>">
                                    <?php echo htmlspecialchars($doctor['Name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="record_date">Record Date *</label>
                        <input type="datetime-local" id="record_date" name="record_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="record_type">Record Type *</label>
                        <select id="record_type" name="record_type" required>
                            <option value="">Select Type</option>
                            <option value="Checkup">Checkup</option>
                            <option value="Treatment">Treatment</option>
                            <option value="Diagnosis">Diagnosis</option>
                            <option value="Emergency">Emergency</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="diagnosis">Diagnosis *</label>
                        <textarea id="diagnosis" name="diagnosis" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="treatment">Treatment Plan *</label>
                        <textarea id="treatment" name="treatment" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes"></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <a href="medical_records.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Add Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Get the record_date input
        const recordDateInput = document.getElementById('record_date');

        // Set the default value to the current date and time in the correct format
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0'); // Months are 0-indexed
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const formattedDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
        recordDateInput.value = formattedDateTime;
    });
    </script>
</body>
</html>
