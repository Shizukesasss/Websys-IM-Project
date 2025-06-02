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

// Get prescription details
$stmt = $conn->prepare("
    SELECT p.*, 
           u.Name AS DoctorName,
           pat.Name AS PatientName,
           pat.PatientID,
           pat.BirthDate,
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

// Get medications for this prescription
$medications = [];
$med_stmt = $conn->prepare("SELECT * FROM prescription_medications WHERE PrescriptionID = ?");
$med_stmt->bind_param("i", $prescription_id);
$med_stmt->execute();
$med_result = $med_stmt->get_result();
while ($med = $med_result->fetch_assoc()) {
    $medications[] = $med;
}

// Extract Diagnosis and Advice from first medication, if exists
$diagnosis = '';
$advice = '';
if (count($medications) > 0) {
    $diagnosis = $medications[0]['Diagnosis'] ?? '';
    $advice = $medications[0]['Advice'] ?? '';
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
// Fetch vital signs
$vital_signs = [];
$investigations_query = $conn->prepare("
    SELECT * FROM prescription_investigations 
    WHERE PrescriptionID = ? 
    AND TestName IN ('Blood Pressure', 'Pulse Rate', 'Temperature', 'Respiratory Rate', 'Oxygen Saturation')
    ORDER BY FIELD(TestName, 'Blood Pressure', 'Pulse Rate', 'Temperature', 'Respiratory Rate', 'Oxygen Saturation')
");
$investigations_query->bind_param("i", $prescription_id);
$investigations_query->execute();
$result = $investigations_query->get_result();

while ($row = $result->fetch_assoc()) {
    $vital_signs[$row['TestName']] = $row['Instructions'];
}

// Display section
if (!empty($vital_signs)): ?>
<div class="vital-signs-display">
    <h3>Vital Signs</h3>
    <div class="vital-signs-grid">
        <?php foreach ($vital_signs as $test => $value): ?>
        <div class="vital-sign-item">
            <span class="vital-sign-label"><?= htmlspecialchars($test) ?>:</span>
            <span class="vital-sign-value"><?= htmlspecialchars($value) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>View Prescription - Bhamira Clinic</title>
<!-- Fonts & Icons -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
<style>
body {
    font-family: 'Poppins', sans-serif;
    background-color: #f5f7fa;
    margin: 0;
    padding: 0;
}

.container {
    max-width: 1000px;
    margin: 20px auto;
    padding: 20px;
    background: white;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

/* Header styles */
.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

/* Add space for logo and title */
.header-top {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
}

.header-top img {
    height: 60px;
    margin-right: 10px;
}

.header h1 {
    margin: 0;
    color: #2c3e50;
}

/* Buttons */
.btn {
    padding: 8px 15px;
    border-radius: 5px;
    cursor: pointer;
    transition: background-color 0.3s;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    text-decoration: none;
}

.btn-print {
    background-color: #3498db;
    color: white;
}

.btn-print:hover {
    background-color: #2980b9;
}

.btn-back {
    background-color: #95a5a6;
    color: white;
}

.btn-back:hover {
    background-color: #7f8c8d;
}

/* Prescription header info */
.prescription-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 30px;
}

.clinic-info h2 {
    color: #3498db;
    margin: 0 0 5px 0;
}

.clinic-info p {
    margin: 0;
    color: #7f8c8d;
    font-size: 14px;
}

.prescription-meta p {
    margin: 0 0 5px 0;
    color: #555;
}

.prescription-id {
    font-weight: bold;
    font-size: 18px;
    color: #2c3e50;
}

/* Patient info */
.patient-info {
    margin-bottom: 30px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 5px;
}

.patient-info h3 {
    margin-top: 0;
    color: #3498db;
}

.patient-details {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
}

.detail-label {
    font-weight: 500;
    color: #555;
    font-size: 14px;
}

.detail-value {
    color: #2c3e50;
}

/* Sections */
.section {
    margin-bottom: 30px;
}

.section h3 {
    color: #3498db;
    border-bottom: 1px solid #eee;
    padding-bottom: 5px;
    margin-bottom: 15px;
}

.diagnosis-content,
.advice-content {
    white-space: pre-line;
    line-height: 1.6;
}

.medications-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.medications-table th {
    background-color: #f8f9fa;
    padding: 10px;
    text-align: left;
}

.medications-table td {
    padding: 10px;
    border-bottom: 1px solid #eee;
}

.investigations-list {
    list-style: none;
    padding: 0;
}

.investigation-item {
    padding: 10px 0;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
}

.signature-section {
    margin-top: 50px;
    display: flex;
    justify-content: flex-end;
}

.signature-box {
    text-align: center;
    border-top: 1px solid #ddd;
    padding-top: 10px;
    width: 300px;
}

.signature-name {
    font-weight: bold;
    margin-top: 5px;
}

.signature-title {
    color: #7f8c8d;
    font-size: 14px;
}

@media print {
    body {
        background: none;
    }
    .container {
        box-shadow: none;
        max-width: 100%;
        padding: 0;
    }
    .no-print {
        display: none;
    }
}
.vital-signs-display {
    margin: 20px 0;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 5px;
}

.vital-signs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
}

.vital-sign-item {
    background: white;
    padding: 10px 15px;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.vital-sign-label {
    font-weight: 600;
    color: #333;
    display: block;
    margin-bottom: 3px;
}

.vital-sign-value {
    color: #555;
    font-size: 1.1em;
}
</style>
</head>
<body>
<div class="container">
    <!-- Logo and Header -->
    <div class="header-top">
        <img src="images/rxlogo.png" alt="RX Logo" />
        <h1>Prescription Details</h1>
    </div>
    
    <!-- Print & Back Buttons -->
    <div class="header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <div class="actions">
            <button class="btn btn-print no-print" onclick="window.print()">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="prescriptions.php" class="btn btn-back no-print">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>

    <!-- Clinic Info & Prescription Meta -->
    <div class="prescription-header">
        <div class="clinic-info">
            <h2>Binamira Medical Clinic</h2>
            <p>123 Medical Street, Health City, HC 12345</p>
            <p>Phone: (123) 456-7890 | Email: info@bhamiraclinic.com</p>
        </div>
        <div class="prescription-meta">
            <p class="prescription-id">Prescription #<?php echo htmlspecialchars($prescription['PrescriptionID']); ?></p>
            <p>Date: <?php echo date('M j, Y', strtotime($prescription['DateIssued'])); ?></p>
            <p>Doctor: <?php echo htmlspecialchars($prescription['DoctorName']); ?></p>
        </div>
    </div>
    
    <!-- Patient Info -->
    <div class="patient-info">
        <h3>Patient Information</h3>
        <div class="patient-details">
            <div class="detail-item">
                <p class="detail-label">Patient Name</p>
                <p class="detail-value"><?php echo htmlspecialchars($prescription['PatientName']); ?></p>
            </div>
            <div class="detail-item">
                <p class="detail-label">Patient ID</p>
                <p class="detail-value"><?php echo htmlspecialchars($prescription['PatientID']); ?></p>
            </div>
            <div class="detail-item">
                <p class="detail-label">Date of Birth</p>
                <p class="detail-value"><?php echo !empty($prescription['BirthDate']) ? date('M j, Y', strtotime($prescription['BirthDate'])) : 'N/A'; ?></p>
            </div>
        </div>
    </div>
    
    <!-- Diagnosis -->
    <div class="section">
        <h3>Diagnosis</h3>
        <div class="diagnosis-content">
            <?php echo nl2br(htmlspecialchars($diagnosis)); ?>
        </div>
    </div>
    
    <!-- Medications -->
    <div class="section">
        <h3>Medications</h3>
        <table class="medications-table">
          <!-- In the medications table header -->
<thead>
    <tr>
        <th>Medication</th>
        <th>Dosage</th>
        <th>Frequency</th>
        <th>Duration</th>
        <th>Quantity</th>
        <th>Instructions</th>
    </tr>
</thead>

<!-- In the medications table body -->
<tbody>
    <?php if (count($medications) > 0): ?>
        <?php foreach ($medications as $med): ?>
            <tr>
                <td><?php echo htmlspecialchars($med['MedicationName']); ?></td>
                <td><?php echo htmlspecialchars($med['Dosage']); ?></td>
                <td><?php echo htmlspecialchars($med['Frequency']); ?></td>
                <td><?php echo htmlspecialchars($med['Duration']); ?></td>
                <td><?php echo htmlspecialchars($med['Quantity'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($med['Instructions']); ?></td>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr><td colspan="6" style="text-align:center;">No medications prescribed</td></tr>
    <?php endif; ?>
</tbody>
        </table>
    </div>

    <!-- Investigations -->
    <div class="section">
        <h3>Investigations</h3>
        <ul class="investigations-list">
            <?php if (count($investigations) > 0): ?>
                <?php foreach($investigations as $inv): ?>
                    <li class="investigation-item">
                        <span><?php echo htmlspecialchars($inv['TestName']); ?></span>
                        <span><?php echo htmlspecialchars($inv['Instructions']); ?></span>
                    </li>
                <?php endforeach; ?>
            <?php else: ?>
                <li>No investigations requested</li>
            <?php endif; ?>
        </ul>
    </div>

  
    <!-- Signature -->
    <div class="signature-section">
        <div class="signature-box">
            <p class="signature-name"><?php echo htmlspecialchars($prescription['DoctorName']); ?></p>
            <p class="signature-title">Medical Doctor</p>
            <p class="signature-title">License No: MD123456</p>
        </div>
    </div>
</div>

<script>
    // Auto-print if URL has ?print=true
    if (window.location.search.includes('print=true')) {
        window.print();
    }
</script>
</body>
</html>