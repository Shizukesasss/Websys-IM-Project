<?php
session_start();
require_once 'db_connect.php';

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

// Get billing information with patient details
$billing_query = "SELECT b.*, p.Name as PatientName, a.ServiceType, a.AppointmentDate 
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

// Set page title
$pageTitle = "Billing Details";

// Based on database structure, we display the single billing item with proper data
$billing_item = [
    'Description' => $billing['Description'] ?? 'General Consultation',
    'Price' => $billing['Price'] > 0 ? $billing['Price'] : $billing['Amount'],
    'Subtotal' => $billing['Subtotal'] > 0 ? $billing['Subtotal'] : $billing['Amount']
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Binamira Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        .invoice-header {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .invoice-table th {
            background-color: #e74c3c;
            color: white;
        }
        .total-row {
            font-weight: bold;
            background-color: #f8f9fa;
        }
        .status-badge {
            font-size: 1rem;
            padding: 8px 15px;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            .container {
                width: 100%;
                max-width: 100%;
                padding: 0;
                margin: 0;
            }
            .card {
                border: none !important;
            }
            .card-header {
                background-color: #f8f9fa !important;
                color: #000 !important;
                border-bottom: 1px solid #ddd;
            }
            .invoice-table th {
                background-color: #f8f9fa !important;
                color: #000 !important;
                border-bottom: 2px solid #ddd;
            }
            .alert {
                border: 1px solid #ddd !important;
                background-color: transparent !important;
                color: #000 !important;
            }
            .print-mb-4 {
                margin-bottom: 1.5rem !important;
            }
            body {
                font-size: 14px;
            }
            .print-header {
                text-align: center;
                margin-bottom: 20px;
            }
            .print-header h2 {
                margin-bottom: 5px;
            }
            .print-header p {
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="print-header d-none d-print-block">
        <h2>Binamira Clinic</h2>
        <p>Bacacay, Albay</p>
        <p>Phone: (052) 123-4567</p>
        <h3 class="mt-3">OFFICIAL RECEIPT</h3>
    </div>
    


    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <h1 class="h3"><i class="fas fa-file-invoice me-2"></i> Billing Details</h1>
            <div>
                <button onclick="window.print();" class="btn btn-primary me-2">
                    <i class="fas fa-print me-1"></i> Print Invoice
                </button>
                <a href="billings.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to Billings
                </a>
            </div>
        </div>

        <!-- Invoice Header -->
        <div class="invoice-header print-mb-4">
            <div class="row">
                <div class="col-md-6 d-print-none">
                    <h4>Binamira Clinic</h4>
                    <p class="mb-1">Bacacay, Albay</p>
                    <p class="mb-1">Phone: (052) 123-4567</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <h4>Invoice #<?php echo $billing['BillingID']; ?></h4>
                    <p class="mb-1"><strong>Date:</strong> <?php echo date('F j, Y', strtotime($billing['BillingDate'])); ?></p>
                    <p class="mb-1"><strong>Status:</strong> 
                        <span class="badge <?php echo ($billing['PaidStatus'] === 'Paid') ? 'bg-success' : 'bg-danger'; ?> status-badge">
                            <?php echo $billing['PaidStatus']; ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Patient Information -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Patient Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($billing['PatientName']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Service Type:</strong> <?php echo htmlspecialchars($billing['ServiceType']); ?></p>
                        <p><strong>Appointment Date:</strong> <?php echo date('F j, Y', strtotime($billing['AppointmentDate'])); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Billing Items -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Billing Items</h5>
                <?php if ($_SESSION['role'] === 'doctor'): ?>
                <a href="edit_billing.php?id=<?php echo $billing_id; ?>" class="btn btn-light btn-sm no-print">
                    <i class="fas fa-edit me-1"></i> Edit Billing
                </a>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover invoice-table mb-0">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th class="text-end">Price</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($billing_item['Description'])): ?>
                                <tr>
                                    <td colspan="3" class="text-center py-4">No items found</td>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($billing_item['Description']); ?></td>
                                    <td class="text-end">₱<?php echo number_format($billing_item['Price'], 2); ?></td>
                                    <td class="text-end">₱<?php echo number_format($billing_item['Subtotal'], 2); ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td colspan="2" class="text-end"><strong>Total Amount:</strong></td>
                                <td class="text-end">₱<?php echo number_format($billing['Amount'], 2); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Payment Information -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Payment Information</h5>
            </div>
            <div class="card-body">
                <?php if ($billing['PaidStatus'] === 'Paid'): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        This invoice was paid on <?php echo date('F j, Y', strtotime($billing['PaymentDate'])); ?> 
                        via <?php echo htmlspecialchars($billing['PaymentMethod']); ?>.
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        This invoice is unpaid.
                        <?php if ($_SESSION['role'] === 'doctor'): ?>
                        <a href="update_payment.php?id=<?php echo $billing_id; ?>" class="btn btn-sm btn-success ms-3 no-print">
                            <i class="fas fa-money-bill-wave me-1"></i> Record Payment
                        </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($billing['Notes'])): ?>
                <div class="mt-3">
                    <h6>Notes:</h6>
                    <p><?php echo nl2br(htmlspecialchars($billing['Notes'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="print-footer" class="d-none d-print-block" style="text-align: center; margin-top: 50px;">
        <p>Thank you for choosing Binamira Clinic!</p>
        <p style="margin-top: 30px; border-top: 1px solid #ddd; padding-top: 10px;">
            <span style="display: inline-block; width: 45%; text-align: center;">
                _________________________<br>
                Nurse Signature
            </span>
            <span style="display: inline-block; width: 45%; text-align: center;">
                _________________________<br>
                Patient's Signature
            </span>
        </p>
        <p style="margin-top: 20px; font-size: 12px;">
            This is an official receipt. Please keep this for your records.
        </p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>