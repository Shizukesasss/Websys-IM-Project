<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in and is a doctor or nurse
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'doctor' && $_SESSION['role'] !== 'nurse')) {
    header("Location: login.php");
    exit;
}

// Check if patient ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "Patient ID not provided";
    header("Location: patients.php");
    exit;
}

$patient_id = $_GET['id'];

// In edit_patient.php, replace the patient access check for doctors with:
if ($_SESSION['role'] === 'doctor') {
    $check_stmt = $conn->prepare("SELECT p.* FROM patients p 
                                LEFT JOIN appointments a ON p.PatientID = a.PatientID 
                                LEFT JOIN medical_records mr ON p.PatientID = mr.PatientID
                                WHERE p.PatientID = ? AND (a.DoctorID = ? OR mr.DoctorID = ?)");
    $check_stmt->bind_param("iii", $patient_id, $_SESSION['user_id'], $_SESSION['user_id']);
} else {
    // For nurses, just check if patient exists
    $check_stmt = $conn->prepare("SELECT * FROM patients WHERE PatientID = ?");
    $check_stmt->bind_param("i", $patient_id);
}

$check_stmt->execute();
$patient = $check_stmt->get_result()->fetch_assoc();

if (!$patient) {
    $_SESSION['error'] = "Patient not found or you don't have permission to edit this patient";
    header("Location: patients.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $name = trim($_POST['name']);
    $gender = $_POST['gender'];
    $birthdate = $_POST['birthdate'];
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    
    // Calculate age from birthdate
    $birthDate = new DateTime($birthdate);
    $today = new DateTime();
    $age = $today->diff($birthDate)->y;
    
    // Update patient in database
    $stmt = $conn->prepare("UPDATE patients SET 
                           Name = ?, Gender = ?, BirthDate = ?, Age = ?, 
                           Phone = ?, Email = ?
                           WHERE PatientID = ?");
    $stmt->bind_param("sssissi", $name, $gender, $birthdate, $age, 
                     $phone, $email, $patient_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Patient updated successfully!";
        header("Location: patients.php");
        exit;
    } else {
        $_SESSION['error'] = "Error updating patient: " . $conn->error;
    }
}

// Get user information for the sidebar
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

if ($role === 'doctor') {
    $stmt = $conn->prepare("SELECT u.*, d.Specialty FROM users u JOIN doctors d ON u.UserID = d.DoctorID WHERE u.UserID = ?");
} else {
    $stmt = $conn->prepare("SELECT u.*, n.Department FROM users u JOIN nurses n ON u.UserID = n.NurseID WHERE u.UserID = ?");
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Patient - Binamira Clinic</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .form-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            max-width: 800px;
            margin: 20px auto;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        
        .form-group input, 
        .form-group select, 
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .btn {
            background-color: #e63946;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: #d62839;
        }
        
        .btn-secondary {
            background-color: #6c757d;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-size: 14px;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="profile">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['Name']); ?>&background=e63946&color=fff" alt="Profile" class="profile-img">
                <h3><?php echo htmlspecialchars($user['Name']); ?></h3>
                <p><?php echo htmlspecialchars($role === 'doctor' ? $user['Specialty'] : $user['Department']); ?></p>
            </div>
            
            <div class="nav-menu">
                <a href="dashboard.php" class="nav-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="patients.php" class="nav-item active">
                    <i class="fas fa-user-injured"></i>
                    <span>Patients</span>
                </a>
                <a href="appointments.php" class="nav-item">
                    <i class="fas fa-calendar-check"></i>
                    <span>Appointments</span>
                </a>
                <a href="prescriptions.php" class="nav-item">
                    <i class="fas fa-prescription-bottle-alt"></i>
                    <span>Prescriptions</span>
                </a>
                <a href="medical_records.php" class="nav-item">
                    <i class="fas fa-file-medical"></i>
                    <span>Medical Records</span>
                </a>
                <?php if ($role === 'nurse'): ?>
                <a href="billings.php" class="nav-item">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Billings</span>
                </a>
                <?php endif; ?>
                <a href="messages.php" class="nav-item">
                    <i class="fas fa-envelope"></i>
                    <span>Messages</span>
                </a>
                <a href="profile_settings.php" class="nav-item">
                    <i class="fas fa-user-cog"></i>
                    <span>Profile Settings</span>
                </a>
            </div>
        </div>
        
        <div class="main-content">
            <div class="header">
                <h1>Edit Patient</h1>
                <div class="user-actions">
                    <a href="patients.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Patients
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
                <form method="POST" action="edit_patient.php?id=<?php echo $patient_id; ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Full Name *</label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($patient['Name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="gender">Gender *</label>
                            <select id="gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="male" <?php echo $patient['Gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo $patient['Gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php echo $patient['Gender'] === 'other' ? 'selected' : ''; ?>>Other</option>
                                <option value="prefer-not-to-say" <?php echo $patient['Gender'] === 'prefer-not-to-say' ? 'selected' : ''; ?>>Prefer not to say</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="birthdate">Date of Birth *</label>
                            <input type="date" id="birthdate" name="birthdate" value="<?php echo $patient['BirthDate']; ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone Number *</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($patient['Phone']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($patient['Email']); ?>">
                    </div>
                    
                    <div class="form-actions">
                        <a href="patients.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn">Update Patient</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>