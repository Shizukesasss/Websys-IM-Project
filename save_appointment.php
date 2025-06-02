<?php
// Enable full error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

// Database configuration
$config = [
    'servername' => "localhost",
    'username' => "root",
    'password' => "",
    'dbname' => "binamira_clinic"
];

// Initialize response
$response = [
    'success' => false,
    'message' => 'An unknown error occurred'
];

try {
    // Verify request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method");
    }

    // Get POST data
    $data = $_POST;

    // Validate required fields
    $required = [
        'name', 'birthdate', 'age', 'gender', 'email', 'mobile',
        'clinic', 'service', 'doctor', 'reason'
    ];
    
    $missing = [];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            $missing[] = $field;
        }
    }

    if (!empty($missing)) {
        throw new Exception("Missing required fields: " . implode(', ', $missing));
    }

    // Establish database connection
    $conn = new PDO(
        "mysql:host={$config['servername']};dbname={$config['dbname']}",
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    // Sanitize and validate inputs
    $patientName = trim(htmlspecialchars($data['name']));
    $birthDate = $data['birthdate'];
    $age = filter_var($data['age'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 0, 'max_range' => 120]
    ]);
    $gender = in_array($data['gender'], ['male', 'female', 'other', 'prefer-not-to-say']) 
        ? $data['gender'] 
        : 'prefer-not-to-say';
    $email = filter_var(trim($data['email']), FILTER_VALIDATE_EMAIL);
    $phone = preg_replace('/[^0-9]/', '', $data['mobile']);
    $branch = htmlspecialchars($data['clinic']);
    $service = htmlspecialchars($data['service']);
    $doctorId = filter_var($data['doctor'], FILTER_VALIDATE_INT);
    $reason = trim(htmlspecialchars($data['reason']));

    // Get and validate appointment date/time
    $appointmentDate = isset($data['appointmentDate']) ? date('Y-m-d', strtotime($data['appointmentDate'])) : '';
    $appointmentTime = isset($data['appointmentTime']) ? date('H:i:s', strtotime($data['appointmentTime'])) : '';
    
    if (empty($appointmentDate) || empty($appointmentTime)) {
        throw new Exception("Please select both date and time for your appointment");
    }

    // Validate date is not in the past
    if (strtotime($appointmentDate) < strtotime(date('Y-m-d'))) {
        throw new Exception("Cannot book appointments in the past");
    }

    // Validate time is within working hours (9AM-5PM, excluding 12PM-1PM)
    $hour = date('H', strtotime($appointmentTime));
    if ($hour < 9 || ($hour >= 12 && $hour < 13) || $hour > 17) {
        throw new Exception("Please select a time during clinic hours (9AM-12PM, 1PM-5PM)");
    }

    // Additional validation
    if (!$email) {
        throw new Exception("Please enter a valid email address");
    }
    
    if (!$doctorId || $doctorId < 1) {
        throw new Exception("Invalid doctor selection");
    }

    // Start transaction
    $conn->beginTransaction();

    try {
        // 1. Insert or update patient
        $stmt = $conn->prepare("
            INSERT INTO patients (Name, BirthDate, Age, Gender, Email, Phone)
            VALUES (:name, :birthdate, :age, :gender, :email, :phone)
            ON DUPLICATE KEY UPDATE 
                Name = VALUES(Name),
                BirthDate = VALUES(BirthDate),
                Age = VALUES(Age),
                Phone = VALUES(Phone)
        ");
        
        $stmt->execute([
            ':name' => $patientName,
            ':birthdate' => $birthDate,
            ':age' => $age,
            ':gender' => $gender,
            ':email' => $email,
            ':phone' => $phone
        ]);

        // Get patient ID
        $patientId = $conn->lastInsertId();
        if (!$patientId) {
            $stmt = $conn->prepare("SELECT PatientID FROM patients WHERE Email = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            $patient = $stmt->fetch();
            $patientId = $patient['PatientID'] ?? null;
        }

        if (!$patientId) {
            throw new Exception("Failed to process patient information");
        }

        // 2. Verify doctor exists and is available
        $stmt = $conn->prepare("
            SELECT d.DoctorID 
            FROM doctors d
            JOIN users u ON d.DoctorID = u.UserID
            WHERE d.DoctorID = ? 
            AND u.Role = 'doctor'
            AND u.is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$doctorId]);
        
        if (!$stmt->fetch()) {
            throw new Exception("Selected doctor is not available");
        }

        // 3. Check for existing appointment
        $stmt = $conn->prepare("
            SELECT AppointmentID 
            FROM appointments 
            WHERE DoctorID = ? 
            AND AppointmentDate = ? 
            AND AppointmentTime = ?
            AND Status != 'Cancelled'
            LIMIT 1
        ");
        $stmt->execute([$doctorId, $appointmentDate, $appointmentTime]);

        if ($stmt->fetch()) {
            throw new Exception("This time slot is already booked. Please choose another time.");
        }

        // 4. Insert appointment
        $stmt = $conn->prepare("
            INSERT INTO appointments (
                PatientID, DoctorID, Branch, ServiceType, 
                AppointmentDate, AppointmentTime, Reason, Status
            ) VALUES (
                :patientId, :doctorId, :branch, :serviceType,
                :appointmentDate, :appointmentTime, :reason, 'Pending'
            )
        ");
        
        $stmt->execute([
            ':patientId' => $patientId,
            ':doctorId' => $doctorId,
            ':branch' => $branch,
            ':serviceType' => $service,
            ':appointmentDate' => $appointmentDate,
            ':appointmentTime' => $appointmentTime,
            ':reason' => $reason
        ]);

        $appointmentId = $conn->lastInsertId();

        // 5. Create initial billing record
        $stmt = $conn->prepare("
            INSERT INTO billings (
                AppointmentID, Amount, BillingDate, PaidStatus
            ) VALUES (?, 0, CURDATE(), 'Unpaid')
        ");
        $stmt->execute([$appointmentId]);

        

        // Commit transaction
        $conn->commit();

        // Prepare success response
        $response = [
            'success' => true,
            'appointmentID' => $appointmentId,
            'date' => date('F j, Y', strtotime($appointmentDate)),
            'time' => date('g:i A', strtotime($appointmentTime)),
            'branch' => $branch,
            'doctorID' => $doctorId,
            'message' => 'Appointment booked successfully!',
            'timestamp' => time()
        ];

    } catch (PDOException $e) {
        $conn->rollBack();
        $response['message'] = 'Database error: ' . $e->getMessage();
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
