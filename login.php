<?php
session_start();

// Redirect if already logged in (doctors/nurses only)
if (isset($_SESSION['user_id']) && in_array($_SESSION['role'], ['doctor', 'nurse'])) {
    header("Location: dashboard.php");
    exit();
}

// Handle errors
$login_error = $_SESSION['login_error'] ?? null;
unset($_SESSION['login_error']);
?>

<!DOCTYPE html>
<html lang="en">
<!-- Keep your existing login form (only for doctors/nurses) -->
<!-- ... (rest of your existing login.php content remains exactly the same) ... -->
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Binamira Medical Clinic - Login / Sign Up</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    .auth-container {
        max-width: 400px;
        margin: 20px auto; /* Reduced from 40px */
        background-color: white;
        padding: 20px; /* Reduced from 30px */
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        position: relative;
        z-index: 1;
    }

    .auth-container h2 {
        text-align: center;
        color: #7e0a20;
        margin-bottom: 15px; /* Reduced from 20px */
        font-size: 24px; /* Added for better proportion */
    }

    .auth-container form {
        display: flex;
        flex-direction: column;
        gap: 10px; /* Added for consistent spacing */
    }

    .auth-container label {
        margin-bottom: 3px; /* Reduced from 5px */
        font-weight: bold;
        font-size: 14px; /* Slightly smaller labels */
    }

    .auth-container input,
    .auth-container select {
        padding: 8px; /* Reduced from 10px */
        margin-bottom: 10px; /* Reduced from 15px */
        border: 1px solid #ccc;
        border-radius: 6px; /* Slightly smaller radius */
        font-size: 14px;
    }

    .auth-container button {
        background-color: #8e2b2b;
        color: white;
        border: none;
        padding: 8px; /* Reduced from 10px */
        font-weight: bold;
        border-radius: 6px;
        cursor: pointer;
        transition: background-color 0.3s ease;
        margin-top: 5px; /* Added space above button */
        font-size: 14px;
    }

    .auth-toggle {
        text-align: center;
        margin-top: 8px; /* Reduced from 10px */
        font-size: 14px;
    }

    /* Rest of your existing styles... */
    body, html {
        margin: 0;
        padding: 0;
        height: 100%;
        overflow: hidden;
    }

    .background-video {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        z-index: -1;
    }

    header {
        position: relative;
        width: 100%;
        padding: 10px 0; /* Reduced padding */
        background-color: transparent;
        text-align: center;
        z-index: 1;
    }

    .header-content {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px; /* Reduced gap */
    }

    .clinic-logo {
        width: 350px;
        height: 350px;
        object-fit: contain;
        margin-bottom: -30px; /* Adjusted for tighter spacing */
        margin-left: 15px;
    }

    .error-message {
        color: #ff0000;
        background-color: #ffeeee;
        padding: 8px; /* Reduced padding */
        border-radius: 5px;
        margin-bottom: 12px; /* Reduced margin */
        text-align: center;
        border: 1px solid #ffcccc;
        min-height: 18px; /* Slightly smaller */
        font-size: 13px;
        display: <?php echo $login_error ? 'block' : 'none'; ?>;
    }
  </style>
</head>
<body>

  <div class="header-content">
    <img src="images/logoblack23.png" alt="Clinic Logo" class="clinic-logo">
  </div>

<!-- Login Form -->
<div class="auth-container" id="login-form">
  <h2>Login</h2>
  <?php if ($login_error): ?>
    <div class="error-message">
      <?php echo htmlspecialchars($login_error); ?>
    </div>
  <?php endif; ?>
  <form action="process_login.php" method="post" id="loginForm" onsubmit="return validateForm()">
    <label for="login-email">Email:</label>
    <input type="email" name="email" id="login-email" required>

    <label for="login-password">Password:</label>
    <input type="password" name="password" id="login-password" required>

    <button type="submit">Login</button>
  </form>
  <div class="auth-toggle">
    Don't have an account? <a href="#" onclick="toggleForm()">Sign Up</a>
  </div>
</div>

<!-- Signup Form -->
<div class="auth-container" id="signup-form" style="display:none;">
  <h2>Sign Up</h2>
  <form action="signup.php" method="post">
    <label for="signup-role">Registering as:</label>
    <select name="role" id="signup-role" required onchange="toggleRoleFields()">
      <option value="">Select Role</option>
      <option value="doctor">Doctor</option>
      <option value="nurse">Nurse</option>
    </select>

    <label for="signup-name">Full Name:</label>
    <input type="text" name="name" id="signup-name" required>

    <label for="phone">Phone Number:</label>
    <input type="tel" name="phone" id="phone" required pattern="[0-9]{10,15}" placeholder="e.g. 09123456789">

    <label for="signup-email">Email:</label>
    <input type="email" name="email" id="signup-email" required>

    <label for="signup-password">Password:</label>
    <input type="password" name="password" id="signup-password" required>

    <!-- Doctor Specialty Dropdown -->
    <div id="doctor-fields" style="display:none;">
      <label for="specialty">Specialty:</label>
      <select name="specialty" id="specialty">
        <option value="">Select Specialty</option>
        <option value="Family Medicine">Family Medicine</option>
        <option value="Primary Care">Primary Care</option>
        <option value="Geriatic Medicine">Geriatic Medicine</option>
        <option value="General Medicine">General Medicine</option>
        <option value="Allergists">Allergists</option>
        <option value="Radiologists">Radiologists</option>
      </select>
    </div>

    <div id="nurse-fields" style="display:none;">
      <label for="department">Department:</label>
      <select name="department" id="department">
        <option value="">Select Department</option>
        <option value="Family Medicine Dpt.">Family Medicine Dpt.</option>
        <option value="Primary Care Dpt.">Primary Care Dpt.</option>
        <option value="Radiologists Dpt.">Radiologists Dpt.</option>
      </select>
    </div>

    <button type="submit">Sign Up</button>
  </form>
  <div class="auth-toggle">
    Already have an account? <a href="#" onclick="toggleForm()">Login</a>
  </div>
</div>



<script>
// Prevent form resubmission on refresh
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}

// Disable form resubmission
document.getElementById('loginForm').addEventListener('submit', function(e) {
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = 'Logging in...';
});

function toggleForm() {
    const loginForm = document.getElementById("login-form");
    const signupForm = document.getElementById("signup-form");

    if (loginForm.style.display === "none" || loginForm.style.display === "") {
        loginForm.style.display = "none";
        signupForm.style.display = "block";
    } else {
        loginForm.style.display = "block";
        signupForm.style.display = "none";
    }
}

function validateForm() {
    // Simple client-side validation
    const email = document.getElementById('login-email').value;
    const password = document.getElementById('login-password').value;
    const errorDiv = document.querySelector('.error-message');
    
    if (!email || !password) {
        if (!errorDiv) {
            const form = document.getElementById('loginForm');
            const newErrorDiv = document.createElement('div');
            newErrorDiv.className = 'error-message';
            newErrorDiv.textContent = 'Please fill in all fields.';
            form.prepend(newErrorDiv);
        } else {
            errorDiv.style.display = 'block';
            errorDiv.textContent = 'Please fill in all fields.';
        }
        return false;
    }
    return true;
}

function toggleRoleFields() {
    const role = document.getElementById("signup-role").value;
    document.getElementById("doctor-fields").style.display = role === "doctor" ? "block" : "none";
    document.getElementById("nurse-fields").style.display = role === "nurse" ? "block" : "none";
}
</script>

</body>
</html>