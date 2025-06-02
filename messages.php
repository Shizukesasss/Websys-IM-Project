<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in and is a doctor or nurse
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'doctor' && $_SESSION['role'] !== 'nurse')) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get user information based on role
if ($role === 'doctor') {
    $stmt = $conn->prepare("SELECT u.*, d.Specialty FROM users u JOIN doctors d ON u.UserID = d.DoctorID WHERE u.UserID = ?");
} else {
    $stmt = $conn->prepare("SELECT u.*, n.Department FROM users u JOIN nurses n ON u.UserID = n.NurseID WHERE u.UserID = ?");
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get statistics for the dashboard
$today = date('Y-m-d');

$messages_stmt = $conn->prepare("SELECT * FROM contact_messages ORDER BY DateSent DESC");
$messages_stmt->execute();
$messages = $messages_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<?php if (isset($_SESSION['success_message'])): ?>
    <div style="background-color: #d4edda; color: #155724; padding: 10px; margin-bottom: 15px; border: 1px solid #c3e6cb;">
        <?php 
            echo $_SESSION['success_message']; 
            unset($_SESSION['success_message']); 
        ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div style="background-color: #f8d7da; color: #721c24; padding: 10px; margin-bottom: 15px; border: 1px solid #f5c6cb;">
        <?php 
            echo $_SESSION['error_message']; 
            unset($_SESSION['error_message']); 
        ?>
    </div>
<?php endif; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucfirst($role); ?> Dashboard - Bhamira Clinic</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css">
    <style>
    /* Cards Section */
.cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
    transition: transform 0.3s;
}

.card:hover {
    transform: translateY(-5px);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.card-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.card-icon.patients {
    background-color: var(--success);
}

.card-icon.appointments {
    background-color: var(--info);
}

.card-icon.prescriptions {
    background-color: var(--warning);
}

.card h3 {
    font-size: 14px;
    color: #666;
    margin-bottom: 5px;
}

.card h2 {
    font-size: 24px;
    color: var(--dark);
}

/* Appointments Table */
.appointments {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.section-header h2 {
    font-size: 20px;
    color: var(--dark);
}

.btn {
    background-color: var(--primary);
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 5px;
    cursor: pointer;
    transition: background-color 0.3s;
    font-size: 14px;
}

.btn:hover {
    background-color: var(--primary-dark);
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    text-align: left;
    padding: 12px 15px;
    background-color: #f8f9fa;
    color: #666;
    font-weight: 500;
}

td {
    padding: 12px 15px;
    border-bottom: 1px solid #eee;
}

.status {
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.status.pending {
    background-color: rgba(247, 127, 0, 0.1);
    color: var(--warning);
}

.status.completed {
    background-color: rgba(42, 157, 143, 0.1);
    color: var(--success);
}

.status.cancelled {
    background-color: rgba(217, 4, 41, 0.1);
    color: var(--danger);
}

.action-btn {
    background: none;
    border: none;
    color: var(--primary);
    cursor: pointer;
    margin-right: 10px;
}
table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px 15px;
            border-bottom: 1px solid #ccc;
        }
        th {
            background-color: #f8f8f8;
            text-align: left;
        }
        .reply-btn {
            background-color:rgb(255, 0, 0);
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .reply-btn:hover {
            background-color:rgb(190, 18, 26);
        }

</style>

</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="profile">
                  <?php
$avatar = !empty($user['ProfileImage']) && file_exists($user['ProfileImage']) 
    ? $user['ProfileImage'] 
    : "https://ui-avatars.com/api/?name=" . urlencode($user['Name']) . "&background=e63946&color=fff";
?>
<img src="<?php echo $avatar; ?>" alt="Profile" class="profile-img">
                <h3><?php echo htmlspecialchars($user['Name']); ?></h3>
                <p><?php echo htmlspecialchars($role === 'doctor' ? $user['Specialty'] : $user['Department']); ?></p>
            </div>
            
            <div class="nav-menu">
                <a href="dashboard.php" class="nav-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Appointments</span>
                </a>
                <a href="patients.php" class="nav-item">
                    <i class="fas fa-user-injured"></i>
                    <span>Patients</span>
                </a>
                
                <a href="prescriptions.php" class="nav-item">
                    <i class="fas fa-prescription-bottle-alt"></i>
                    <span>Prescriptions</span>
                </a>
                <a href="medical_records.php" class="nav-item">
                    <i class="fas fa-file-medical"></i>
                    <span>Medical Records</span>
                </a>
                   <a href="medicines.php" class="nav-item">
                    <i class="fas fa-pills"></i>
                    <span>Medicines</span>
                </a>
                <?php if ($role === 'nurse'): ?>
                <a href="billings.php" class="nav-item">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Billings</span>
                </a>
                <?php endif; ?>
                <a href="messages.php" class="nav-item active">
                    <i class="fas fa-envelope"></i>
                    <span>Messages</span>
                </a>
                
                <a href="profile_settings.php" class="nav-item">
                    <i class="fas fa-user-cog"></i>
                    <span>Profile Settings</span>
                </a>
            </div>
        </div>
        
     <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1>Messages</h1>
                <div class="user-actions">
                    
                    <a href="logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
            
<h2>Contact Messages</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Fullname</th>
                <th>Email</th>
                <th>Subject</th>
                <th>Message</th>
                <th>Received At</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($messages as $msg): ?>
            <tr>
                <td><?= $msg['MessageID'] ?></td>
                <td><?= htmlspecialchars($msg['FullName']) ?></td>
                <td><?= htmlspecialchars($msg['Email']) ?></td>
                <td><?= htmlspecialchars($msg['Subject']) ?></td>
                <td><?= htmlspecialchars($msg['Message']) ?></td>
                <td><?= $msg['DateSent'] ?></td>

                <td>
                    <form method="post" action="send_reply.php" style="display:inline;">
                        <input type="hidden" name="email" value="<?= htmlspecialchars($msg['Email']) ?>">
                        <input type="hidden" name="name" value="<?= htmlspecialchars($msg['FullName']) ?>">

                        <input type="text" name="reply" placeholder="Type your reply" required>
                        <button class="reply-btn" type="submit">Send Reply</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

            
           
</body>
</html>