<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['doctor', 'nurse'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Handle appointment deletion
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];

    // Delete related billing first
    $delete_billing_stmt = $conn->prepare("DELETE FROM billings WHERE AppointmentID = ?");
    $delete_billing_stmt->bind_param("i", $delete_id);
    $delete_billing_stmt->execute();

    // Then delete the appointment
    $delete_stmt = $conn->prepare("DELETE FROM appointments WHERE AppointmentID = ?");
    $delete_stmt->bind_param("i", $delete_id);

    if ($delete_stmt->execute()) {
        $_SESSION['success_message'] = "Appointment deleted successfully!";
    } else {
        $_SESSION['error_message'] = "Error deleting appointment.";
    }
    header("Location: dashboard.php");
    exit;
}

// Handle status update
if (isset($_POST['update_status'])) {
    $appointment_id = $_POST['appointment_id'];
    $new_status = $_POST['new_status'];

    $update_stmt = $conn->prepare("UPDATE appointments SET Status = ? WHERE AppointmentID = ?");
    $update_stmt->bind_param("si", $new_status, $appointment_id);

    if ($update_stmt->execute()) {
        $_SESSION['success_message'] = "Appointment status updated successfully!";
    } else {
        $_SESSION['error_message'] = "Error updating appointment status.";
    }
    header("Location: dashboard.php");
    exit;
}

