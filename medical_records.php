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

// Initialize medical records array
$medical_records = [];
$records_stmt = false;

// Fetch medical records based on role
if ($role === 'doctor') {
    $records_query = "SELECT mr.*, p.Name as PatientName, p.PatientID, u.Name as DoctorName 
                      FROM medical_records mr
                      JOIN patients p ON mr.PatientID = p.PatientID
                      JOIN users u ON mr.DoctorID = u.UserID
                      WHERE mr.DoctorID = ?
                      ORDER BY mr.RecordDate DESC";
    $records_stmt = $conn->prepare($records_query);
    if ($records_stmt) {
        $records_stmt->bind_param("i", $user_id);
        $records_stmt->execute();
        $medical_records = $records_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
} else {
    $records_query = "SELECT mr.*, p.Name as PatientName, p.PatientID, u.Name as DoctorName 
                      FROM medical_records mr
                      JOIN patients p ON mr.PatientID = p.PatientID
                      JOIN users u ON mr.DoctorID = u.UserID
                      ORDER BY mr.RecordDate DESC";
    $records_stmt = $conn->prepare($records_query);
    if ($records_stmt) {
        $records_stmt->execute();
        $medical_records = $records_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// Initialize patients array
$patients = [];
$patients_stmt = false;

// Get patients for the "Add New Record" form (only for nurses)
if ($role === 'nurse') {
    $patients_query = "SELECT * FROM patients ORDER BY Name";
    $patients_stmt = $conn->prepare($patients_query);
    if ($patients_stmt) {
        $patients_stmt->execute();
        $patients = $patients_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// Get doctors for nurse form
$doctors = [];
if ($role === 'nurse') {
    $doctors_query = "SELECT u.UserID, u.Name FROM users u 
                     JOIN doctors d ON u.UserID = d.DoctorID
                     ORDER BY u.Name";
    $doctors_stmt = $conn->prepare($doctors_query);
    if ($doctors_stmt) {
        $doctors_stmt->execute();
        $doctors = $doctors_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// Get appointments for auto-selecting doctor
$appointments = [];
if ($role === 'nurse') {
    $appointments_query = "SELECT a.AppointmentID, a.PatientID, a.DoctorID, u.Name AS DoctorName 
                          FROM appointments a
                          JOIN users u ON a.DoctorID = u.UserID
                          WHERE a.Status = 'Pending'";
    $appointments_stmt = $conn->prepare($appointments_query);
    if ($appointments_stmt) {
        $appointments_stmt->execute();
        $appointments = $appointments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Records - Bhamira Clinic</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        /* Medical Records Specific Styles */
        .medical-records {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }
        
        .record-filter {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .record-filter input, .record-filter select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .record-details {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
            display: none;
        }
        
        .expand-btn {
            background: none;
            border: none;
            color: var(--primary);
            cursor: pointer;
            padding: 5px;
        }
        
        .record-tag {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 15px;
            font-size: 12px;
            margin-right: 5px;
        }
        
        .tag-checkup {
            background-color: rgba(42, 157, 143, 0.1);
            color: var(--success);
        }
        
        .tag-treatment {
            background-color: rgba(247, 127, 0, 0.1);
            color: var(--warning);
        }
        
        .tag-diagnosis {
            background-color: rgba(0, 124, 196, 0.1);
            color: var(--info);
        }
        
        .tag-emergency {
            background-color: rgba(217, 4, 41, 0.1);
            color: var(--danger);
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background-color: white;
            padding: 25px;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
        }
        
        .form-row {
            margin-bottom: 15px;
        }
        
        .form-row label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-row input, .form-row select, .form-row textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: 'Poppins', sans-serif;
        }
        
        .form-row textarea {
            height: 120px;
            resize: vertical;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .delete-btn {
            color: #dc3545;
            background: none;
            border: none;
            cursor: pointer;
            padding: 5px;
        }

        .delete-btn:hover {
            color: #a71d2a;
        }

        .confirm-delete-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .confirm-delete-content {
            background-color: white;
            padding: 25px;
            border-radius: 10px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .confirm-delete-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
        }

        .confirm-delete-buttons button {
            padding: 8px 20px;
            border-radius: 5px;
            cursor: pointer;
        }

        .confirm-delete-btn {
            background-color: #dc3545;
            color: white;
            border: none;
        }

        .cancel-delete-btn {
            background-color: #6c757d;
            color: white;
            border: none;
        }

        .notes-summary {
            color: #666;
            font-style: italic;
            margin-top: 5px;
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
                <a href="medical_records.php" class="nav-item active">
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
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1>Medical Records</h1>
                <div class="user-actions">
                    <div class="notification">
                        <i class="fas fa-bell"></i>
                        <div class="notification-badge">3</div>
                    </div>
                    <a href="logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="cards">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3>Total Records</h3>
                            <h2><?php echo count($medical_records); ?></h2>
                        </div>
                        <div class="card-icon patients">
                            <i class="fas fa-file-medical"></i>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3>Records This Month</h3>
                            <?php 
                            $current_month = date('Y-m');
                            $monthly_count = 0;
                            foreach($medical_records as $record) {
                                if(substr($record['RecordDate'], 0, 7) === $current_month) {
                                    $monthly_count++;
                                }
                            }
                            ?>
                            <h2><?php echo $monthly_count; ?></h2>
                        </div>
                        <div class="card-icon appointments">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3>Unique Patients</h3>
                            <?php 
                            $unique_patients = array();
                            foreach($medical_records as $record) {
                                $unique_patients[$record['PatientID']] = true;
                            }
                            ?>
                            <h2><?php echo count($unique_patients); ?></h2>
                        </div>
                        <div class="card-icon prescriptions">
                            <i class="fas fa-user-injured"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Medical Records Section -->
            <div class="medical-records">
                <div class="section-header">
                    <h2>Medical Records</h2>
                    <?php if ($role === 'nurse'): ?>
                        <button class="btn" id="add-record-btn">Add New Record</button>
                    <?php endif; ?>
                </div>
                
                <div class="record-filter">
                    <input type="text" id="patient-search" placeholder="Search by patient name...">
                    <select id="record-type-filter">
                        <option value="">All Record Types</option>
                        <option value="Checkup">Checkup</option>
                        <option value="Treatment">Treatment</option>
                        <option value="Diagnosis">Diagnosis</option>
                        <option value="Emergency">Emergency</option>
                    </select>
                    <input type="date" id="date-filter">
                </div>
                
                <table id="records-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Patient</th>
                            <th>Record Type</th>
                            <th>Diagnosis</th>
                            <th>Treatment</th>
                            <th>Doctor</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($medical_records)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center;">No medical records found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($medical_records as $record): ?>
                                <tr>
                                    <td data-date="<?php echo date('Y-m-d', strtotime($record['RecordDate'])); ?>">
                                        <?php echo date('M d, Y', strtotime($record['RecordDate'])); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($record['PatientName']); ?></td>
                                    <td>
                                        <span class="record-tag tag-<?php echo strtolower($record['RecordType']); ?>">
                                            <?php echo htmlspecialchars($record['RecordType']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars(substr($record['Diagnosis'], 0, 50) . (strlen($record['Diagnosis']) > 50 ? '...' : '')); ?>
                                        <?php if (!empty($record['Notes'])): ?>
                                            <div class="notes-summary">Has notes</div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(substr($record['Treatment'], 0, 50) . (strlen($record['Treatment']) > 50 ? '...' : '')); ?></td>
                                    <td><?php echo htmlspecialchars($record['DoctorName']); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <!-- To this: -->
<a href="view_medical_record.php?id=<?php echo $record['RecordID']; ?>" class="btn view-btn">
    <i class="fas fa-eye"></i> View
</a>
                                            <?php if ($role === 'nurse'): ?>
                                                <button class="action-btn edit-btn" data-record-id="<?php echo $record['RecordID']; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="delete-btn" data-record-id="<?php echo $record['RecordID']; ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <tr id="details-<?php echo $record['RecordID']; ?>" style="display: none;">
                                    <td colspan="7">
                                        <div class="record-details">
                                            <h4>Diagnosis:</h4>
                                            <p><?php echo nl2br(htmlspecialchars($record['Diagnosis'])); ?></p>
                                            
                                            <h4>Treatment Plan:</h4>
                                            <p><?php echo nl2br(htmlspecialchars($record['Treatment'])); ?></p>
                                            
                                            <?php if (!empty($record['Notes'])): ?>
                                                <h4>Additional Notes:</h4>
                                                <p><?php echo nl2br(htmlspecialchars($record['Notes'])); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Add/Edit Record Modal (only shown for nurses) -->
    <?php if ($role === 'nurse'): ?>
    <div class="modal" id="record-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modal-title">Add New Medical Record</h2>
                <button class="close-btn">&times;</button>
            </div>
            <form id="record-form" action="process_medical_record.php" method="post">
                <input type="hidden" name="record_id" id="record-id">
                <input type="hidden" name="appointment_id" id="appointment-id">
                
                <div class="form-row">
                    <label for="patient">Patient:</label>
                    <select name="patient_id" id="patient" required>
                        <option value="">Select Patient</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?php echo $patient['PatientID']; ?>">
                                <?php echo htmlspecialchars($patient['Name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <label for="doctor">Doctor:</label>
                    <select name="doctor_id" id="doctor" required>
                        <option value="">Select Doctor</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo $doctor['UserID']; ?>">
                                <?php echo htmlspecialchars($doctor['Name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <label for="record-date">Record Date:</label>
                    <input type="date" name="record_date" id="record-date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="form-row">
                    <label for="record-type">Record Type:</label>
                    <select name="record_type" id="record-type" required>
                        <option value="Checkup">Checkup</option>
                        <option value="Treatment">Treatment</option>
                        <option value="Diagnosis">Diagnosis</option>
                        <option value="Emergency">Emergency</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <label for="diagnosis">Diagnosis:</label>
                    <textarea name="diagnosis" id="diagnosis" required></textarea>
                </div>
                
                <div class="form-row">
                    <label for="treatment">Treatment Plan:</label>
                    <textarea name="treatment" id="treatment" required></textarea>
                </div>
                
                <div class="form-row">
                    <label for="notes">Additional Notes:</label>
                    <textarea name="notes" id="notes" placeholder="Optional notes about the patient's condition or treatment"></textarea>
                </div>
                
                <div class="form-row">
                    <button type="submit" class="btn">Save Record</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Confirm Delete Modal -->
    <div class="confirm-delete-modal" id="confirm-delete-modal">
        <div class="confirm-delete-content">
            <h3>Confirm Delete</h3>
            <p>Are you sure you want to delete this medical record? This action cannot be undone.</p>
            <div class="confirm-delete-buttons">
                <button class="cancel-delete-btn">Cancel</button>
                <button class="confirm-delete-btn">Delete</button>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <script>
// Convert PHP appointments array to JavaScript object
const appointments = <?php echo json_encode($appointments); ?>;

document.addEventListener('DOMContentLoaded', function() {
    // Expand record details
    const expandButtons = document.querySelectorAll('.expand-btn');
    expandButtons.forEach(button => {
        button.addEventListener('click', function() {
            const recordId = this.getAttribute('data-record-id');
            const detailsRow = document.getElementById('details-' + recordId);
            
            if (detailsRow.style.display === 'none' || detailsRow.style.display === '') {
                detailsRow.style.display = 'table-row';
                this.innerHTML = '<i class="fas fa-eye-slash"></i> Hide';
            } else {
                detailsRow.style.display = 'none';
                this.innerHTML = '<i class="fas fa-eye"></i> View';
            }
        });
    });
    
    // Modal functionality
    const modal = document.getElementById('record-modal');
    const addRecordBtn = document.getElementById('add-record-btn');
    const closeBtn = document.querySelector('.close-btn');
    const editBtns = document.querySelectorAll('.edit-btn');
    
    if (addRecordBtn) {
        addRecordBtn.addEventListener('click', function() {
            document.getElementById('modal-title').textContent = 'Add New Medical Record';
            document.getElementById('record-form').reset();
            document.getElementById('record-id').value = '';
            document.getElementById('appointment-id').value = '';
            modal.style.display = 'flex';
        });
    }
    
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            modal.style.display = 'none';
        });
    }
    
    if (modal) {
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    }
    
    // Auto-select doctor when patient is selected
    const patientSelect = document.getElementById('patient');
    const doctorSelect = document.getElementById('doctor');
    
    if (patientSelect && doctorSelect) {
        patientSelect.addEventListener('change', function() {
            const patientId = this.value;
            if (!patientId) return;
            
            // Find appointments for this patient
            const patientAppointments = appointments.filter(app => app.PatientID == patientId);
            
            if (patientAppointments.length > 0) {
                // Get the most recent appointment
                const mostRecentAppointment = patientAppointments[0];
                
                // Set the doctor and appointment ID
                doctorSelect.value = mostRecentAppointment.DoctorID;
                document.getElementById('appointment-id').value = mostRecentAppointment.AppointmentID;
            }
        });
    }
    
    // Edit functionality with AJAX (only for nurses)
    if (editBtns) {
        editBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const recordId = this.getAttribute('data-record-id');
                
                // Show loading state
                document.getElementById('modal-title').textContent = 'Loading...';
                modal.style.display = 'flex';
                
                // Fetch record data via AJAX
                fetch(`process_medical_record.php?action=get_record&record_id=${recordId}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.status === 'success') {
                            const record = data.record;
                            document.getElementById('modal-title').textContent = 'Edit Medical Record';
                            document.getElementById('record-id').value = record.RecordID;
                            document.getElementById('patient').value = record.PatientID;
                            
                            if (document.getElementById('doctor')) {
                                document.getElementById('doctor').value = record.DoctorID;
                            }
                            
                            document.getElementById('record-date').value = record.RecordDate;
                            document.getElementById('record-type').value = record.RecordType;
                            document.getElementById('diagnosis').value = record.Diagnosis;
                            document.getElementById('treatment').value = record.Treatment;
                            document.getElementById('notes').value = record.Notes || '';
                            document.getElementById('appointment-id').value = record.AppointmentID || '';
                        } else {
                            alert(data.message);
                            modal.style.display = 'none';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while fetching record data.');
                        modal.style.display = 'none';
                    });
            });
        });
    }
    
    // AJAX form submission (only for nurses)
    const recordForm = document.getElementById('record-form');
    if (recordForm) {
        recordForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const form = this;
            const submitButton = form.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.innerHTML;
            
            // Show loading state
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            
            fetch(form.action, {
                method: 'POST',
                body: new FormData(form)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    modal.style.display = 'none';
                    window.location.reload(); // Refresh to show changes
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while saving the record.');
            })
            .finally(() => {
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
            });
        });
    }
    
    // Search and filter functionality
    const patientSearch = document.getElementById('patient-search');
    const typeFilter = document.getElementById('record-type-filter');
    const dateFilter = document.getElementById('date-filter');
    
    function filterRecords() {
        const searchTerm = patientSearch.value.toLowerCase();
        const selectedType = typeFilter.value;
        const selectedDate = dateFilter.value;
        
        const rows = document.querySelectorAll('#records-table tbody tr:not([id^="details-"])');
        
        rows.forEach(row => {
            const patientName = row.cells[1].textContent.toLowerCase();
            const recordType = row.cells[2].textContent.trim();
            const recordDate = row.cells[0].getAttribute('data-date') || '';
            
            const matchesSearch = !searchTerm || patientName.includes(searchTerm);
            const matchesType = !selectedType || recordType.includes(selectedType);
            const matchesDate = !selectedDate || recordDate === selectedDate;
            
            if (matchesSearch && matchesType && matchesDate) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
                const recordId = row.querySelector('.expand-btn')?.getAttribute('data-record-id');
                if (recordId) {
                    const detailsRow = document.getElementById('details-' + recordId);
                    if (detailsRow) {
                        detailsRow.style.display = 'none';
                    }
                }
            }
        });
    }
    
    patientSearch.addEventListener('input', filterRecords);
    typeFilter.addEventListener('change', filterRecords);
    dateFilter.addEventListener('change', filterRecords);

    // Delete functionality (only for nurses)
    const deleteButtons = document.querySelectorAll('.delete-btn');
    const confirmDeleteModal = document.getElementById('confirm-delete-modal');
    const cancelDeleteBtn = document.querySelector('.cancel-delete-btn');
    const confirmDeleteBtn = document.querySelector('.confirm-delete-btn');
    let recordIdToDelete = null;

    if (deleteButtons) {
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                recordIdToDelete = this.getAttribute('data-record-id');
                confirmDeleteModal.style.display = 'flex';
            });
        });
    }

    if (cancelDeleteBtn) {
        cancelDeleteBtn.addEventListener('click', function() {
            confirmDeleteModal.style.display = 'none';
            recordIdToDelete = null;
        });
    }

    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function() {
            if (!recordIdToDelete) {
                confirmDeleteModal.style.display = 'none';
                return;
            }

            // Show loading state
            confirmDeleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
            confirmDeleteBtn.disabled = true;

            fetch('process_medical_record.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=delete_record&record_id=${recordIdToDelete}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    window.location.reload(); // Refresh to show changes
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the record.');
            })
            .finally(() => {
                confirmDeleteModal.style.display = 'none';
                recordIdToDelete = null;
                confirmDeleteBtn.innerHTML = 'Delete';
                confirmDeleteBtn.disabled = false;
            });
        });
    }

    // Close delete modal when clicking outside
    if (confirmDeleteModal) {
        window.addEventListener('click', function(event) {
            if (event.target === confirmDeleteModal) {
                confirmDeleteModal.style.display = 'none';
                recordIdToDelete = null;
            }
        });
    }
});
</script>
</body>
</html>