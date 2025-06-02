<?php
session_start();
require_once 'db_connect.php';

// Check if user is authorized
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'doctor' && $_SESSION['role'] !== 'nurse')) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['prescription_id'])) {
    $prescription_id = $_POST['prescription_id'];
    
    // First delete related medications and investigations
    $conn->begin_transaction();
    
    try {
        $stmt = $conn->prepare("DELETE FROM prescription_medications WHERE PrescriptionID = ?");
        $stmt->bind_param("i", $prescription_id);
        $stmt->execute();
        
        $stmt = $conn->prepare("DELETE FROM prescription_investigations WHERE PrescriptionID = ?");
        $stmt->bind_param("i", $prescription_id);
        $stmt->execute();
        
        $stmt = $conn->prepare("DELETE FROM prescriptions WHERE PrescriptionID = ?");
        $stmt->bind_param("i", $prescription_id);
        $stmt->execute();
        
        $conn->commit();
        $_SESSION['success_message'] = "Prescription deleted successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Error deleting prescription: " . $e->getMessage();
    }
}

header("Location: prescriptions.php");
exit;
?>