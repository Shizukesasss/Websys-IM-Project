<?php
header('Content-Type: application/json');

// Database configuration (use your actual credentials)
$config = [
    'servername' => "localhost",
    'username' => "root",
    'password' => "",
    'dbname' => "binamira_clinic"
];

try {
    $conn = new PDO(
        "mysql:host={$config['servername']};dbname={$config['dbname']}",
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    // Query to get all active (non-cancelled) appointments
    $stmt = $conn->prepare("
        SELECT DATE(AppointmentDate) as date, AppointmentTime as time 
        FROM appointments 
        WHERE Status != 'Cancelled'
    ");
    $stmt->execute();
    $appointments = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'appointments' => $appointments
    ]);
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>