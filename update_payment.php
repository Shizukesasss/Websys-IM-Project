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

// Get billing ID from URL
if (!isset($_GET['id'])) {
    header("Location: billings.php");
    exit();
}

$billing_id = $_GET['id'];

// Get billing information
$billing_query = "SELECT b.*, p.Name as PatientName 
                 FROM billings b
                 JOIN appointments a ON b.AppointmentID = a.AppointmentID
                 JOIN patients p ON a.PatientID = p.PatientID
                 WHERE b.BillingID = ?";
$stmt = $conn->prepare($billing_query);
$stmt->bind_param("i", $billing_id);
$stmt->execute();
$billing = $stmt->get_result()->fetch_assoc();

if (!$billing) {
    $_SESSION['error_message'] = "Billing record not found";
    header("Location: billings.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paid_status = $_POST['paid_status'];
    $payment_method = $_POST['payment_method'];
    $payment_date = ($paid_status === 'Paid') ? date('Y-m-d') : null;

    // Update payment status
    $update_query = "UPDATE billings SET PaidStatus = ?, PaymentMethod = ?, PaymentDate = ? 
                    WHERE BillingID = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("sssi", $paid_status, $payment_method, $payment_date, $billing_id);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Payment status updated successfully!";
        header("Location: view_billing_details.php?id=" . $billing_id);
        exit();
    } else {
        $_SESSION['error_message'] = "Error updating payment status: " . $conn->error;
    }
}

// Set page title
$pageTitle = "Update Payment Status";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Binamira Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
</head>
<body>
    

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3"><i class="fas fa-money-bill-wave me-2"></i> Update Payment Status</h1>
            <a href="billings.php?id=<?php echo $billing_id; ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Billing
            </a>
        </div>

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

        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Update Payment for Invoice #<?php echo $billing_id; ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="patient_name" class="form-label">Patient Name</label>
                            <input type="text" class="form-control" id="patient_name" 
                                   value="<?php echo htmlspecialchars($billing['PatientName']); ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="amount" class="form-label">Amount Due</label>
                            <input type="text" class="form-control" id="amount" 
                                   value="₱<?php echo number_format($billing['Amount'], 2); ?>" readonly>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="paid_status" class="form-label">Payment Status</label>
                            <select class="form-select" id="paid_status" name="paid_status" required>
                                <option value="Unpaid" <?php echo ($billing['PaidStatus'] === 'Unpaid') ? 'selected' : ''; ?>>Unpaid</option>
                                <option value="Paid" <?php echo ($billing['PaidStatus'] === 'Paid') ? 'selected' : ''; ?>>Paid</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="payment_method" class="form-label">Payment Method</label>
                            <select class="form-select" id="payment_method" name="payment_method" required>
                                <option value="">Select payment method</option>
                                <option value="Cash" <?php echo ($billing['PaymentMethod'] === 'Cash') ? 'selected' : ''; ?>>Cash</option>
                                <option value="Credit Card" <?php echo ($billing['PaymentMethod'] === 'Credit Card') ? 'selected' : ''; ?>>Credit Card</option>
                                <option value="GCash" <?php echo ($billing['PaymentMethod'] === 'GCash') ? 'selected' : ''; ?>>GCash</option>
                                <option value="Bank Transfer" <?php echo ($billing['PaymentMethod'] === 'Bank Transfer') ? 'selected' : ''; ?>>Bank Transfer</option>
                                <option value="Insurance" <?php echo ($billing['PaymentMethod'] === 'Insurance') ? 'selected' : ''; ?>>Insurance</option>
                            </select>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        If marking as paid, the payment date will be set to today's date automatically.
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Update Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>