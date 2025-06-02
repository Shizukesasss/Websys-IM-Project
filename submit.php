<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $errors = [];

    function sanitize($data) {
        return htmlspecialchars(stripslashes(trim($data)));
    }

    // Sanitize and assign values
    $name = sanitize($_POST['name'] ?? '');
    $birthdate = sanitize($_POST['birthdate'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $mobile = sanitize($_POST['mobile'] ?? '');
    $clinic_branch = sanitize($_POST['clinic'] ?? '');
    $reason = sanitize($_POST['reason'] ?? '');
    $preferred_date = sanitize($_POST['preferred_date'] ?? '');
    $preferred_time = sanitize($_POST['preferred_time'] ?? '');

    // Validate required fields
    $required_fields = [
        'Name' => $name,
        'Birthdate' => $birthdate,
        'Email' => $email,
        'Mobile number' => $mobile,
        'Clinic branch' => $clinic_branch,
        'Reason for appointment' => $reason,
        'Preferred date' => $preferred_date,
        'Preferred time' => $preferred_time
    ];

    foreach ($required_fields as $field_name => $value) {
        if (empty($value)) {
            $errors[] = "$field_name is required.";
        }
    }

    // Email & mobile validation
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    if (!empty($mobile) && !preg_match("/^\d{10,11}$/", $mobile)) {
        $errors[] = "Mobile number must be 10 or 11 digits.";
    }

    if (!empty($birthdate) && strtotime($birthdate) > time()) {
        $errors[] = "Birthdate cannot be in the future.";
    }

    if (!empty($preferred_date) && strtotime($preferred_date) < strtotime(date('Y-m-d'))) {
        $errors[] = "Preferred date cannot be in the past.";
    }

    // Show errors or proceed to save
    if (!empty($errors)) {
        echo "<h3 style='color: red;'>Please fix the following errors:</h3><ul>";
        foreach ($errors as $error) {
            echo "<li>" . htmlspecialchars($error) . "</li>";
        }
        echo "</ul>";
    } else {
        // ✅ DATABASE INSERTION START
        $servername = "localhost";
        $username = "root"; // default for XAMPP
        $password = "";     // default for XAMPP
        $dbname = "binamira_clinic_db"; // your database name

        // Create connection
        $conn = new mysqli($servername, $username, $password, $dbname);

        // Check connection
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        $stmt = $conn->prepare("INSERT INTO booking (name, birthdate, email, mobile, clinic_branch, reason, preferred_date, preferred_time)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssss", $name, $birthdate, $email, $mobile, $clinic_branch, $reason, $preferred_date, $preferred_time);

        if ($stmt->execute()) {
            echo "<h3 style='color: green;'>Appointment Submitted Successfully and saved to the database!</h3>";
        } else {
            echo "<h3 style='color: red;'>Error: " . $stmt->error . "</h3>";
        }

        $stmt->close();
        $conn->close();
        // ✅ DATABASE INSERTION END
    }
}
?>
