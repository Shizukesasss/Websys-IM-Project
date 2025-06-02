<?php
require_once __DIR__ . '/db_connect.php';
$signupMessage = "";
$isSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $phone = $_POST['phone'];
    $specialty = $_POST['specialty'] ?? null;
    $department = $_POST['department'] ?? null;

    $sql = "INSERT INTO users (role, name, email, password, phone) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $role, $name, $email, $password, $phone);

    if ($stmt->execute()) {
        $userId = $conn->insert_id;

        if ($role === 'doctor') {
            $doctorSql = "INSERT INTO doctors (DoctorID, Specialty) VALUES (?, ?)";
            $doctorStmt = $conn->prepare($doctorSql);
            $doctorStmt->bind_param("is", $userId, $specialty);
            $doctorStmt->execute();
            $doctorStmt->close();
        } elseif ($role === 'nurse') {
            $nurseSql = "INSERT INTO nurses (NurseID, Department) VALUES (?, ?)";
            $nurseStmt = $conn->prepare($nurseSql);
            $nurseStmt->bind_param("is", $userId, $department);
            $nurseStmt->execute();
            $nurseStmt->close();
        }

        $signupMessage = "Sign up successful!";
        $isSuccess = true;
    } else {
        $signupMessage = "Error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Binamira Clinic</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <style>
        :root {
            --primary: #7e0a20;
            --primary-dark: #5e0717;
            --primary-light: #ae1a30;
            --white: #ffffff;
            --light-gray: #f7f7f7;
            --gray: #e2e2e2;
            --dark-gray: #646464;
            --success: #2e7d32;
            --success-bg: #e8f5e9;
            --error: #c62828;
            --error-bg: #ffebee;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--light-gray);
            color: #333;
            line-height: 1.6;
        }

        .page-container {
            display: flex;
            min-height: 100vh;
        }

        .signup-sidebar {
            display: none;
            background: var(--primary);
            color: var(--white);
            width: 40%;
            padding: 60px 40px;
        }

        .signup-form-container {
            width: 100%;
            padding: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .signup-form {
            width: 100%;
            max-width: 500px;
            background: var(--white);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo img {
            width: 140px;
            transition: transform 0.3s ease;
        }

        .logo img:hover {
            transform: scale(1.05);
        }

        h2 {
            font-size: 28px;
            text-align: center;
            margin-bottom: 30px;
            color: var(--primary);
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }

        input, select {
            width: 100%;
            padding: 12px 15px;
            font-size: 15px;
            border: 1px solid var(--gray);
            border-radius: 8px;
            background-color: var(--white);
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        input:focus, select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(126, 10, 32, 0.15);
        }

        button {
            width: 100%;
            background: var(--primary);
            color: var(--white);
            font-size: 16px;
            font-weight: 600;
            padding: 14px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        button:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(126, 10, 32, 0.2);
        }

        button:active {
            transform: translateY(0);
        }

        .success-message,
        .error-message {
            padding: 18px;
            border-radius: 8px;
            font-size: 16px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .success-message {
            background-color: var(--success-bg);
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .error-message {
            background-color: var(--error-bg);
            color: var(--error);
            border-left: 4px solid var(--error);
        }

        .login-link {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }

        .login-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--primary);
            color: var(--white);
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }

        .login-button:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(126, 10, 32, 0.2);
        }

        .role-specific {
            display: none;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-row {
            display: flex;
            gap: 20px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .icon-input {
            position: relative;
        }

        .icon-input i {
            position: absolute;
            top: 50%;
            left: 15px;
            transform: translateY(-50%);
            color: var(--dark-gray);
        }

        .icon-input input, 
        .icon-input select {
            padding-left: 45px;
        }

        .signup-text {
            text-align: center;
            margin-top: 20px;
            color: var(--dark-gray);
        }

        .signup-text a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .signup-text a:hover {
            text-decoration: underline;
        }

        @media (min-width: 992px) {
            .signup-sidebar {
                display: flex;
                flex-direction: column;
                justify-content: center;
            }

            .signup-form-container {
                width: 60%;
            }

            .signup-sidebar h1 {
                font-size: 36px;
                margin-bottom: 20px;
            }

            .signup-sidebar p {
                font-size: 18px;
                opacity: 0.9;
                margin-bottom: 30px;
            }

            .features {
                margin-top: 40px;
            }

            .feature {
                display: flex;
                align-items: center;
                margin-bottom: 20px;
            }

            .feature-icon {
                background: rgba(255, 255, 255, 0.2);
                width: 40px;
                height: 40px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-right: 15px;
            }

            .feature-text {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <div class="signup-sidebar">
            <h1>Welcome Nurse/Doctor to Binamira Clinic</h1>
            <p>Join our healthcare platform and experience premium medical services tailored to your needs.</p>
            
            <div class="features">
                <div class="feature">
                    <div class="feature-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="feature-text">Easy appointment managing</div>
                </div>
                <div class="feature">
                    <div class="feature-icon">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <div class="feature-text">Specialist doctors</div>
                </div>
                <div class="feature">
                    <div class="feature-icon">
                        <i class="fas fa-notes-medical"></i>
                    </div>
                    <div class="feature-text">Electronic medical records</div>
                </div>
                <div class="feature">
                    <div class="feature-icon">
                        <i class="fas fa-pills"></i>
                    </div>
                    <div class="feature-text">Prescription management</div>
                </div>
            </div>
        </div>

        <div class="signup-form-container">
            <div class="signup-form">
                <div class="logo">
                    <img src="image/logoblack23.png" alt="Binamira Clinic Logo">
                </div>

                <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isSuccess): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $signupMessage; ?>
                    </div>
                <?php endif; ?>

                <?php if ($isSuccess): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i>
                        <?php echo $signupMessage; ?> Your account has been created successfully.
                    </div>
                    <div class="login-link">
                        <a href="login.php" class="login-button">
                            <i class="fas fa-sign-in-alt"></i> Go to Login
                        </a>
                    </div>
                <?php else: ?>
                    <h2>Create Your Account</h2>
                    <form method="POST">
                        <div class="form-group icon-input">
                            <label for="role">Your Role</label>
                            <i class="fas fa-user-tag"></i>
                            <select name="role" id="role" required>
                                <option value="">Select Role</option>
                                <option value="patient" <?php echo ($role ?? '') === 'patient' ? 'selected' : ''; ?>>Patient</option>
                                <option value="doctor" <?php echo ($role ?? '') === 'doctor' ? 'selected' : ''; ?>>Doctor</option>
                                <option value="nurse" <?php echo ($role ?? '') === 'nurse' ? 'selected' : ''; ?>>Nurse</option>
                            </select>
                        </div>

                        <div class="form-group icon-input">
                            <label for="name">Full Name</label>
                            <i class="fas fa-user"></i>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name ?? '') ?>" placeholder="Enter your full name" required>
                        </div>

                        <div class="form-group icon-input">
                            <label for="email">Email Address</label>
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email ?? '') ?>" placeholder="Enter your email" required>
                        </div>

                        <div class="form-group icon-input">
                            <label for="password">Password</label>
                            <i class="fas fa-lock"></i>
                            <input type="password" id="password" name="password" placeholder="Create a strong password" required>
                        </div>

                        <div class="form-group icon-input">
                            <label for="phone">Phone Number</label>
                            <i class="fas fa-phone"></i>
                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($phone ?? '') ?>" placeholder="Enter your phone number" required>
                        </div>

                        <div id="doctorFields" class="role-specific">
                            <div class="form-group icon-input">
                                <label for="specialty">Medical Specialty</label>
                                <i class="fas fa-stethoscope"></i>
                                <input type="text" name="specialty" id="specialty" value="<?php echo htmlspecialchars($specialty ?? '') ?>" placeholder="Enter your medical specialty">
                            </div>
                        </div>

                        <div id="nurseFields" class="role-specific">
                            <div class="form-group icon-input">
                                <label for="department">Department</label>
                                <i class="fas fa-hospital"></i>
                                <input type="text" name="department" id="department" value="<?php echo htmlspecialchars($department ?? '') ?>" placeholder="Enter your department">
                            </div>
                        </div>

                        <button type="submit">
                            <i class="fas fa-user-plus"></i> Sign Up
                        </button>

                        <div class="signup-text">
                            Already have an account? <a href="login.php">Login here</a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function toggleRoleFields(role) {
            document.getElementById('doctorFields').style.display = 'none';
            document.getElementById('nurseFields').style.display = 'none';

            if (role === 'doctor') {
                document.getElementById('doctorFields').style.display = 'block';
            } else if (role === 'nurse') {
                document.getElementById('nurseFields').style.display = 'block';
            }
        }

        document.getElementById('role')?.addEventListener('change', function() {
            toggleRoleFields(this.value);
        });

        window.addEventListener('DOMContentLoaded', function() {
            toggleRoleFields(document.getElementById('role').value);
        });
    </script>
</body>
</html>