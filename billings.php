<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['doctor', 'nurse'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get user information
if ($role === 'doctor') {
    $stmt = $conn->prepare("SELECT u.*, d.Specialty FROM users u JOIN doctors d ON u.UserID = d.DoctorID WHERE u.UserID = ?");
} else {
    $stmt = $conn->prepare("SELECT u.*, n.Department FROM users u JOIN nurses n ON u.UserID = n.NurseID WHERE u.UserID = ?");
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get billing records
$billings = [];
$search = '';
$filter = '';

// Handle search and filter
if (isset($_GET['search'])) {
    $search = $_GET['search'];
}
if (isset($_GET['filter']) && $_GET['filter'] !== 'all') {
    $filter = $_GET['filter'];
}

// Base query
$query = "SELECT b.BillingID, b.AppointmentID, b.Amount, b.BillingDate,  
          b.PaidStatus, b.PaymentMethod, b.PaymentDate, b.Notes,
          p.Name as PatientName, a.ServiceType, a.AppointmentDate 
          FROM billings b
          JOIN appointments a ON b.AppointmentID = a.AppointmentID
          JOIN patients p ON a.PatientID = p.PatientID";

// Apply search
if (!empty($search)) {
    $search = mysqli_real_escape_string($conn, $search);
    $query .= " WHERE p.Name LIKE '%$search%' OR b.BillingID LIKE '%$search%'";
}

// Apply filter
if (!empty($filter)) {
    $whereClause = !empty($search) ? " AND" : " WHERE";
    if ($filter === 'paid') {
        $query .= "$whereClause b.PaidStatus = 'Paid'";
    } elseif ($filter === 'unpaid') {
        $query .= "$whereClause b.PaidStatus = 'Unpaid'";
    }
}

// Order by most recent
$query .= " ORDER BY b.BillingDate DESC";

$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        // Update amount to 180 if service is general and amount is 0
        if ($row['ServiceType'] === 'general' && $row['Amount'] == 0.00) {
            $updateAmountQuery = "UPDATE billings SET Amount = 180.00 WHERE BillingID = " . $row['BillingID'];
            mysqli_query($conn, $updateAmountQuery);
            $row['Amount'] = 180.00;
        }
        $billings[] = $row;
    }
}

// Handle billing detail submission
if (isset($_POST['submit_billing_details'])) {
    $billingId = $_POST['billing_id'];
    $itemType = $_POST['item_type'];
    $description = $_POST['description'];
    $quantity = $_POST['quantity'];
    $price = $_POST['price'];
    $subtotal = $quantity * $price;
    
    // Insert billing detail
    $insertQuery = "INSERT INTO billing_details (BillingID, ItemType, Description, Quantity, Price, Subtotal) 
                   VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $insertQuery);
    mysqli_stmt_bind_param($stmt, "issidi", $billingId, $itemType, $description, $quantity, $price, $subtotal);
    
    if (mysqli_stmt_execute($stmt)) {
        // Update the total amount in billings table
        $updateQuery = "UPDATE billings SET Amount = (SELECT SUM(Subtotal) FROM billing_details WHERE BillingID = ?) WHERE BillingID = ?";
        $updateStmt = mysqli_prepare($conn, $updateQuery);
        mysqli_stmt_bind_param($updateStmt, "ii", $billingId, $billingId);
        mysqli_stmt_execute($updateStmt);
        
        // Set success message
        $_SESSION['success_message'] = "Billing item added successfully!";
    } else {
        $_SESSION['error_message'] = "Error adding billing item: " . mysqli_error($conn);
    }
    
    // Redirect to prevent form resubmission
    header("Location: billings.php");
    exit();
}

