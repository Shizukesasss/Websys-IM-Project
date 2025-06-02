<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in and is a doctor or nurse
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'doctor' && $_SESSION['role'] !== 'nurse')) {
    header("Location: login.php");
    exit;
}

// Handle delete patient request
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    
    // First, check if the patient exists and belongs to this doctor (if doctor)
    if ($_SESSION['role'] === 'doctor') {
        $check_stmt = $conn->prepare("SELECT p.PatientID FROM patients p 
                                    JOIN appointments a ON p.PatientID = a.PatientID 
                                    WHERE p.PatientID = ? AND a.DoctorID = ?");
        $check_stmt->bind_param("ii", $delete_id, $_SESSION['user_id']);
    } else {
        // For nurses, just check if patient exists
        $check_stmt = $conn->prepare("SELECT PatientID FROM patients WHERE PatientID = ?");
        $check_stmt->bind_param("i", $delete_id);
    }
    
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Patient exists and (if doctor) belongs to this doctor - proceed with deletion
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // First delete related records to maintain referential integrity
            $conn->query("DELETE FROM appointments WHERE PatientID = $delete_id");
            $conn->query("DELETE FROM prescriptions WHERE PatientID = $delete_id");
            $conn->query("DELETE FROM medical_records WHERE PatientID = $delete_id");
            
            // Then delete the patient
            $delete_stmt = $conn->prepare("DELETE FROM patients WHERE PatientID = ?");
            $delete_stmt->bind_param("i", $delete_id);
            $delete_stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            // Redirect to refresh the page and show success message
            $_SESSION['message'] = "Patient deleted successfully";
            header("Location: patients.php");
            exit;
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $_SESSION['error'] = "Error deleting patient: " . $e->getMessage();
            header("Location: patients.php");
            exit;
        }
    } else {
        $_SESSION['error'] = "Patient not found or you don't have permission to delete this patient";
        header("Location: patients.php");
        exit;
    }
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

