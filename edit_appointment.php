<?php
session_start();
require_once 'db_connect.php';

if (!isset($_GET['id'])) {
    echo "Appointment ID missing.";
    exit;
}

$appointment_id = $_GET['id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['cancel_appointment'])) {
        // Handle cancellation
        $stmt = $conn->prepare("UPDATE appointments SET Status = 'Cancelled' WHERE AppointmentID = ?");
        $stmt->bind_param("i", $appointment_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Appointment cancelled successfully!";
            header("Location: dashboard.php");
            exit;
        } else {
            echo "Failed to cancel appointment.";
        }
    } else {
        // Handle update
        $branch = $_POST['branch'];
        $service = $_POST['service'];
        $date = $_POST['date'];
        $time = $_POST['time'];
        $reason = $_POST['reason'];
        $status = $_POST['status'];

        $stmt = $conn->prepare("UPDATE appointments SET Branch = ?, ServiceType = ?, AppointmentDate = ?, AppointmentTime = ?, Reason = ?, Status = ? WHERE AppointmentID = ?");
        $stmt->bind_param("ssssssi", $branch, $service, $date, $time, $reason, $status, $appointment_id);

        if ($stmt->execute()) {
            $_SESSION['message'] = "Appointment updated successfully!";
            header("Location: dashboard.php");
            exit;
        } else {
            echo "Failed to update appointment.";
        }
    }
}

// Fetch current data
$stmt = $conn->prepare("SELECT * FROM appointments WHERE AppointmentID = ?");
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$result = $stmt->get_result();
$appointment = $result->fetch_assoc();

if (!$appointment) {
    echo "Appointment not found.";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Appointment</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            padding: 60px 20px;
            margin: 0;
        }

        .container {
            max-width: 700px;
            background: #fff;
            padding: 40px 50px;
            margin: auto;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }

        h2 {
            margin-top: 0;
            margin-bottom: 25px;
            font-weight: 600;
            font-size: 28px;
            color: #333;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #444;
        }

        input[type="text"],
        input[type="date"],
        input[type="time"],
        select,
        textarea {
            width: 100%;
            padding: 10px 15px;
            font-size: 16px;
            border: 1px solid #ccc;
            border-radius: 6px;
            margin-bottom: 20px;
            box-sizing: border-box;
        }

        textarea {
            height: 100px;
            resize: vertical;
        }

        .btn {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 12px 25px;
            font-size: 16px;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .btn:hover {
            background-color: #43a047;
        }

        .cancel-btn {
            background-color: #e63946;
            margin-top: 10px;
        }

        .cancel-btn:hover {
            background-color: #d62839;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #e63946;
            text-decoration: none;
            font-weight: 500;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Edit Appointment</h2>
    <form method="POST">
        <label>Branch:</label>
        <input type="text" name="branch" value="<?php echo htmlspecialchars($appointment['Branch']); ?>" required>

        <label>Service Type:</label>
        <input type="text" name="service" value="<?php echo htmlspecialchars($appointment['ServiceType']); ?>" required>

        <label>Date:</label>
        <input type="date" name="date" value="<?php echo $appointment['AppointmentDate']; ?>" required>

        <label>Time:</label>
        <input type="time" name="time" value="<?php echo $appointment['AppointmentTime']; ?>" required>

        <label>Reason:</label>
        <textarea name="reason" required><?php echo htmlspecialchars($appointment['Reason']); ?></textarea>

        <label>Status:</label>
        <select name="status">
            <option value="Pending" <?php if ($appointment['Status'] === 'Pending') echo 'selected'; ?>>Pending</option>
            <option value="Completed" <?php if ($appointment['Status'] === 'Completed') echo 'selected'; ?>>Completed</option>
            <option value="Cancelled" <?php if ($appointment['Status'] === 'Cancelled') echo 'selected'; ?>>Cancelled</option>
        </select>

        <button type="submit" class="btn"><i class="fas fa-save"></i> Update Appointment</button>
    </form>

    <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this appointment?');">
        <input type="hidden" name="cancel_appointment" value="1">
        <button type="submit" class="btn cancel-btn"><i class="fas fa-times-circle"></i> Cancel Appointment</button>
    </form>

    <p><a class="back-link" href="dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a></p>
</div>

</body>
</html>
