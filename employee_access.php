<?php
session_start();

// Redirect to dashboard if already authenticated as clinic staff
if (isset($_SESSION["is_clinic_staff"])) {
    header("Location: login.php"); // Proceed to doctor/nurse login
    exit();
}

// Display error if exists
$error_message = $_SESSION['employee_login_error'] ?? '';
unset($_SESSION['employee_login_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Clinic Staff Verification</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #8e2b2b, #7e0a20);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
        }
        .login-container {
            background: white;
            width: 100%;
            max-width: 400px;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            text-align: center;
        }
        h2 {
            color: #7e0a20;
            margin-bottom: 25px;
            font-size: 24px;
        }
        .error-message {
            color: #ff4444;
            background: #ffebee;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #ff4444;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 16px;
        }
        input[type="submit"] {
            width: 100%;
            background: #8e2b2b;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.3s;
        }
        input[type="submit"]:hover {
            background: #7e0a20;
        }
        .clinic-logo {
            max-width: 150px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <img src="images/logoblack23.png" alt="Clinic Logo" class="clinic-logo">
        <h2>Clinic Staff Verification</h2>
        
        <?php if (!empty($error_message)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <form method="POST" action="employee_gate.php">
            <input type="text" name="username" placeholder="Staff ID" required>
            <input type="password" name="password" placeholder="Access Code" required>
            <input type="submit" value="Verify & Proceed">
        </form>

        <p style="margin-top: 20px; color: #666; font-size: 14px;">
            <i class="fas fa-lock"></i> This portal is restricted to authorized clinic personnel only.
        </p>
    </div>
</body>
</html>