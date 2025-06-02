<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['doctor', 'nurse'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Handle medicine deletion
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    
    // Check if medicine is in use before deleting
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM prescription_medications WHERE MedicineID = ?");
    $check_stmt->bind_param("i", $delete_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    
    if ($count > 0) {
        $_SESSION['error_message'] = "Cannot delete medicine as it is used in prescriptions.";
    } else {
        // Delete the medicine
        $delete_stmt = $conn->prepare("DELETE FROM medicines WHERE MedicineID = ?");
        $delete_stmt->bind_param("i", $delete_id);
        
        if ($delete_stmt->execute()) {
            $_SESSION['success_message'] = "Medicine deleted successfully!";
        } else {
            $_SESSION['error_message'] = "Error deleting medicine.";
        }
    }
    header("Location: medicines.php");
    exit;
}

// Handle medicine addition
if (isset($_POST['add_medicine'])) {
    $name = $_POST['name'];
    $generic_name = $_POST['generic_name'];
    $category = $_POST['category'];
    $description = $_POST['description'];
    $dosage_form = $_POST['dosage_form'];
    $strength = $_POST['strength'];
    $manufacturer = $_POST['manufacturer'];
    
    $add_stmt = $conn->prepare("INSERT INTO medicines (Name, GenericName, Category, Description, DosageForm, Strength, Manufacturer) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $add_stmt->bind_param("sssssss", $name, $generic_name, $category, $description, $dosage_form, $strength, $manufacturer);
    
    if ($add_stmt->execute()) {
        $_SESSION['success_message'] = "Medicine added successfully!";
    } else {
        $_SESSION['error_message'] = "Error adding medicine: " . $conn->error;
    }
    header("Location: medicines.php");
    exit;
}

// Handle medicine update
if (isset($_POST['update_medicine'])) {
    $medicine_id = $_POST['medicine_id'];
    $name = $_POST['name'];
    $generic_name = $_POST['generic_name'];
    $category = $_POST['category'];
    $description = $_POST['description'];
    $dosage_form = $_POST['dosage_form'];
    $strength = $_POST['strength'];
    $manufacturer = $_POST['manufacturer'];
    
    $update_stmt = $conn->prepare("UPDATE medicines SET Name = ?, GenericName = ?, Category = ?, Description = ?, DosageForm = ?, Strength = ?, Manufacturer = ? WHERE MedicineID = ?");
    $update_stmt->bind_param("sssssssi", $name, $generic_name, $category, $description, $dosage_form, $strength, $manufacturer, $medicine_id);
    
    if ($update_stmt->execute()) {
        $_SESSION['success_message'] = "Medicine updated successfully!";
    } else {
        $_SESSION['error_message'] = "Error updating medicine: " . $conn->error;
    }
    header("Location: medicines.php");
    exit;
}

// Get user information
if ($role === 'doctor') {
    $stmt = $conn->prepare("SELECT u.*, d.Specialty FROM users u JOIN doctors d ON u.UserID = d.DoctorID WHERE u.UserID = ?");
} else {
    $stmt = $conn->prepare("SELECT u.*, n.Department FROM users u JOIN nurses n ON u.UserID = n.NurseID WHERE u.UserID = ?");
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get all medicines
$medicines_query = "SELECT * FROM medicines ORDER BY Name";
$result = $conn->query($medicines_query);
$all_medicines = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<!-- Rest of your HTML remains exactly the same -->
<head>
    <meta charset="UTF-8">
    <title>Medicines - Binamira Clinic</title>
    <link rel="stylesheet" href="dashboard.css">
    <!-- Font Awesome CDN -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 30px;
            border: 1px solid #888;
            border-radius: 10px;
            width: 50%;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
        }

        .close-modal {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close-modal:hover,
        .close-modal:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .form-group textarea {
            height: 100px;
        }

        .submit-btn {
            background-color: #e63946;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 10px;
        }

        .submit-btn:hover {
            background-color: #d62839;
        }

        /* Message styles */
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            border-left: 4px solid;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
            border-left-color: #4CAF50;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border-left-color: #F44336;
        }

        .message .close-btn {
            float: right;
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: inherit;
            opacity: 0.7;
        }

        .message .close-btn:hover {
            opacity: 1;
        }

        /* Table and button styles */
        .table-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-header h2 {
            font-size: 20px;
            color: #333;
        }

        .btn {
            background-color: #e63946;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            font-size: 14px;
        }

        .btn:hover {
            background-color: #d62839;
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
            vertical-align: top;
        }

        .action-btn {
            background: none;
            border: none;
            cursor: pointer;
            margin-right: 10px;
            font-size: 14px;
            padding: 5px;
            border-radius: 3px;
            transition: background-color 0.3s;
        }

        .action-btn:hover {
            background-color: rgba(0, 0, 0, 0.1);
        }

        .action-btn.edit {
            color: #FFC107;
        }

        .action-btn.delete {
            color: #F44336;
        }

        /* Search and filter styles */
        .search-filter {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .search-box {
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            width: 250px;
            font-size: 14px;
        }

        .filter-box {
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            width: 150px;
            font-size: 14px;
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
                
                <a href="medicines.php" class="nav-item active">
                    <i class="fas fa-pills"></i>
                    <span>Medicines</span>
                </a>
                <?php if ($role === 'nurse'): ?>
                <a href="billings.php" class="nav-item">
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

        <div class="main-content">
            <div class="header">
                <h1>Medicines</h1>
                <div class="user-actions">
                    <a href="logout.php" class="btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="message success">
                    <?php echo $_SESSION['success_message']; ?>
                    <button class="close-btn" onclick="this.parentElement.style.display='none';">&times;</button>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="message error">
                    <?php echo $_SESSION['error_message']; ?>
                    <button class="close-btn" onclick="this.parentElement.style.display='none';">&times;</button>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <div class="table-container">
                <div class="section-header">
                    <h2>Medicine List</h2>
                    <button id="add-medicine-btn" class="btn">
                        <i class="fas fa-plus"></i> Add Medicine
                    </button>
                </div>

                <div class="search-filter">
                    <input type="text" id="search-medicine" class="search-box" placeholder="Search medicines...">
                    <select id="filter-category" class="filter-box">
                        <option value="">All Categories</option>
                        <?php
                        $categories = [];
                        foreach ($all_medicines as $medicine) {
                            if (!in_array($medicine['Category'], $categories) && !empty($medicine['Category'])) {
                                $categories[] = $medicine['Category'];
                                echo '<option value="' . htmlspecialchars($medicine['Category']) . '">' . htmlspecialchars($medicine['Category']) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>

                <table id="medicines-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Generic Name</th>
                            <th>Category</th>
                            <th>Dosage Form</th>
                            <th>Strength</th>
                            <th>Manufacturer</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_medicines as $medicine): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($medicine['MedicineID']); ?></td>
                                <td><?php echo htmlspecialchars($medicine['Name']); ?></td>
                                <td><?php echo htmlspecialchars($medicine['GenericName']); ?></td>
                                <td><?php echo htmlspecialchars($medicine['Category']); ?></td>
                                <td><?php echo htmlspecialchars($medicine['DosageForm']); ?></td>
                                <td><?php echo htmlspecialchars($medicine['Strength']); ?></td>
                                <td><?php echo htmlspecialchars($medicine['Manufacturer']); ?></td>
                                <td>
                                    <button class="action-btn edit" onclick="openEditModal(<?php echo $medicine['MedicineID']; ?>, '<?php echo addslashes($medicine['Name']); ?>', '<?php echo addslashes($medicine['GenericName']); ?>', '<?php echo addslashes($medicine['Category']); ?>', '<?php echo addslashes($medicine['Description']); ?>', '<?php echo addslashes($medicine['DosageForm']); ?>', '<?php echo addslashes($medicine['Strength']); ?>', '<?php echo addslashes($medicine['Manufacturer']); ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="#" class="action-btn delete" onclick="confirmDelete(<?php echo $medicine['MedicineID']; ?>, '<?php echo addslashes($medicine['Name']); ?>')">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Medicine Modal -->
    <div id="add-medicine-modal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h2>Add New Medicine</h2>
            <form action="medicines.php" method="POST">
                <div class="form-group">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="generic_name">Generic Name</label>
                    <input type="text" id="generic_name" name="generic_name" required>
                </div>
                <div class="form-group">
                    <label for="category">Category</label>
                    <input type="text" id="category" name="category" required>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description"></textarea>
                </div>
                <div class="form-group">
                    <label for="dosage_form">Dosage Form</label>
                    <input type="text" id="dosage_form" name="dosage_form" required>
                </div>
                <div class="form-group">
                    <label for="strength">Strength</label>
                    <input type="text" id="strength" name="strength" required>
                </div>
                <div class="form-group">
                    <label for="manufacturer">Manufacturer</label>
                    <input type="text" id="manufacturer" name="manufacturer" required>
                </div>
                <button type="submit" name="add_medicine" class="submit-btn">Add Medicine</button>
            </form>
        </div>
    </div>

    <!-- Edit Medicine Modal -->
    <div id="edit-medicine-modal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h2>Edit Medicine</h2>
            <form action="medicines.php" method="POST">
                <input type="hidden" id="edit_medicine_id" name="medicine_id">
                <div class="form-group">
                    <label for="edit_name">Name</label>
                    <input type="text" id="edit_name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="edit_generic_name">Generic Name</label>
                    <input type="text" id="edit_generic_name" name="generic_name" required>
                </div>
                <div class="form-group">
                    <label for="edit_category">Category</label>
                    <input type="text" id="edit_category" name="category" required>
                </div>
                <div class="form-group">
                    <label for="edit_description">Description</label>
                    <textarea id="edit_description" name="description"></textarea>
                </div>
                <div class="form-group">
                    <label for="edit_dosage_form">Dosage Form</label>
                    <input type="text" id="edit_dosage_form" name="dosage_form" required>
                </div>
                <div class="form-group">
                    <label for="edit_strength">Strength</label>
                    <input type="text" id="edit_strength" name="strength" required>
                </div>
                <div class="form-group">
                    <label for="edit_manufacturer">Manufacturer</label>
                    <input type="text" id="edit_manufacturer" name="manufacturer" required>
                </div>
                <button type="submit" name="update_medicine" class="submit-btn">Update Medicine</button>
            </form>
        </div>
    </div>

    <script>
        // Modal functionality
        const addModal = document.getElementById("add-medicine-modal");
        const editModal = document.getElementById("edit-medicine-modal");
        const addBtn = document.getElementById("add-medicine-btn");
        const closeBtns = document.getElementsByClassName("close-modal");

        // Open add modal
        addBtn.onclick = function() {
            addModal.style.display = "block";
        }

        // Close modals
        for (let i = 0; i < closeBtns.length; i++) {
            closeBtns[i].onclick = function() {
                addModal.style.display = "none";
                editModal.style.display = "none";
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target == addModal) {
                addModal.style.display = "none";
            }
            if (event.target == editModal) {
                editModal.style.display = "none";
            }
        }

        // Open edit modal and populate with medicine data
        function openEditModal(id, name, genericName, category, description, dosageForm, strength, manufacturer) {
            document.getElementById("edit_medicine_id").value = id;
            document.getElementById("edit_name").value = name;
            document.getElementById("edit_generic_name").value = genericName;
            document.getElementById("edit_category").value = category;
            document.getElementById("edit_description").value = description;
            document.getElementById("edit_dosage_form").value = dosageForm;
            document.getElementById("edit_strength").value = strength;
            document.getElementById("edit_manufacturer").value = manufacturer;
            
            editModal.style.display = "block";
        }

        // Confirm delete
        function confirmDelete(id, name) {
            if (confirm("Are you sure you want to delete " + name + "?")) {
                window.location.href = "medicines.php?delete_id=" + id;
            }
        }

        // Search and filter functionality
        document.getElementById('search-medicine').addEventListener('input', filterMedicines);
        document.getElementById('filter-category').addEventListener('change', filterMedicines);

        function filterMedicines() {
            const searchTerm = document.getElementById('search-medicine').value.toLowerCase();
            const categoryFilter = document.getElementById('filter-category').value.toLowerCase();
            const table = document.getElementById('medicines-table');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const nameCell = rows[i].getElementsByTagName('td')[1];
                const genericNameCell = rows[i].getElementsByTagName('td')[2];
                const categoryCell = rows[i].getElementsByTagName('td')[3];
                
                if (nameCell && genericNameCell && categoryCell) {
                    const nameText = nameCell.textContent || nameCell.innerText;
                    const genericNameText = genericNameCell.textContent || genericNameCell.innerText;
                    const categoryText = categoryCell.textContent || categoryCell.innerText;
                    
                    const matchesSearch = nameText.toLowerCase().includes(searchTerm) || 
                                          genericNameText.toLowerCase().includes(searchTerm);
                    
                    const matchesCategory = categoryFilter === '' || 
                                          categoryText.toLowerCase() === categoryFilter;
                    
                    if (matchesSearch && matchesCategory) {
                        rows[i].style.display = "";
                    } else {
                        rows[i].style.display = "none";
                    }
                }
            }
        }
    </script>
</body>
</html>