// Handle payment update
if (isset($_POST['update_payment'])) {
    $billingId = $_POST['billing_id'];
    $paidStatus = $_POST['paid_status'];
    $paymentMethod = $_POST['payment_method'];
    $paymentDate = null;
    
    if ($paidStatus === 'Paid') {
        $paymentDate = date('Y-m-d'); // Set to current date
    }
    
    $updateQuery = "UPDATE billings SET PaidStatus = ?, PaymentMethod = ?, PaymentDate = ? WHERE BillingID = ?";
    $stmt = mysqli_prepare($conn, $updateQuery);
    mysqli_stmt_bind_param($stmt, "sssi", $paidStatus, $paymentMethod, $paymentDate, $billingId);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success_message'] = "Payment status updated successfully!";
    } else {
        $_SESSION['error_message'] = "Error updating payment status: " . mysqli_error($conn);
    }
    
    header("Location: billings.php");
    exit();
}

// Get billing details for a specific billing
function getBillingDetails($conn, $billingId) {
    $details = [];
    $query = "SELECT * FROM billing_details WHERE BillingID = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $billingId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $details[] = $row;
    }
    
    return $details;
}


// Set page title
$pageTitle = "Billing Management";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucfirst($role); ?> Dashboard - Binamira Clinic</title>
    <link rel="stylesheet" href="dashboard.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color:rgb(230, 14, 14);
            --secondary-color:rgb(237, 242, 246);
            --accent-color: #e74c3c;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
            --text-color: #333;
            --light-bg: #f5f7fa;
            --border-radius: 10px;
            --box-shadow: 0 5px 15px rgba(255, 252, 252, 0.05);
            --transition: all 0.3s ease;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light-bg);
            color: var(--text-color);
            overflow-x: hidden;
        }
        
        .dashboard {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background-color: var(--secondary-color);
            color: white;
            padding: 20px 0;
            transition: var(--transition);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }
        
        .profile {
            text-align: center;
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(141, 98, 98, 0.1);
        }
        
        .profile-img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 15px;
            border: 4px solid var(--primary-color);
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
        }
        
        .nav-menu {
            padding: 20px 0;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color:rgb(88, 90, 93);
            text-decoration: none;
            transition: var(--transition);
            border-left: 4px solid transparent;
        }
        
        
        
        .nav-item i {
            margin-right: 10px;
            font-size: 18px;
            width: 24px;
            text-align: center;
        }
        
        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: 280px;
            background-color: var(--light-bg);
            transition: var(--transition);
        }
        
        .header {
            background-color: white;
            padding: 20px;
            box-shadow: var(--box-shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 900;
        }
        
        .user-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .notification {
            position: relative;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
            border-radius: 50%;
            transition: var(--transition);
        }
        
        .notification:hover {
            background-color: #e9ecef;
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: var(--accent-color);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .logout-btn {
            background-color: var(--accent-color);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 50px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: 500;
        }
        
        .logout-btn:hover {
            background-color: #c0392b;
            transform: translateY(-2px);
        }
        
        /* Card Styles */
        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
            transition: var(--transition);
            overflow: hidden;
        }
        
        .card:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
            border-radius: var(--border-radius) var(--border-radius) 0 0 !important;
            padding: 15px 20px;
            font-weight: 600;
        }
        
        /* Badge Styles */
        .badge {
            padding: 6px 10px;
            font-weight: 500;
            font-size: 12px;
            border-radius: 50px;
        }
        
        /* Button Styles */
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
            border-radius: 50px;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-success {
            background-color: var(--success-color);
            border-color: var(--success-color);
        }
        
        .btn-danger {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
        }
        
        /* Table Styles */
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            background-color: #f8f9fa;
            color: #495057;
            font-weight: 600;
            border-bottom: 1px solid #dee2e6;
            white-space: nowrap;
        }
        
        .table td {
            vertical-align: middle;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: nowrap;
        }
        
        /* Alert Styles */
        .alert {
            border-radius: var(--border-radius);
            border-left: 4px solid;
            padding: 15px 20px;
        }
        
        .alert-success {
            border-left-color: var(--success-color);
            background-color: rgba(46, 204, 113, 0.1);
        }
        
        .alert-danger {
            border-left-color: var(--accent-color);
            background-color: rgba(231, 76, 60, 0.1);
        }
        
        /* Search and Filter Styles */
        .search-filter {
            background-color: white;
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            box-shadow: var(--box-shadow);
        }
        
        /* Modal Styles */
        .modal-header {
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            padding: 15px 20px;
        }
        
        .modal-content {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        /* Mobile Responsiveness */
        @media (max-width: 991px) {
            .sidebar {
                width: 80px;
                overflow: hidden;
            }
            
            .sidebar.expanded {
                width: 280px;
            }
            
            .profile-img {
                width: 50px;
                height: 50px;
                border-width: 2px;
            }
            
            .profile h3, .profile p, .nav-item span {
                opacity: 0;
                transition: opacity 0.3s;
            }
            
            .sidebar.expanded .profile h3, 
            .sidebar.expanded .profile p, 
            .sidebar.expanded .nav-item span {
                opacity: 1;
            }
            
            .nav-item {
                justify-content: center;
                padding: 12px;
            }
            
            .sidebar.expanded .nav-item {
                justify-content: flex-start;
                padding: 12px 20px;
            }
            
            .nav-item i {
                margin-right: 0;
                font-size: 20px;
            }
            
            .sidebar.expanded .nav-item i {
                margin-right: 10px;
            }
            
            .main-content {
                margin-left: 80px;
            }
            
            .toggle-sidebar {
                display: block;
            }
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
                padding: 15px;
            }
            
            .header h1 {
                font-size: 24px;
                margin-bottom: 10px;
            }
            
            .user-actions {
                align-self: flex-end;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .table-responsive {
                font-size: 14px;
            }
            
            .search-filter form {
                flex-direction: column;
            }
            
            .search-filter .col-md-6,
            .search-filter .col-md-4,
            .search-filter .col-md-2 {
                margin-bottom: 10px;
            }
        }
        
        @media (max-width: 576px) {
            .main-content {
                margin-left: 0;
                padding-top: 60px;
            }
            
            .sidebar {
                width: 0;
                padding: 0;
            }
            
            .sidebar.expanded {
                width: 100%;
                z-index: 1050;
            }
            
            .toggle-sidebar {
                position: fixed;
                top: 15px;
                left: 15px;
                z-index: 1060;
                background-color: var(--primary-color);
                color: white;
                border: none;
                width: 40px;
                height: 40px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            }
            
            .header {
                padding-left: 60px;
            }
        }
        
        /* Toggle Sidebar Button */
        .toggle-sidebar {
            display: none;
            cursor: pointer;
            border: none;
            background: transparent;
            padding: 0;
        }
        
        /* Custom responsive table for mobile */
        @media (max-width: 767px) {
            .mobile-table-card {
                margin-bottom: 15px;
                border: 1px solid #eee;
                border-radius: var(--border-radius);
                padding: 15px;
                background-color: white;
            }
            
            .mobile-table-card .row {
                margin-bottom: 8px;
            }
            
            .mobile-table-card .col-header {
                font-weight: 600;
                color: #495057;
            }
            
            .desktop-table {
                display: none;
            }
            
            .mobile-table {
                display: block;
            }
        }
        
        @media (min-width: 768px) {
            .desktop-table {
                display: block;
            }
            
            .mobile-table {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Toggle Sidebar Button -->
        <button class="toggle-sidebar" id="toggleSidebar">
            <i class="fas fa-bars"></i>
        </button>
        
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="profile">
                <?php
                $avatar = !empty($user['ProfileImage']) && file_exists($user['ProfileImage']) 
                    ? $user['ProfileImage'] 
                    : "https://ui-avatars.com/api/?name=" . urlencode($user['Name']) . "&background=0056b3&color=fff";
                ?>
                <img src="<?php echo $avatar; ?>" alt="Profile" class="profile-img">
                <h3><?php echo htmlspecialchars($user['Name']); ?></h3>
                <p><?php echo htmlspecialchars($role === 'doctor' ? $user['Specialty'] : $user['Department']); ?></p>
            </div>
            
            <div class="nav-menu">
                <a href="appointments.php" class="nav-item">
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
                <?php if ($role === 'nurse' || $role === 'doctor'): ?>
                <a href="billings.php" class="nav-item active">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Billings</span>
                </a>
                <?php endif; ?>
                <a href="messages.php" class="nav-item">
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
                <h1>Profile</h1>
                <div class="user-actions">
                  
                    <a href="logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
            
            <div class="container-fluid p-4">
                <!-- Success and Error Messages -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php 
                            echo $_SESSION['success_message']; 
                            unset($_SESSION['success_message']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php 
                            echo $_SESSION['error_message']; 
                            unset($_SESSION['error_message']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Search and Filter Form -->
                <div class="search-filter mb-4">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-6">
                            <div class="input-group">
                                <span class="input-group-text bg-white">
                                    <i class="fas fa-search text-muted"></i>
                                </span>
                                <input type="text" class="form-control" placeholder="Search by patient name or billing ID" name="search" value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-primary" type="submit">
                                    Search
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text bg-white">
                                    <i class="fas fa-filter text-muted"></i>
                                </span>
                                <select class="form-select" name="filter" onchange="this.form.submit()">
                                    <option value="all" <?php echo ($filter === '') ? 'selected' : ''; ?>>All Billings</option>
                                    <option value="paid" <?php echo ($filter === 'paid') ? 'selected' : ''; ?>>Paid</option>
                                    <option value="unpaid" <?php echo ($filter === 'unpaid') ? 'selected' : ''; ?>>Unpaid</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <a href="billings.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-sync-alt me-1"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- Billings Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Billing Records</h5>
                        <span class="badge bg-light text-dark">
                            <i class="fas fa-file-invoice me-1"></i>
                            <?php echo count($billings); ?> records
                        </span>
                    </div>
                    <div class="card-body p-0">
                        <!-- Desktop Table View -->
                        <div class="desktop-table">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Patient</th>
                                            <th>Service</th>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($billings)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center py-4">
                                                    <i class="fas fa-folder-open text-muted mb-3" style="font-size: 48px;"></i>
                                                    <p class="mb-0">No billing records found</p>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($billings as $billing): ?>
                                                <tr>
                                                    <td>#<?php echo htmlspecialchars($billing['BillingID']); ?></td>
                                                    <td><?php echo htmlspecialchars($billing['PatientName']); ?></td>
                                                    <td><?php echo htmlspecialchars($billing['ServiceType']); ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($billing['BillingDate'])); ?></td>
                                                    <td><strong>₱<?php echo number_format($billing['Amount'], 2); ?></strong></td>
                                                    <td>
                                                        <span class="badge <?php echo ($billing['PaidStatus'] === 'Paid') ? 'bg-success' : 'bg-danger'; ?>">
                                                            <?php echo ($billing['PaidStatus'] === 'Paid') ? '<i class="fas fa-check-circle me-1"></i>' : '<i class="fas fa-exclamation-circle me-1"></i>'; ?>
                                                            <?php echo htmlspecialchars($billing['PaidStatus']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                   <div class="action-buttons justify-content-center">
    <a href="view_billing_details.php?id=<?php echo $billing['BillingID']; ?>" class="btn btn-sm btn-info text-white" title="View Details">
        <i class="fas fa-eye"></i>
    </a>
    <a href="add_item.php?id=<?php echo $billing['BillingID']; ?>" class="btn btn-sm btn-primary" title="Add Item">
        <i class="fas fa-plus"></i>
    </a>
    <a href="update_payment.php?id=<?php echo $billing['BillingID']; ?>" class="btn btn-sm btn-success" title="Update Payment">
        <i class="fas fa-money-bill-wave"></i>
    </a>
</div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        