// Get user information
if ($role === 'doctor') {
    $stmt = $conn->prepare("SELECT u.*, d.Specialty FROM users u JOIN doctors d ON u.UserID = d.DoctorID WHERE u.UserID = ?");
} else {
    $stmt = $conn->prepare("SELECT u.*, n.Department FROM users u JOIN nurses n ON u.UserID = n.NurseID WHERE u.UserID = ?");
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get appointments based on role
if ($role === 'doctor') {
    // For doctors, show only their assigned appointments
    $appointments_query = "SELECT a.*, p.Name as PatientName, p.Gender, p.BirthDate, p.Email as PatientEmail, 
                           u.Name as DoctorName 
                           FROM appointments a 
                           JOIN patients p ON a.PatientID = p.PatientID 
                           LEFT JOIN users u ON a.DoctorID = u.UserID 
                           WHERE a.DoctorID = ?
                           ORDER BY a.AppointmentDate DESC, a.AppointmentTime DESC";
    $stmt = $conn->prepare($appointments_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $all_appointments = $result->fetch_all(MYSQLI_ASSOC);
} else {
    // For nurses, show all appointments (existing behavior)
    $appointments_query = "SELECT a.*, p.Name as PatientName, p.Gender, p.BirthDate, p.Email as PatientEmail, 
                           u.Name as DoctorName 
                           FROM appointments a 
                           JOIN patients p ON a.PatientID = p.PatientID 
                           LEFT JOIN users u ON a.DoctorID = u.UserID 
                           ORDER BY a.AppointmentDate DESC, a.AppointmentTime DESC";
    $result = $conn->query($appointments_query);
    $all_appointments = $result->fetch_all(MYSQLI_ASSOC);
}

// Dashboard statistics
$today = date('Y-m-d');

if ($role === 'doctor') {
    $patients_stmt = $conn->prepare("SELECT COUNT(DISTINCT PatientID) as total FROM appointments WHERE DoctorID = ?");
    $patients_stmt->bind_param("i", $user_id);
    $patients_stmt->execute();
    $total_patients = $patients_stmt->get_result()->fetch_assoc()['total'];

    $appointments_stmt = $conn->prepare("SELECT COUNT(*) as total FROM appointments WHERE DoctorID = ? AND AppointmentDate = ?");
    $appointments_stmt->bind_param("is", $user_id, $today);
    $appointments_stmt->execute();
    $today_appointments = $appointments_stmt->get_result()->fetch_assoc()['total'];

    $prescriptions_stmt = $conn->prepare("SELECT COUNT(*) as total FROM prescriptions WHERE DoctorID = ?");
    $prescriptions_stmt->bind_param("i", $user_id);
    $prescriptions_stmt->execute();
    $total_prescriptions = $prescriptions_stmt->get_result()->fetch_assoc()['total'];
} else {
    $patients_stmt = $conn->prepare("SELECT COUNT(*) as total FROM patients");
    $patients_stmt->execute();
    $total_patients = $patients_stmt->get_result()->fetch_assoc()['total'];

    $appointments_stmt = $conn->prepare("SELECT COUNT(*) as total FROM appointments WHERE AppointmentDate = ?");
    $appointments_stmt->bind_param("s", $today);
    $appointments_stmt->execute();
    $today_appointments = $appointments_stmt->get_result()->fetch_assoc()['total'];

    $prescriptions_stmt = $conn->prepare("SELECT COUNT(*) as total FROM prescriptions");
    $prescriptions_stmt->execute();
    $total_prescriptions = $prescriptions_stmt->get_result()->fetch_assoc()['total'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo ucfirst($role); ?> Dashboard - Binamira Clinic</title>
    <link rel="stylesheet" href="dashboard.css">
    <!-- Font Awesome CDN -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
            background-color: #4CAF50;
        }

        .card-icon.appointments {
            background-color: #2196F3;
        }

        .card-icon.prescriptions {
            background-color: #FFC107;
        }

        .card h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }

        .card h2 {
            font-size: 24px;
            color: #333;
        }

        /* Appointments Table */
        .appointments {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-header h2 {
            font-size: 20px;
            color: #333;
        }

        .btn {
            background-color: #e63946;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            font-size: 14px;
        }

        .btn:hover {
            background-color: #d62839;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 12px 15px;
            background-color: #f8f9fa;
            color: #666;
            font-weight: 500;
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }

        .status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status.pending {
            background-color: rgba(247, 127, 0, 0.1);
            color: #FF9800;
        }

        .status.completed {
            background-color: rgba(42, 157, 143, 0.1);
            color: #4CAF50;
        }

        .status.cancelled {
            background-color: rgba(217, 4, 41, 0.1);
            color: #F44336;
        }

        .status.confirmed {
            background-color: rgba(33, 150, 243, 0.1);
            color: #2196F3;
        }

        .action-btn {
            background: none;
            border: none;
            cursor: pointer;
            margin-right: 10px;
            font-size: 14px;
            padding: 5px;
            border-radius: 3px;
            transition: background-color 0.3s;
        }

        .action-btn:hover {
            background-color: rgba(0, 0, 0, 0.1);
        }

        .action-btn.view {
            color: #2196F3;
        }

        .action-btn.edit {
            color: #FFC107;
        }

        .action-btn.delete {
            color: #F44336;
        }

        /* Confirm button styling */
        .confirm-btn {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: background-color 0.3s;
            margin-bottom: 5px;
            display: block;
        }

        .confirm-btn:hover {
            background-color: #45a049;
        }

        .status-form {
            display: inline-block;
        }

        .status-select {
            padding: 5px;
            border-radius: 4px;
            border: 1px solid #ddd;
            font-size: 12px;
        }

        .status-update-btn {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            margin-left: 5px;
        }

        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            border-left: 4px solid;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
            border-left-color: #4CAF50;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border-left-color: #F44336;
        }

        .message .close-btn {
            float: right;
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: inherit;
            opacity: 0.7;
        }

        .message .close-btn:hover {
            opacity: 1;
        }

        /* Loading spinner for confirm button */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .loading::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            margin: auto;
            border: 2px solid transparent;
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .actions-cell {
            min-width: 150px;
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
        <a href="appointments.php" class="nav-item active">
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
        <a href="medicines.php" class="nav-item">
            <i class="fas fa-pills"></i>
            <span>Medicines</span>
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

         <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1>Dashboard</h1>
                <div class="user-actions">
                   
                    <a href="logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
            

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="message success" id="successMessage">
                    <button class="close-btn" onclick="closeMessage('successMessage')">&times;</button>
                    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="message error" id="errorMessage">
                    <button class="close-btn" onclick="closeMessage('errorMessage')">&times;</button>
                    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="cards">
                <div class="card">
                    <h3><?php echo $role === 'doctor' ? 'My Patients' : 'Total Patients'; ?></h3>
                    <h2><?php echo $total_patients; ?></h2>
                </div>
                <div class="card">
                    <h3><?php echo $role === 'doctor' ? 'My Today\'s Appointments' : 'Today\'s Appointments'; ?></h3>
                    <h2><?php echo $today_appointments; ?></h2>
                </div>
                <div class="card">
                    <h3><?php echo $role === 'doctor' ? 'My Prescriptions' : 'Total Prescriptions'; ?></h3>
                    <h2><?php echo $total_prescriptions; ?></h2>
                </div>
            </div>

            <!-- Appointments Table -->
            <div class="appointments">
                <div class="section-header">
                    <h2><?php echo $role === 'doctor' ? 'My Appointments' : 'All Appointments'; ?></h2>
                    <button class="btn" onclick="window.location.href='add_appointment.php'">
                        <i class="fas fa-plus"></i> Add New
                    </button>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>Patient Name</th>
                            <th>Date & Time</th>
                            <?php if ($role === 'nurse'): ?>
                            <th>Doctor</th>
                            <?php endif; ?>
                            <th>Branch</th>
                            <th>Service</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($all_appointments)): ?>
                            <?php foreach ($all_appointments as $appointment): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($appointment['PatientName']); ?></strong><br>
                                        <small>
                                            <?php echo ucfirst($appointment['Gender']); ?>,
                                            <?php
                                                $bday = new DateTime($appointment['BirthDate']);
                                                $age = (new DateTime())->diff($bday)->y;
                                                echo $age . ' yrs';
                                            ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php echo date('M j, Y', strtotime($appointment['AppointmentDate'])); ?><br>
                                        <small><?php echo date('h:i A', strtotime($appointment['AppointmentTime'])); ?></small>
                                    </td>
                                    <?php if ($role === 'nurse'): ?>
                                    <td><?php echo htmlspecialchars($appointment['DoctorName'] ?? 'Not assigned'); ?></td>
                                    <?php endif; ?>
                                    <td><?php echo htmlspecialchars($appointment['Branch']); ?></td>
                                    <td><?php echo htmlspecialchars($appointment['ServiceType']); ?></td>
                                    <td><?php echo htmlspecialchars($appointment['Reason']); ?></td>
                                    <td>
                                        <span class="status <?php echo strtolower($appointment['Status']); ?>">
                                            <?php echo htmlspecialchars($appointment['Status']); ?>
                                        </span>
                                    </td>
                                    <td class="actions-cell">
                                        <div class="actions-container">
                                            <div class="icon-actions">
                                                <button class="action-btn view" onclick="window.location.href='appointment_details.php?id=<?php echo $appointment['AppointmentID']; ?>'" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="action-btn edit" onclick="window.location.href='edit_appointment.php?id=<?php echo $appointment['AppointmentID']; ?>'" title="Edit Appointment">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="action-btn delete" onclick="confirmDelete(<?php echo $appointment['AppointmentID']; ?>)" title="Delete Appointment">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                            <?php if ($appointment['Status'] !== 'Confirmed' && $appointment['Status'] !== 'Completed' && $appointment['Status'] !== 'Cancelled'): ?>
                                            <button class="confirm-btn" onclick="confirmAppointment(<?php echo $appointment['AppointmentID']; ?>, '<?php echo htmlspecialchars($appointment['PatientName']); ?>', '<?php echo htmlspecialchars($appointment['PatientEmail']); ?>')">
                                                Confirm Appointment
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="<?php echo $role === 'doctor' ? '7' : '8'; ?>" style="text-align: center;">No appointments found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Hidden form for appointment confirmation -->
    <form id="confirmForm" action="email_confirmation.php" method="POST" style="display: none;">
        <input type="hidden" name="appointment_id" id="confirmAppointmentId">
    </form>

    <script>
        function confirmDelete(id) {
            if (confirm("Are you sure you want to delete this appointment?")) {
                window.location.href = "dashboard.php?delete_id=" + id;
            }
        }

        function confirmAppointment(appointmentId, patientName, patientEmail) {
            if (patientEmail === '' || patientEmail === null) {
                alert("Patient email is not available. Cannot send confirmation email.");
                return;
            }
            
            const confirmMessage = `Confirm appointment for ${patientName}?\n\nThis will:\n- Change status to "Confirmed"\n- Send confirmation email to: ${patientEmail}`;
            
            if (confirm(confirmMessage)) {
                // Show loading state
                const confirmButton = event.target;
                const originalText = confirmButton.textContent;
                confirmButton.classList.add('loading');
                confirmButton.textContent = 'Confirming...';
                confirmButton.disabled = true;
                
                // Submit the form to email_confirmation.php
                document.getElementById('confirmAppointmentId').value = appointmentId;
                document.getElementById('confirmForm').submit();
            }
        }

        function closeMessage(messageId) {
            const message = document.getElementById(messageId);
            if (message) {
                message.style.opacity = '0';
                setTimeout(() => {
                    message.remove();
                }, 300);
            }
        }

        // Auto-hide success messages after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const successMessage = document.getElementById('successMessage');
            if (successMessage) {
                setTimeout(() => {
                    closeMessage('successMessage');
                }, 5000);
            }
        });
    </script>
</body>
</html>