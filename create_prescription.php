<?php
session_start();
require_once 'db_connect.php';

// ✅ Only allow logged-in doctors
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointment_id = $_POST['appointment_id'] ?? null;
    $patient_name = $_POST['patient_name'] ?? '';
    $diagnosis = $_POST['diagnosis'] ?? '';
    $advice = $_POST['advice'] ?? '';
    $doctor_id = $_SESSION['user_id'];

    // Validate required fields
    if (empty($appointment_id) || empty($patient_name) || empty($diagnosis)) {
        $_SESSION['error_message'] = "Missing required fields.";
        header("Location: dashboard.php");
        exit;
    }

    // 🔍 Get Patient ID
    $stmt = $conn->prepare("SELECT PatientID FROM appointments WHERE AppointmentID = ?");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result || $result->num_rows === 0) {
        $_SESSION['error_message'] = "Invalid appointment selected.";
        header("Location: dashboard.php");
        exit;
    }

    $appointment = $result->fetch_assoc();
    $patient_id = $appointment['PatientID'];

    // 💊 Insert Prescription
    $instructions = "Diagnosis: $diagnosis\nAdvice: $advice";
    $stmt = $conn->prepare("INSERT INTO prescriptions 
        (AppointmentID, DoctorID, PatientName, DateIssued, Instructions) 
        VALUES (?, ?, ?, CURDATE(), ?)");
    $stmt->bind_param("iiss", $appointment_id, $doctor_id, $patient_name, $instructions);
    $stmt->execute();
    $prescription_id = $conn->insert_id;

    // 💊 Insert Medications
    if (isset($_POST['medications']) && is_array($_POST['medications'])) {
        foreach ($_POST['medications'] as $med) {
            if (!empty($med['name'])) {
                $stmt = $conn->prepare("INSERT INTO prescription_medications 
                    (PrescriptionID, MedicationName, Dosage, Frequency, Duration, Instructions) 
                    VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssss", $prescription_id, $med['name'], $med['dosage'], 
                    $med['frequency'], $med['duration'], $med['instructions']);
                $stmt->execute();
            }
        }
    }

    // 🧪 Insert Investigations
    if (isset($_POST['investigations']) && is_array($_POST['investigations'])) {
        foreach ($_POST['investigations'] as $inv) {
            if (!empty($inv['name'])) {
                $stmt = $conn->prepare("INSERT INTO prescription_investigations 
                    (PrescriptionID, TestName, Instructions) 
                    VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $prescription_id, $inv['name'], $inv['instructions']);
                $stmt->execute();
            }
        }
    }
    

    $_SESSION['success_message'] = "Prescription created successfully!";
    header("Location: prescriptions.php");
    exit;

} else {
    // 🚫 Disallow non-POST access
    header("Location: dashboard.php");
    exit;
}
// After saving the main prescription
if (isset($_POST['vital_signs'])) {
    $vital_signs = $_POST['vital_signs'];
    $stmt = $conn->prepare("INSERT INTO prescription_investigations (PrescriptionID, TestName, Instructions) VALUES (?, ?, ?)");
    
    // Blood Pressure
    if (!empty($vital_signs['bp_systolic']) && !empty($vital_signs['bp_diastolic'])) {
        $bp_value = $vital_signs['bp_systolic'] . '/' . $vital_signs['bp_diastolic'] . ' mmHg';
        $notes = !empty($vital_signs['notes']) ? "\nNotes: " . $vital_signs['notes'] : '';
        $stmt->bind_param("iss", $prescription_id, "Blood Pressure", $bp_value . $notes);
        $stmt->execute();
    }
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
            $stmt->bind_param("iss", $prescription_id, $data['name'], $value);
            $stmt->execute();
        }
    }
?>
