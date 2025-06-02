<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in and is a doctor or nurse
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'doctor' && $_SESSION['role'] !== 'nurse')) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get user information based on role
if ($role === 'doctor') {
    $stmt = $conn->prepare("SELECT u.*, d.Specialty FROM users u JOIN doctors d ON u.UserID = d.DoctorID WHERE u.UserID = ?");
} else {
    $stmt = $conn->prepare("SELECT u.*, n.Department FROM users u JOIN nurses n ON u.UserID = n.NurseID WHERE u.UserID = ?");
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get statistics for the dashboard
$today = date('Y-m-d');

// For doctors, show their own patients/appointments
// For nurses, show all patients/appointments
if ($role === 'doctor') {
    // Total Patients
    $patients_stmt = $conn->prepare("SELECT COUNT(DISTINCT PatientID) as total FROM appointments WHERE DoctorID = ?");
    $patients_stmt->bind_param("i", $user_id);
    $patients_stmt->execute();
    $patients_result = $patients_stmt->get_result()->fetch_assoc();
    $total_patients = $patients_result['total'];

    // Today's Appointments
    $appointments_stmt = $conn->prepare("SELECT COUNT(*) as total FROM appointments WHERE DoctorID = ? AND AppointmentDate = ?");
    $appointments_stmt->bind_param("is", $user_id, $today);
    $appointments_stmt->execute();
    $appointments_result = $appointments_stmt->get_result()->fetch_assoc();
    $today_appointments = $appointments_result['total'];

    // Total Prescriptions
    $prescriptions_stmt = $conn->prepare("SELECT COUNT(*) as total FROM prescriptions WHERE DoctorID = ?");
    $prescriptions_stmt->bind_param("i", $user_id);
    $prescriptions_stmt->execute();
    $prescriptions_result = $prescriptions_stmt->get_result()->fetch_assoc();
    $total_prescriptions = $prescriptions_result['total'];

    // Upcoming Appointments
    $upcoming_stmt = $conn->prepare("SELECT a.*, p.Name, p.Gender, p.Phone, p.BirthDate 
                                   FROM appointments a 
                                   JOIN patients p ON a.PatientID = p.PatientID 
                                   WHERE a.DoctorID = ? AND a.AppointmentDate >= ? 
                                   ORDER BY a.AppointmentDate, a.AppointmentTime 
                                   LIMIT 5");
    $upcoming_stmt->bind_param("is", $user_id, $today);
} else {
    // For nurses - show all data
    // Total Patients
    $patients_stmt = $conn->prepare("SELECT COUNT(*) as total FROM patients");
    $patients_stmt->execute();
    $patients_result = $patients_stmt->get_result()->fetch_assoc();
    $total_patients = $patients_result['total'];

    // Today's Appointments
    $appointments_stmt = $conn->prepare("SELECT COUNT(*) as total FROM appointments WHERE AppointmentDate = ?");
    $appointments_stmt->bind_param("s", $today);
    $appointments_stmt->execute();
    $appointments_result = $appointments_stmt->get_result()->fetch_assoc();
    $today_appointments = $appointments_result['total'];

    // Total Prescriptions
    $prescriptions_stmt = $conn->prepare("SELECT COUNT(*) as total FROM prescriptions");
    $prescriptions_stmt->execute();
    $prescriptions_result = $prescriptions_stmt->get_result()->fetch_assoc();
    $total_prescriptions = $prescriptions_result['total'];

    // Upcoming Appointments
    $upcoming_stmt = $conn->prepare("SELECT a.*, p.Name, p.Gender, p.Phone, p.BirthDate 
                                   FROM appointments a 
                                   JOIN patients p ON a.PatientID = p.PatientID 
                                   WHERE a.AppointmentDate >= ? 
                                   ORDER BY a.AppointmentDate, a.AppointmentTime 
                                   LIMIT 5");
    $upcoming_stmt->bind_param("s", $today);
}

$upcoming_stmt->execute();
$upcoming_appointments = $upcoming_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucfirst($role); ?> Dashboard - Bhamira Clinic</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css">
    <style>
    /* Cards Section */

.cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
    transition: transform 0.3s;
}

.card:hover {
    transform: translateY(-5px);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.card-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.card-icon.patients {
    background-color: var(--success);
}

.card-icon.appointments {
    background-color: var(--info);
}

.card-icon.prescriptions {
    background-color: var(--warning);
}

.card h3 {
    font-size: 14px;
    color: #666;
    margin-bottom: 5px;
}

.card h2 {
    font-size: 24px;
    color: var(--dark);
}
.profile-info {
    background: white;
    padding: 40px 50px;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
    margin-top: 50px;
    max-width: 1500px;
    width: 100%;
}

.profile-info h2 {
    font-size: 28px;
    color: var(--dark);
    margin-bottom: 30px;
    font-weight: 600;
}

.profile-info form {
    display: flex;
    flex-direction: column;
    gap: 25px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.profile-info label {
    font-weight: 600;
    font-size: 16px;
    color: #333;
    margin-bottom: 10px;
}

.input-with-icon {
    position: relative;
    display: flex;
    align-items: center;
}

.input-with-icon input {
    padding: 14px 45px 14px 15px;
    border: 1px solid #ccc;
    border-radius: 10px;
    font-size: 16px;
    width: 100%;
}

.input-with-icon .edit-icon {
    position: absolute;
    right: 15px;
    color: #888;
    cursor: pointer;
    font-size: 16px;
    pointer-events: none; /* prevents click */
}

.profile-info input[type="file"] {
    padding: 10px;
    font-size: 15px;
}

.profile-info button.btn {
    align-self: flex-start;
    background-color: var(--primary);
    color: white;
    border: none;
    padding: 14px 30px;
    font-weight: 500;
    border-radius: 8px;
    cursor: pointer;
    transition: background-color 0.3s;
    font-size: 16px;
}

.profile-info button.btn:hover {
    background-color: var(--primary-dark);
}



</style>

</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="profile">
                <?php
$avatar = !empty($user['ProfileImage']) && file_exists($user['ProfileImage']) 
    ? $user['ProfileImage'] 
    : "https://ui-avatars.com/api/?name=" . urlencode($user['Name']) . "&background=e63946&color=fff";
?>
<img src="<?php echo $avatar; ?>" alt="Profile" class="profile-img">

                <h3><?php echo htmlspecialchars($user['Name']); ?></h3>
                <p><?php echo htmlspecialchars($role === 'doctor' ? $user['Specialty'] : $user['Department']); ?></p>
            </div>
            
            <div class="nav-menu">
                <a href="dashboard.php" class="nav-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Appointments</span>
                </a>
                <a href="patients.php" class="nav-item">
                    <i class="fas fa-user-injured"></i>
                    <span>Patients</span>
                </a>
                
                <a href="prescriptions.php" class="nav-item">
                    <i class="fas fa-prescription-bottle-alt"></i>
                    <span>Prescriptions</span>
                </a>
                <a href="medical_records.php" class="nav-item">
                    <i class="fas fa-file-medical"></i>
                    <span>Medical Records</span>
                </a>
                   <a href="medicines.php" class="nav-item">
                    <i class="fas fa-pills"></i>
                    <span>Medicines</span>
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
                <a href="profile_settings.php" class="nav-item active">
                    <i class="fas fa-user-cog"></i>
                    <span>Profile Settings</span>
                </a>
            </div>
        </div>
        
  <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1>Profile</h1>
                <div class="user-actions">
                  
                    <a href="logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
            
<div class="profile-info">
    <h2>Welcome, <?php echo htmlspecialchars($user['Name']); ?>!</h2>
    <form action="update_profile.php" method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label>Name:</label>
            <div class="input-with-icon">
                <input type="text" name="name" value="<?php echo htmlspecialchars($user['Name']); ?>" required>
                <span class="edit-icon"><i class="fas fa-pen"></i></span>
            </div>
        </div>

        <div class="form-group">
            <label>Email:</label>
            <div class="input-with-icon">
                <input type="email" name="email" value="<?php echo htmlspecialchars($user['Email']); ?>" required>
                <span class="edit-icon"><i class="fas fa-pen"></i></span>
            </div>
        </div>

        <div class="form-group">
            <label>Phone:</label>
            <div class="input-with-icon">
                <input type="text" name="phone" value="<?php echo htmlspecialchars($user['Phone']); ?>">
                <span class="edit-icon"><i class="fas fa-pen"></i></span>
            </div>
        </div>

        <div class="form-group">
            <label>New Password (leave blank to keep current):</label>
            <div class="input-with-icon">
                <input type="password" name="password" placeholder="Enter new password">
                <span class="edit-icon"><i class="fas fa-pen"></i></span>
            </div>
        </div>

        <div class="form-group">
            <label>Upload Profile Picture:</label>
            <input type="file" name="profile_image" accept="image/*">
        </div>

        <button type="submit" class="btn">Update Profile</button>
    </form>
</div>



            
           
</body>
</html>