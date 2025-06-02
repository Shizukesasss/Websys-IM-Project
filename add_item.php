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
    $item_type = $_POST['item_type'];
    $description = $_POST['description'];
    $quantity = $_POST['quantity'];
    $price = $_POST['price'];
    $subtotal = $quantity * $price;

    // Insert new item
    $insert_query = "INSERT INTO billing_details (BillingID, ItemType, Description, Quantity, Price, Subtotal) 
                    VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("issidi", $billing_id, $item_type, $description, $quantity, $price, $subtotal);

    if ($stmt->execute()) {
        // Update total amount in billings table
        $update_query = "UPDATE billings SET Amount = (SELECT SUM(Subtotal) FROM billing_details WHERE BillingID = ?) 
                        WHERE BillingID = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("ii", $billing_id, $billing_id);
        $stmt->execute();

        $_SESSION['success_message'] = "Item added successfully!";
        header("Location: view_billing_details.php?id=" . $billing_id);
        exit();
    } else {
        $_SESSION['error_message'] = "Error adding item: " . $conn->error;
    }
}

// Set page title
$pageTitle = "Add Billing Item";

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
            <h1 class="h3"><i class="fas fa-plus-circle me-2"></i> Add Billing Item</h1>
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
                <h5 class="mb-0">Add New Item to Invoice #<?php echo $billing_id; ?></h5>
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
                            <label for="current_amount" class="form-label">Current Amount</label>
                            <input type="text" class="form-control" id="current_amount" 
                                   value="₱<?php echo number_format($billing['Amount'], 2); ?>" readonly>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="item_type" class="form-label">Item Type</label>
                            <select class="form-select" id="item_type" name="item_type" required>
                                <option value="">Select item type</option>
                                <option value="Consultation">Consultation</option>
                                <option value="Medication">Medication</option>
                                <option value="Laboratory">Laboratory Test</option>
                                <option value="Procedure">Procedure</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label for="description" class="form-label">Description</label>
                            <input type="text" class="form-control" id="description" name="description" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="quantity" class="form-label">Quantity</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" min="1" value="1" required>
                        </div>
                        <div class="col-md-4">
                            <label for="price" class="form-label">Price (₱)</label>
                            <input type="number" class="form-control" id="price" name="price" min="0" step="0.01" required>
                        </div>
                        <div class="col-md-4">
                            <label for="subtotal" class="form-label">Subtotal (₱)</label>
                            <input type="text" class="form-control" id="subtotal" readonly>
                        </div>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Save Item
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Calculate subtotal when quantity or price changes
        document.getElementById('quantity').addEventListener('input', calculateSubtotal);
        document.getElementById('price').addEventListener('input', calculateSubtotal);

        function calculateSubtotal() {
            const quantity = parseFloat(document.getElementById('quantity').value) || 0;
            const price = parseFloat(document.getElementById('price').value) || 0;
            const subtotal = quantity * price;
            document.getElementById('subtotal').value = subtotal.toFixed(2);
        }

        // Initialize calculation on page load
        calculateSubtotal();
    </script>
</body>
</html>