// In patients.php, replace the patients list query for doctors with:
if ($role === 'doctor') {
    // For doctors, show patients they have treated (have appointments or medical records with)
    $patients_list_stmt = $conn->prepare("SELECT DISTINCT p.* 
                                        FROM patients p
                                        LEFT JOIN appointments a ON p.PatientID = a.PatientID
                                        LEFT JOIN medical_records mr ON p.PatientID = mr.PatientID
                                        WHERE a.DoctorID = ? OR mr.DoctorID = ?
                                        ORDER BY p.Name");
    $patients_list_stmt->bind_param("ii", $user_id, $user_id);
} else {
    // For nurses, show all patients
    $patients_list_stmt = $conn->prepare("SELECT * FROM patients ORDER BY Name");
}

// Execute the patients list query
$patients_list_stmt->execute();
$patients_list = $patients_list_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Check if we have patients
$has_patients = !empty($patients_list);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucfirst($role); ?> Dashboard - Binamira Clinic</title>
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

/* Appointments Table */
.appointments, .patients-section {
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
    color: var(--dark);
}

.btn {
    background-color: var(--primary);
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 5px;
    cursor: pointer;
    transition: background-color 0.3s;
    font-size: 14px;
}

.btn:hover {
    background-color: var(--primary-dark);
}

.btn-danger {
    background-color: var(--danger);
}

.btn-danger:hover {
    background-color: #c82333;
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
}

.status {
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.status.pending {
    background-color: rgba(247, 127, 0, 0.1);
    color: var(--warning);
}

.status.completed {
    background-color: rgba(42, 157, 143, 0.1);
    color: var(--success);
}

.status.cancelled {
    background-color: rgba(217, 4, 41, 0.1);
    color: var(--danger);
}

.action-btn {
    background: none;
    border: none;
    color: var(--primary);
    cursor: pointer;
    margin-right: 10px;
}

.action-btn.delete {
    color: var(--danger);
}

.action-btn:hover {
    opacity: 0.8;
}

/* Search bar and filters */
.table-controls {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
}

.search-container {
    position: relative;
    width: 300px;
}

.search-input {
    width: 100%;
    padding: 10px 15px 10px 40px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 14px;
}

.search-icon {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #999;
}

.patient-filters {
    display: flex;
    gap: 10px;
}

.filter-select {
    padding: 8px 15px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 14px;
    color: #666;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    margin-top: 20px;
}

.pagination-btn {
    padding: 8px 15px;
    margin: 0 5px;
    border: 1px solid #ddd;
    border-radius: 5px;
    background-color: white;
    cursor: pointer;
    transition: background-color 0.3s;
}

.pagination-btn.active {
    background-color: var(--primary);
    color: white;
    border-color: var(--primary);
}

.pagination-btn:hover:not(.active) {
    background-color: #f5f5f5;
}

/* Age calculation class */
.age-display {
    color: #666;
}

/* Gender badges */
.gender-badge {
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
}

.gender-badge.male {
    background-color: rgba(13, 110, 253, 0.1);
    color: #0d6efd;
}

.gender-badge.female {
    background-color: rgba(214, 51, 132, 0.1);
    color: #d63384;
}

.gender-badge.other {
    background-color: rgba(108, 117, 125, 0.1);
    color: #6c757d;
}

/* No data message */
.no-data {
    text-align: center;
    padding: 20px;
    color: #666;
    font-style: italic;
}

/* Modal styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
}

.modal-content {
    background-color: white;
    margin: 15% auto;
    padding: 20px;
    border-radius: 8px;
    width: 400px;
    max-width: 90%;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.modal-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--dark);
}

.close-btn {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #999;
}

.modal-body {
    margin-bottom: 20px;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

/* Alert messages */
.alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 5px;
    font-size: 14px;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
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
                <a href="patients.php" class="nav-item active">
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
                <h1>Patient Management</h1>
                <div class="user-actions">
                   
                    <a href="logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
            
            <!-- Display success/error messages -->
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Patients Table Section -->
            <div class="patients-section">
                <div class="section-header">
                    <h2>Patient Information</h2>
                    <a href="add_patient.php" class="btn">+ Add New Patient</a>
                </div>
                
                <div class="table-controls">
                    <div class="search-container">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" class="search-input" id="patientSearch" placeholder="Search patients...">
                    </div>
                    
                    <div class="patient-filters">
                        <select class="filter-select" id="genderFilter">
                            <option value="">All Genders</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                            <option value="prefer-not-to-say">Prefer not to say</option>
                        </select>
                    </div>
                </div>
                
                <table id="patientsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Gender</th>
                            <th>Date of Birth</th>
                            <th>Age</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($has_patients): ?>
                            <?php foreach ($patients_list as $patient): ?>
                                <?php 
                                // Set gender badge class
                                $genderClass = 'other';
                                if (strtolower($patient['Gender']) === 'male') {
                                    $genderClass = 'male';
                                } elseif (strtolower($patient['Gender']) === 'female') {
                                    $genderClass = 'female';
                                }
                                ?>
                                <tr>
                                    <td><?php echo $patient['PatientID']; ?></td>
                                    <td><?php echo htmlspecialchars($patient['Name']); ?></td>
                                    <td><span class="gender-badge <?php echo $genderClass; ?>"><?php echo htmlspecialchars($patient['Gender']); ?></span></td>
                                    <td><?php echo date('M d, Y', strtotime($patient['BirthDate'])); ?></td>
                                    <td><span class="age-display"><?php echo $patient['Age']; ?> years</span></td>
                                    <td><?php echo htmlspecialchars($patient['Phone']); ?></td>
                                    <td><?php echo htmlspecialchars($patient['Email']); ?></td>
                                    <td>
    <a href="view_patient.php?id=<?php echo $patient['PatientID']; ?>" class="action-btn" title="View"><i class="fas fa-eye"></i></a>
    <a href="edit_patient.php?id=<?php echo $patient['PatientID']; ?>" class="action-btn" title="Edit"><i class="fas fa-edit"></i></a>
    <button class="action-btn delete" onclick="confirmDelete(<?php echo $patient['PatientID']; ?>)" title="Delete"><i class="fas fa-trash-alt"></i></button>
    <?php if ($role === 'nurse'): ?>
        <a href="prescriptions.php?patient_id=<?php echo $patient['PatientID']; ?>" class="action-btn" title="Add Prescription"><i class="fas fa-prescription-bottle-alt"></i></a>
        <a href="appointments.php?patient_id=<?php echo $patient['PatientID']; ?>" class="action-btn" title="Schedule Appointment"><i class="fas fa-calendar-plus"></i></a>
    <?php endif; ?>
</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="no-data">No patients found. Add a new patient to get started.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <div class="pagination">
                    <button class="pagination-btn active">1</button>
                    <button class="pagination-btn">2</button>
                    <button class="pagination-btn">3</button>
                    <button class="pagination-btn">Next</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Confirm Delete</h3>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this patient? This action cannot be undone.</p>
                <p>All related appointments, prescriptions, and medical records will also be deleted.</p>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="closeModal()">Cancel</button>
                <button class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>
    
    <script>
    // Patient search functionality
    document.getElementById('patientSearch').addEventListener('keyup', function() {
        let searchValue = this.value.toLowerCase();
        let rows = document.querySelectorAll('#patientsTable tbody tr');
        
        rows.forEach(row => {
            // Skip the "no data" row if it exists
            if (row.querySelector('.no-data')) {
                return;
            }
            
            let nameCell = row.querySelector('td:nth-child(2)');
            let name = nameCell.textContent.toLowerCase();
            
            if (name.includes(searchValue)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
    
    // Gender filter functionality
    document.getElementById('genderFilter').addEventListener('change', function() {
        let filterValue = this.value.toLowerCase();
        let rows = document.querySelectorAll('#patientsTable tbody tr');
        
        rows.forEach(row => {
            // Skip the "no data" row if it exists
            if (row.querySelector('.no-data')) {
                return;
            }
            
            if (filterValue === '') {
                row.style.display = '';
                return;
            }
            
            let genderCell = row.querySelector('td:nth-child(3)');
            let gender = genderCell.textContent.toLowerCase();
            
            if (gender === filterValue) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
    
    // Delete confirmation modal
    let patientIdToDelete = null;
    
    function confirmDelete(patientId) {
        patientIdToDelete = patientId;
        document.getElementById('deleteModal').style.display = 'block';
    }
    
    function closeModal() {
        document.getElementById('deleteModal').style.display = 'none';
    }
    
    document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
        if (patientIdToDelete) {
            window.location.href = 'patients.php?delete_id=' + patientIdToDelete;
        }
    });
    
    // Close modal when clicking outside of it
    window.addEventListener('click', function(event) {
        if (event.target === document.getElementById('deleteModal')) {
            closeModal();
        }
    });
    </script>
</body>
</html>