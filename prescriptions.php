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

// Get appointment options for the form
$appt_query = $conn->prepare("
    SELECT a.AppointmentID, p.PatientID, p.Name, 
           a.AppointmentDate, a.AppointmentTime, a.Reason, a.Status
    FROM appointments a 
    JOIN patients p ON a.PatientID = p.PatientID 
    ORDER BY a.AppointmentDate DESC
");

$appointments_list = [];
if ($appt_query->execute()) {
    $result = $appt_query->get_result();
    while ($appt = $result->fetch_assoc()) {
        $appointments_list[] = $appt;
    }
}

// Get all prescriptions for the view section
$prescriptions_query = $conn->prepare("
    SELECT p.*, u.Name AS DoctorName 
    FROM prescriptions p
    JOIN users u ON p.DoctorID = u.UserID
    ORDER BY p.DateIssued DESC
");
$prescriptions_list = [];
if ($prescriptions_query->execute()) {
    $result = $prescriptions_query->get_result();
    while ($pres = $result->fetch_assoc()) {
        $prescriptions_list[] = $pres;
    }
}

// Get all medicines for dropdown
$medicines_query = $conn->prepare("
    SELECT * FROM medicines
    ORDER BY Name ASC
");
$medicines_list = [];
if ($medicines_query->execute()) {
    $result = $medicines_query->get_result();
    while ($med = $result->fetch_assoc()) {
        $medicines_list[] = $med;
    }
}
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
    .dashboard {
    display: flex;
    min-height: 100vh;
    font-family: 'Poppins', sans-serif;
}

/* Sidebar Styles */
.sidebar {
    width: 250px;
    background: white;
    height: 100vh;
    position: fixed;
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
    z-index: 100;
    transition: all 0.3s ease;
    overflow-y: auto;
}

.profile {
    padding: 25px 20px;
    text-align: center;
    border-bottom: 1px solid var(--light-gray);
}

.profile-img {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    object-fit: cover;
    margin: 0 auto 15px;
    border: 3px solid var(--primary);
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.profile-img:hover {
    transform: scale(1.05);
}

.profile h3 {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 5px;
    color: var(--dark);
}

.profile p {
    font-size: 14px;
    color: var(--gray);
    margin-bottom: 0;
}

.nav-menu {
    padding: 15px 0;
}

.nav-item {
    display: flex;
    align-items: center;
    padding: 12px 25px;
    color: var(--gray);
    text-decoration: none;
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    font-size: 15px;
    margin: 2px 0;
}

.nav-item i {
    width: 24px;
    text-align: center;
    margin-right: 12px;
    font-size: 16px;
    transition: all 0.3s ease;
}

.nav-item:hover {
    background-color: rgba(230, 57, 70, 0.1);
    color: var(--primary);
}

.nav-item.active {
    background-color: rgba(230, 57, 70, 0.15);
    color: var(--primary);
    border-left: 3px solid var(--primary);
    font-weight: 500;
}

.nav-item.active i {
    color: var(--primary);
}

/* Main Content Area */
.main-content {
    flex: 1;
    margin-left: 250px;
    padding: 30px;
    min-height: 100vh;
    transition: all 0.3s ease;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.header h1 {
    font-size: 24px;
    color: var(--dark);
    font-weight: 600;
}

.user-actions {
    display: flex;
    align-items: center;
    gap: 15px;
}

.notification {
    position: relative;
    color: var(--gray);
    font-size: 20px;
    cursor: pointer;
}

.notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background-color: var(--danger);
    color: white;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    font-size: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.logout-btn {
    background-color: var(--primary);
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 5px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 5px;
}

.logout-btn:hover {
    background-color: var(--primary-dark);
}

.logout-btn i {
    font-size: 14px;
}
/* Content Wrapper */
.content-wrapper {
    padding: 20px;
}

/* Form Styles */
.prescription-form {
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
    color: #2c3e50;
}

.btn {
    background-color: rgb(217, 19, 19);
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 5px;
    cursor: pointer;
    transition: background-color 0.3s;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.btn:hover {
    background-color: rgb(212, 28, 28);
}

.btn-primary {
    background-color: rgb(235, 14, 18);
}

.btn-primary:hover {
    background-color: rgb(237, 19, 19);
}

.btn-secondary {
    background-color: #95a5a6;
}

.btn-secondary:hover {
    background-color: #7f8c8d;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: #555;
}

.form-group input[type="text"],
.form-group input[type="number"],
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-family: 'Poppins', sans-serif;
}

.form-group textarea {
    min-height: 80px;
}

/* Medications and Investigations Sections */
.medications-section,
.investigations-section {
    margin: 20px 0;
    padding: 15px;
    border: 1px dashed #ddd;
    border-radius: 5px;
}

.medications-section h3,
.investigations-section h3 {
    margin-top: 0;
    color: #d91313;
}

.medication-row {
    display: grid;
    grid-template-columns: repeat(6, 1fr) 40px;
    gap: 10px;
    align-items: flex-end;
}

.investigation-item {
    display: grid;
    grid-template-columns: 1fr 1fr 40px;
    gap: 10px;
    align-items: flex-end;
}

.remove-btn-container {
    margin-bottom: 15px;
}

.btn-danger {
    background-color: #e74c3c;
    color: white;
}

.btn-danger:hover {
    background-color: #c0392b;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
}

.appointment-select {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-family: 'Poppins', sans-serif;
}

/* Prescriptions Table Styles */
.prescriptions-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

.prescriptions-table th {
    background-color: #f8f9fa;
    padding: 12px 15px;
    text-align: left;
}

.prescriptions-table td {
    padding: 12px 15px;
    border-bottom: 1px solid #eee;
}

.action-buttons {
    display: flex;
    gap: 5px;
}

.btn-view {
    background-color: #17a2b8;
    color: white;
}

.btn-view:hover {
    background-color: #138496;
}

.btn-edit {
    background-color: #ffc107;
    color: black;
}

.btn-edit:hover {
    background-color: #e0a800;
}

.btn-delete {
    background-color: #dc3545;
    color: white;
}

.btn-delete:hover {
    background-color: #c82333;
}

.hidden {
    display: none;
}
.vital-signs-section {
    margin: 20px 0;
    padding: 15px;
    border: 1px dashed #ddd;
    border-radius: 5px;
}

.bp-inputs {
    display: flex;
    align-items: center;
    gap: 5px;
}

.bp-inputs input {
    width: 70px;
}

.bp-separator {
    margin: 0 5px;
}

.unit {
    margin-left: 5px;
    color: #777;
    font-size: 14px;
}

.form-row {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
}

.form-row .form-group {
    flex: 1;
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
                <a href="prescriptions.php" class="nav-item active">
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
                <a href="profile_settings.php" class="nav-item">
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
            
            
            <div class="content-wrapper">
                <!-- Toggle Buttons -->
                <div class="section-header">
                    <h2>Prescriptions</h2>
                    <div>
                        <button id="show-form-btn" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Create New Prescription
                        </button>
                        <button id="show-list-btn" class="btn btn-view">
                            <i class="fas fa-list"></i> View All Prescriptions
                        </button>
                    </div>
                </div>
                
                <!-- Prescription Form Section -->
                <div id="prescription-form-section" class="prescription-form">
                    <form action="create_prescription.php" method="POST">
                        <div class="form-group">
                            <label for="appointment">Select Appointment</label>
                            <select name="appointment_id" id="appointment" required class="appointment-select">
                                <option value="">-- Select Appointment --</option>
                                <?php foreach ($appointments_list as $appt): 
                                    $display_text = htmlspecialchars(
                                        $appt['Name'] . ' - ' . 
                                        date('M j, Y', strtotime($appt['AppointmentDate'])) . ' at ' . 
                                        date('g:i A', strtotime($appt['AppointmentTime'])) . ' - ' . 
                                        $appt['Reason']
                                    );
                                    
                                    $patient_data = json_encode([
                                        'name' => $appt['Name'],
                                        'id' => $appt['PatientID']
                                    ]);
                                ?>
                                    <option value="<?php echo $appt['AppointmentID']; ?>" 
                                            data-patient='<?php echo $patient_data; ?>'>
                                        <?php echo $display_text; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="patient_name">Patient Name</label>
                            <input type="text" id="patient_name" name="patient_name" readonly>
                            <input type="hidden" id="patient_id" name="patient_id">
                        </div>
                        
                        <div class="form-group">
                            <label for="diagnosis">Diagnosis</label>
                            <textarea id="diagnosis" name="diagnosis" rows="3" required></textarea>
                        </div>
                        
                        <div class="medications-section">
                            <h3>Medications</h3>
                            <div id="medications-container">
                                <div class="medication-item">
                                    <div class="medication-row">
                                        <div class="form-group">
                                            <label>Medication Name</label>
                                            <select name="medications[0][medicine_id]" class="medicine-select" required>
                                                <option value="">-- Select Medicine --</option>
                                                <?php foreach ($medicines_list as $medicine): ?>
                                                <option value="<?php echo $medicine['MedicineID']; ?>" data-name="<?php echo htmlspecialchars($medicine['Name']); ?>">
                                                    <?php echo htmlspecialchars($medicine['Name']); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="hidden" name="medications[0][name]" class="medicine-name-input">
                                        </div>
                                        <div class="form-group">
                                            <label>Dosage</label>
                                            <select name="medications[0][dosage]" required>
                                                <option value="">-- Select --</option>
                                                <option value="2.5 mg">2.5 mg</option>
                                                <option value="5 mg">5 mg</option>
                                                <option value="10 mg">10 mg</option>
                                                <option value="20 mg">20 mg</option>
                                                <option value="25 mg">25 mg</option>
                                                <option value="50 mg">50 mg</option>
                                                <option value="100 mg">100 mg</option>
                                                <option value="250 mg">250 mg</option>
                                                <option value="500 mg">500 mg</option>
                                                <option value="1000 mg">1000 mg</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Frequency</label>
                                            <select name="medications[0][frequency]" required>
                                                <option value="">-- Select --</option>
                                                <option value="Once daily">Once daily</option>
                                                <option value="Twice daily">Twice daily</option>
                                                <option value="Three times daily">Three times daily</option>
                                                <option value="Four times daily">Four times daily</option>
                                                <option value="Every 4 hours">Every 4 hours</option>
                                                <option value="Every 6 hours">Every 6 hours</option>
                                                <option value="Every 8 hours">Every 8 hours</option>
                                                <option value="Every 12 hours">Every 12 hours</option>
                                                <option value="As needed">As needed</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Duration</label>
                                            <select name="medications[0][duration]" required>
                                                <option value="">-- Select --</option>
                                                <option value="1 day">1 day</option>
                                                <option value="2 days">2 days</option>
                                                <option value="3 days">3 days</option>
                                                <option value="4 days">4 days</option>
                                                <option value="5 days">5 days</option>
                                                <option value="7 days">7 days</option>
                                                <option value="10 days">10 days</option>
                                                <option value="14 days">14 days</option>
                                                <option value="1 month">1 month</option>
                                                <option value="Continuous">Continuous</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Quantity</label>
                                            <input type="number" name="medications[0][quantity]" min="1" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Instructions</label>
                                            <input type="text" name="medications[0][instructions]">
                                        </div>
                                        <div class="form-group remove-btn-container">
                                            <button type="button" class="btn btn-danger remove-medication" style="display: none;">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-secondary" id="add-medication">
                                <i class="fas fa-plus"></i> Add Medication
                            </button>
                        </div>
                        
                        <div class="investigations-section">
                            <h3>Investigations</h3>
                            <div id="investigations-container">
                                <div class="investigation-item">
                                    <div class="form-group">
                                        <label>Test Name</label>
                                        <input type="text" name="investigations[0][name]" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Instructions</label>
                                        <input type="text" name="investigations[0][instructions]">
                                    </div>
                                    <div class="form-group remove-btn-container">
                                        <button type="button" class="btn btn-danger remove-investigation" style="display: none;">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-secondary" id="add-investigation">
                                <i class="fas fa-plus"></i> Add Investigation
                            </button>
                        </div>
  
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Prescription
                            </button>
                            <button type="reset" class="btn btn-secondary">
                                <i class="fas fa-undo"></i> Reset
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Prescriptions List Section -->
                <div id="prescriptions-list-section" class="hidden">
                    <div class="appointments">
                        <table class="prescriptions-table">
                            <thead>
                                <tr>
                                    <th>Prescription ID</th>
                                    <th>Patient Name</th>
                                    <th>Doctor</th>
                                    <th>Date Issued</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($prescriptions_list as $prescription): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($prescription['PrescriptionID']); ?></td>
                                    <td><?php echo htmlspecialchars($prescription['PatientName']); ?></td>
                                    <td><?php echo htmlspecialchars($prescription['DoctorName']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($prescription['DateIssued'])); ?></td>
                                    <td class="action-buttons">
                                        <a href="view_prescription.php?id=<?php echo $prescription['PrescriptionID']; ?>" class="btn btn-view">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="edit_prescription.php?id=<?php echo $prescription['PrescriptionID']; ?>" class="btn btn-edit">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <form action="delete_prescription.php" method="POST" style="display:inline;">
                                            <input type="hidden" name="prescription_id" value="<?php echo $prescription['PrescriptionID']; ?>">
                                            <button type="submit" class="btn btn-delete" onclick="return confirm('Are you sure you want to delete this prescription?');">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-fill patient name when appointment is selected
        const appointmentSelect = document.getElementById('appointment');
        const patientNameInput = document.getElementById('patient_name');
        const patientIdInput = document.getElementById('patient_id');

        appointmentSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const data = selectedOption.getAttribute('data-patient');
            if (data) {
                const patientData = JSON.parse(data);
                patientNameInput.value = patientData.name;
                patientIdInput.value = patientData.id;
            } else {
                patientNameInput.value = '';
                patientIdInput.value = '';
            }
        });

        // Toggle between form and list views
        const showFormBtn = document.getElementById('show-form-btn');
        const showListBtn = document.getElementById('show-list-btn');
        const formSection = document.getElementById('prescription-form-section');
        const listSection = document.getElementById('prescriptions-list-section');
        
        showFormBtn.addEventListener('click', function() {
            formSection.classList.remove('hidden');
            listSection.classList.add('hidden');
        });
        
        showListBtn.addEventListener('click', function() {
            formSection.classList.add('hidden');
            listSection.classList.remove('hidden');
        });

        // Set medicine name value when medicine is selected
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('medicine-select')) {
                const selectedOption = e.target.options[e.target.selectedIndex];
                const medicineName = selectedOption.getAttribute('data-name');
                const medicineNameInput = e.target.nextElementSibling;
                medicineNameInput.value = medicineName;
            }
        });

        // Dynamic medication fields
        const medicationsContainer = document.getElementById('medications-container');
        const addMedicationBtn = document.getElementById('add-medication');
        
        addMedicationBtn.addEventListener('click', function() {
            const index = medicationsContainer.children.length;
            const medicationItem = document.createElement('div');
            medicationItem.className = 'medication-item';
            medicationItem.innerHTML = `
                <div class="medication-row">
                    <div class="form-group">
                        <label>Medication Name</label>
                        <select name="medications[${index}][medicine_id]" class="medicine-select" required>
                            <option value="">-- Select Medicine --</option>
                            <?php foreach ($medicines_list as $medicine): ?>
                            <option value="<?php echo $medicine['MedicineID']; ?>" data-name="<?php echo htmlspecialchars($medicine['Name']); ?>">
                                <?php echo htmlspecialchars($medicine['Name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="medications[${index}][name]" class="medicine-name-input">
                    </div>
                    <div class="form-group">
                        <label>Dosage</label>
                        <select name="medications[${index}][dosage]" required>
                            <option value="">-- Select --</option>
                            <option value="2.5 mg">2.5 mg</option>
                            <option value="5 mg">5 mg</option>
                            <option value="10 mg">10 mg</option>
                            <option value="20 mg">20 mg</option>
                            <option value="25 mg">25 mg</option>
                            <option value="50 mg">50 mg</option>
                            <option value="100 mg">100 mg</option>
                            <option value="250 mg">250 mg</option>
                            <option value="500 mg">500 mg
                                                        <option value="1000 mg">1000 mg</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Frequency</label>
                        <select name="medications[${index}][frequency]" required>
                            <option value="">-- Select --</option>
                            <option value="Once daily">Once daily</option>
                            <option value="Twice daily">Twice daily</option>
                            <option value="Three times daily">Three times daily</option>
                            <option value="Four times daily">Four times daily</option>
                            <option value="Every 4 hours">Every 4 hours</option>
                            <option value="Every 6 hours">Every 6 hours</option>
                            <option value="Every 8 hours">Every 8 hours</option>
                            <option value="Every 12 hours">Every 12 hours</option>
                            <option value="As needed">As needed</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Duration</label>
                        <select name="medications[${index}][duration]" required>
                            <option value="">-- Select --</option>
                            <option value="1 day">1 day</option>
                            <option value="2 days">2 days</option>
                            <option value="3 days">3 days</option>
                            <option value="4 days">4 days</option>
                            <option value="5 days">5 days</option>
                            <option value="7 days">7 days</option>
                            <option value="10 days">10 days</option>
                            <option value="14 days">14 days</option>
                            <option value="1 month">1 month</option>
                            <option value="Continuous">Continuous</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Quantity</label>
                        <input type="number" name="medications[${index}][quantity]" min="1" required>
                    </div>
                    <div class="form-group">
                        <label>Instructions</label>
                        <input type="text" name="medications[${index}][instructions]">
                    </div>
                    <div class="form-group remove-btn-container">
                        <button type="button" class="btn btn-danger remove-medication">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
            medicationsContainer.appendChild(medicationItem);
        });

        // Dynamic investigation fields
        const investigationsContainer = document.getElementById('investigations-container');
        const addInvestigationBtn = document.getElementById('add-investigation');
        
        addInvestigationBtn.addEventListener('click', function() {
            const index = investigationsContainer.children.length;
            const investigationItem = document.createElement('div');
            investigationItem.className = 'investigation-item';
            investigationItem.innerHTML = `
                <div class="form-group">
                    <label>Test Name</label>
                    <input type="text" name="investigations[${index}][name]" required>
                </div>
                <div class="form-group">
                    <label>Instructions</label>
                    <input type="text" name="investigations[${index}][instructions]">
                </div>
                <div class="form-group remove-btn-container">
                    <button type="button" class="btn btn-danger remove-investigation">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
            investigationsContainer.appendChild(investigationItem);
        });

        // Remove medication or investigation
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-medication') || 
                e.target.parentElement.classList.contains('remove-medication')) {
                const btn = e.target.classList.contains('remove-medication') ? e.target : e.target.parentElement;
                const medicationItem = btn.closest('.medication-item');
                if (medicationItem && medicationsContainer.children.length > 1) {
                    medicationItem.remove();
                }
            }
            
            if (e.target.classList.contains('remove-investigation') || 
                e.target.parentElement.classList.contains('remove-investigation')) {
                const btn = e.target.classList.contains('remove-investigation') ? e.target : e.target.parentElement;
                const investigationItem = btn.closest('.investigation-item');
                if (investigationItem && investigationsContainer.children.length > 1) {
                    investigationItem.remove();
                }
            }
        });

        // Show remove buttons for all but the first item
        function updateRemoveButtons() {
            const medicationItems = medicationsContainer.querySelectorAll('.medication-item');
            medicationItems.forEach((item, index) => {
                const removeBtn = item.querySelector('.remove-medication');
                if (removeBtn) {
                    removeBtn.style.display = index === 0 ? 'none' : 'inline-block';
                }
            });

            const investigationItems = investigationsContainer.querySelectorAll('.investigation-item');
            investigationItems.forEach((item, index) => {
                const removeBtn = item.querySelector('.remove-investigation');
                if (removeBtn) {
                    removeBtn.style.display = index === 0 ? 'none' : 'inline-block';
                }
            });
        }

        // Initialize
        updateRemoveButtons();
        formSection.classList.remove('hidden');
        listSection.classList.add('hidden');
    });
    </script>
</body>
</html>