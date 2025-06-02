<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in and is a doctor or nurse
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'doctor' && $_SESSION['role'] !== 'nurse')) {
    header("Location: login.php");
    exit;
}

// Check if prescription ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: prescriptions.php");
    exit;
}

$prescription_id = $_GET['id'];
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Main prescription query
$stmt = $conn->prepare("SELECT p.*, 
       u.Name AS DoctorName,
       a.PatientID,
       pat.Name AS PatientName,
       a.AppointmentID
FROM prescriptions p
JOIN users u ON p.DoctorID = u.UserID
JOIN appointments a ON p.AppointmentID = a.AppointmentID
JOIN patients pat ON a.PatientID = pat.PatientID
WHERE p.PrescriptionID = ?
");
$stmt->bind_param("i", $prescription_id);
$stmt->execute();
$prescription = $stmt->get_result()->fetch_assoc();

if (!$prescription) {
    header("Location: prescriptions.php");
    exit;
}

// Only allow the prescribing doctor to edit
if ($prescription['DoctorID'] != $user_id && $role !== 'admin') {
    header("Location: view_prescription.php?id=" . $prescription_id);
    exit;
}

// Get medications for this prescription (including diagnosis and advice)
$medications = [];
$med_stmt = $conn->prepare("SELECT * FROM prescription_medications WHERE PrescriptionID = ?");
$med_stmt->bind_param("i", $prescription_id);
$med_stmt->execute();
$med_result = $med_stmt->get_result();
while ($med = $med_result->fetch_assoc()) {
    $medications[] = $med;
}

// Get investigations for this prescription
$investigations = [];
$inv_stmt = $conn->prepare("SELECT * FROM prescription_investigations WHERE PrescriptionID = ?");
$inv_stmt->bind_param("i", $prescription_id);
$inv_stmt->execute();
$inv_result = $inv_stmt->get_result();
while ($inv = $inv_result->fetch_assoc()) {
    $investigations[] = $inv;
}

// Get vital signs from investigations
$vital_signs = [
    'bp_systolic' => '',
    'bp_diastolic' => '',
    'pulse_rate' => '',
    'temperature' => '',
    'respiratory_rate' => '',
    'oxygen_saturation' => '',
    'notes' => ''
];

foreach ($investigations as $inv) {
    if ($inv['TestName'] === 'Blood Pressure') {
        if (preg_match('/(\d+)\/(\d+)/', $inv['Instructions'], $matches)) {
            $vital_signs['bp_systolic'] = $matches[1];
            $vital_signs['bp_diastolic'] = $matches[2];
        }
    } elseif ($inv['TestName'] === 'Pulse Rate') {
        $vital_signs['pulse_rate'] = preg_replace('/[^\d]/', '', $inv['Instructions']);
    } elseif ($inv['TestName'] === 'Temperature') {
        $vital_signs['temperature'] = preg_replace('/[^\d.]/', '', $inv['Instructions']);
    } elseif ($inv['TestName'] === 'Respiratory Rate') {
        $vital_signs['respiratory_rate'] = preg_replace('/[^\d]/', '', $inv['Instructions']);
    } elseif ($inv['TestName'] === 'Oxygen Saturation') {
        $vital_signs['oxygen_saturation'] = preg_replace('/[^\d]/', '', $inv['Instructions']);
    } elseif (strpos($inv['Instructions'], 'Notes:') !== false) {
        $vital_signs['notes'] = trim(str_replace('Notes:', '', $inv['Instructions']));
    }
}

// Get appointment options for the form
$appt_query = $conn->prepare("
    SELECT a.AppointmentID, p.PatientID, p.Name, 
           a.AppointmentDate, a.AppointmentTime, a.Reason
    FROM appointments a 
    JOIN patients p ON a.PatientID = p.PatientID 
    WHERE a.Status = 'Completed'
    ORDER BY a.AppointmentDate DESC
");
$appointments_list = [];
if ($appt_query->execute()) {
    $result = $appt_query->get_result();
    while ($appt = $result->fetch_assoc()) {
        $appointments_list[] = $appt;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $appointment_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : null;
    $patient_id = intval($_POST['patient_id']);
    $diagnosis = trim($_POST['diagnosis']);
    $advice = trim($_POST['advice']);
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Update prescription
        $update_stmt = $conn->prepare("
            UPDATE prescriptions 
            SET AppointmentID = ?, PatientID = ?, LastUpdated = NOW() 
            WHERE PrescriptionID = ?
        ");
        $update_stmt->bind_param("iii", $appointment_id, $patient_id, $prescription_id);
        $update_stmt->execute();
        
        // Delete existing medications
        $delete_meds = $conn->prepare("DELETE FROM prescription_medications WHERE PrescriptionID = ?");
        $delete_meds->bind_param("i", $prescription_id);
        $delete_meds->execute();
        
        // Insert updated medications
        if (isset($_POST['medications'])) {
            $med_stmt = $conn->prepare("
                INSERT INTO prescription_medications 
                (PrescriptionID, MedicationName, Dosage, Frequency, Duration, Instructions, Diagnosis, Advice) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($_POST['medications'] as $med) {
                $med_diagnosis = !empty(trim($med['diagnosis'])) ? trim($med['diagnosis']) : $diagnosis;
                $med_advice = !empty(trim($med['advice'])) ? trim($med['advice']) : $advice;
                
                $med_stmt->bind_param(
                    "isssssss", 
                    $prescription_id,
                    trim($med['name']),
                    trim($med['dosage']),
                    trim($med['frequency']),
                    trim($med['duration']),
                    trim($med['instructions']),
                    $med_diagnosis,
                    $med_advice
                );
                $med_stmt->execute();
            }
        }
        
        // Delete existing investigations
        $delete_inv = $conn->prepare("DELETE FROM prescription_investigations WHERE PrescriptionID = ?");
        $delete_inv->bind_param("i", $prescription_id);
        $delete_inv->execute();
        
        // Insert updated investigations
        if (isset($_POST['investigations'])) {
            $inv_stmt = $conn->prepare("
                INSERT INTO prescription_investigations 
                (PrescriptionID, TestName, Instructions) 
                VALUES (?, ?, ?)
            ");
            
            foreach ($_POST['investigations'] as $inv) {
                $inv_stmt->bind_param(
                    "iss", 
                    $prescription_id,
                    trim($inv['name']),
                    trim($inv['instructions'])
                );
                $inv_stmt->execute();
            }
        }
        
        // Insert vital signs as investigations
        if (isset($_POST['vital_signs'])) {
            $vital_signs = $_POST['vital_signs'];
            $inv_stmt = $conn->prepare("
                INSERT INTO prescription_investigations 
                (PrescriptionID, TestName, Instructions) 
                VALUES (?, ?, ?)
            ");
            
           // Blood Pressure
if (!empty($vital_signs['bp_systolic']) && !empty($vital_signs['bp_diastolic'])) {
    $bp_value = $vital_signs['bp_systolic'] . '/' . $vital_signs['bp_diastolic'] . ' mmHg';
    $notes = !empty($vital_signs['notes']) ? "\nNotes: " . $vital_signs['notes'] : '';
    $inv_stmt->bind_param("iss", $prescription_id, "Blood Pressure", $bp_value . $notes);
    $inv_stmt->execute();
}
            // Other vital signs
            $vital_mappings = [
                'pulse_rate' => ['name' => 'Pulse Rate', 'unit' => 'bpm'],
                'temperature' => ['name' => 'Temperature', 'unit' => '°C'],
                'respiratory_rate' => ['name' => 'Respiratory Rate', 'unit' => 'breaths/min'],
                'oxygen_saturation' => ['name' => 'Oxygen Saturation', 'unit' => '%']
            ];
            
            foreach ($vital_mappings as $field => $data) {
                if (!empty($vital_signs[$field])) {
                    $value = $vital_signs[$field] . ' ' . $data['unit'];
                    $inv_stmt->bind_param("iss", $prescription_id, $data['name'], $value);
                    $inv_stmt->execute();
                }
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        // Redirect to view page with success message
        header("Location: view_prescription.php?id=$prescription_id&success=1");
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $error = "Error updating prescription: " . $e->getMessage();
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Prescription - Clinic Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
         body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        h1 {
            color: #2c3e50;
            margin: 0;
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-back {
            background-color: #95a5a6;
            color: white;
        }
        
        .btn-primary {
            background-color: #3498db;
            color: white;
        }
        
        .btn-secondary {
            background-color: #bdc3c7;
            color: #2c3e50;
        }
        
        .btn-danger {
            background-color: #e74c3c;
            color: white;
        }
        
        .error-message {
            background-color: #fdecea;
            color: #d32f2f;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .success-message {
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        form {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2c3e50;
        }
        
        input[type="text"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: 'Poppins', sans-serif;
        }
        
        textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .medications-section,
        .investigations-section {
            margin: 30px 0;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 8px;
        }
        
        h3 {
            margin-top: 0;
            color: #2c3e50;
        }
        
        .medication-item,
        .investigation-item {
            background-color: white;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            border: 1px solid #eee;
            position: relative;
        }
        
        .medication-row {
            display: grid;
            grid-template-columns: repeat(5, 1fr) 40px;
            gap: 10px;
        }
        
        .remove-btn-container {
            display: flex;
            align-items: flex-end;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .appointment-select {
            width: 100%;
        }
        
        .vital-signs-section {
            margin: 20px 0;
            padding: 15px;
            border: 1px dashed #ddd;
            border-radius: 8px;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-row .form-group {
            flex: 1;
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
    </style>
</head>
<body>
    <div class="container">
        
        <div class="header">
            <h1>Edit Prescription #<?php echo htmlspecialchars($prescription_id); ?></h1>
            <div class="actions">
                <a href="view_prescription.php?id=<?php echo $prescription_id; ?>" class="btn btn-back">
                    <i class="fas fa-arrow-left"></i> Cancel
                </a>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i> Prescription updated successfully!
            </div>
        <?php endif; ?>
        
        <form action="edit_prescription.php?id=<?php echo $prescription_id; ?>" method="POST">
              <div class="form-group">
                <label for="appointment">Select Appointment</label>
                <select name="appointment_id" id="appointment" class="appointment-select">
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
                                data-patient='<?php echo $patient_data; ?>'
                                <?php echo ($prescription['AppointmentID'] == $appt['AppointmentID']) ? 'selected' : ''; ?>>
                            <?php echo $display_text; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="patient_name">Patient Name</label>
                <input type="text" id="patient_name" name="patient_name" 
                       value="<?php echo htmlspecialchars($prescription['PatientName']); ?>" readonly>
                <input type="hidden" id="patient_id" name="patient_id" 
                       value="<?php echo htmlspecialchars($prescription['PatientID']); ?>">
            </div>
            
            <div class="form-group">
                <label for="diagnosis">Primary Diagnosis</label>
                <textarea id="diagnosis" name="diagnosis" rows="3" required><?php 
                    echo htmlspecialchars($prescription['Diagnosis'] ?? ''); 
                ?></textarea>
            </div>
            
           
            
            <div class="medications-section">
                <h3>Medications</h3>
                <div id="medications-container">
                    <?php if (count($medications) > 0): ?>
                        <?php foreach ($medications as $index => $med): ?>
                        <div class="medication-item">
                            <div class="medication-row">
                                <div class="form-group">
                                    <label>Medication Name</label>
                                    <input type="text" name="medications[<?php echo $index; ?>][name]" 
                                           value="<?php echo htmlspecialchars($med['MedicationName']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Dosage</label>
                                    <input type="text" name="medications[<?php echo $index; ?>][dosage]" 
                                           value="<?php echo htmlspecialchars($med['Dosage']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Frequency</label>
                                    <input type="text" name="medications[<?php echo $index; ?>][frequency]" 
                                           value="<?php echo htmlspecialchars($med['Frequency']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Duration</label>
                                    <input type="text" name="medications[<?php echo $index; ?>][duration]" 
                                           value="<?php echo htmlspecialchars($med['Duration']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Instructions</label>
                                    <input type="text" name="medications[<?php echo $index; ?>][instructions]" 
                                           value="<?php echo htmlspecialchars($med['Instructions']); ?>">
                                </div>
                                <div class="form-group remove-btn-container">
                                    <button type="button" class="btn btn-danger remove-medication">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Diagnosis</label>
                                <textarea name="medications[<?php echo $index; ?>][diagnosis]" rows="2"><?php 
                                    echo htmlspecialchars($med['Diagnosis'] ?? ''); 
                                ?></textarea>
                            </div>
                         
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="medication-item">
                            <div class="medication-row">
                                <div class="form-group">
                                    <label>Medication Name</label>
                                    <input type="text" name="medications[0][name]" required>
                                </div>
                                <div class="form-group">
                                    <label>Dosage</label>
                                    <input type="text" name="medications[0][dosage]" required>
                                </div>
                                <div class="form-group">
                                    <label>Frequency</label>
                                    <input type="text" name="medications[0][frequency]" required>
                                </div>
                                <div class="form-group">
                                    <label>Duration</label>
                                    <input type="text" name="medications[0][duration]" required>
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
                           
                           
                        
                    <?php endif; ?>
                </div>
                <button type="button" class="btn btn-secondary" id="add-medication">
                    <i class="fas fa-plus"></i> Add Medication
                </button>
            </div>
            
          
            
            <div class="investigations-section">
                <h3>Investigations</h3>
                <div id="investigations-container">
                    <?php if (count($investigations) > 0): ?>
                        <?php foreach ($investigations as $index => $inv): ?>
                        <div class="investigation-item">
                            <div class="form-group">
                                <label>Test Name</label>
                                <input type="text" name="investigations[<?php echo $index; ?>][name]" 
                                       value="<?php echo htmlspecialchars($inv['TestName']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Instructions</label>
                                <input type="text" name="investigations[<?php echo $index; ?>][instructions]" 
                                       value="<?php echo htmlspecialchars($inv['Instructions']); ?>">
                            </div>
                            <div class="form-group remove-btn-container">
                                <button type="button" class="btn btn-danger remove-investigation">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
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
                    <?php endif; ?>
                </div>
                <button type="button" class="btn btn-secondary" id="add-investigation">
                    <i class="fas fa-plus"></i> Add Investigation
                </button>
            </div>
            
                  <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Prescription
                </button>
                <button type="reset" class="btn btn-secondary">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </form>
    </div>
        </form>
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
                        <input type="text" name="medications[${index}][name]" required>
                    </div>
                    <div class="form-group">
                        <label>Dosage</label>
                        <input type="text" name="medications[${index}][dosage]" required>
                    </div>
                    <div class="form-group">
                        <label>Frequency</label>
                        <input type="text" name="medications[${index}][frequency]" required>
                    </div>
                    <div class="form-group">
                        <label>Duration</label>
                        <input type="text" name="medications[${index}][duration]" required>
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
                <div class="form-group">
                    <label>Medication-Specific Diagnosis</label>
                    <textarea name="medications[${index}][diagnosis]" rows="2"></textarea>
                </div>
                
            `
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

        // Remove medication row
        medicationsContainer.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-medication') || 
                e.target.closest('.remove-medication')) {
                const btn = e.target.classList.contains('remove-medication') ? 
                    e.target : e.target.closest('.remove-medication');
                btn.closest('.medication-item').remove();
            }
        });

        // Remove investigation row
        investigationsContainer.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-investigation') || 
                e.target.closest('.remove-investigation')) {
                const btn = e.target.classList.contains('remove-investigation') ? 
                    e.target : e.target.closest('.remove-investigation');
                btn.closest('.investigation-item').remove();
            }
        });
    });
    </script>
</body>
</html>