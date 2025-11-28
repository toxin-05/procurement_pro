<?php
$page_title = 'Manage Vendors';
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();
if (!hasRole('admin')) {
    header("Location: user_dashboard.php");
    exit();
}

// Flash Message System
$success_msg = '';
if (isset($_SESSION['success_msg'])) {
    $success_msg = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}
$error_msg = '';
if (isset($_SESSION['error_msg'])) {
    $error_msg = $_SESSION['error_msg'];
    unset($_SESSION['error_msg']);
}

// Handle All Form Submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Handle Add/Update Vendor from the modal
    if (isset($_POST['save_vendor'])) {
        $vendor_id = intval($_POST['vendor_id']);
        $vendor_name = trim($_POST['vendor_name']);
        $contact_person = trim($_POST['contact_person']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);

        if ($vendor_id > 0) { // This is an UPDATE
            $stmt = $conn->prepare("UPDATE vendors SET vendor_name = ?, contact_person = ?, email = ?, phone = ?, address = ? WHERE id = ?");
            $stmt->bind_param("sssssi", $vendor_name, $contact_person, $email, $phone, $address, $vendor_id);
            $_SESSION['success_msg'] = "Vendor updated successfully.";
        } else { // This is an INSERT
            $stmt = $conn->prepare("INSERT INTO vendors (vendor_name, contact_person, email, phone, address) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $vendor_name, $contact_person, $email, $phone, $address);
            $_SESSION['success_msg'] = "New vendor added successfully.";
        }
        
        if (!$stmt->execute()) {
            $_SESSION['error_msg'] = "Database Error: Could not save vendor.";
            unset($_SESSION['success_msg']);
        }
        $stmt->close();
    }

    // Handle Delete Vendor
    else if (isset($_POST['delete_vendor'])) {
        $vendor_id = intval($_POST['vendor_id']);
        try {
            $stmt = $conn->prepare("DELETE FROM vendors WHERE id = ?");
            $stmt->bind_param("i", $vendor_id);
            $stmt->execute();
            $_SESSION['success_msg'] = "Vendor deleted successfully.";
        } catch (mysqli_sql_exception $e) {
            $_SESSION['error_msg'] = "Cannot delete vendor. It is linked to existing Purchase Orders.";
        }
    }

    header("Location: manage_vendors.php");
    exit();
}

// Data Fetching with Search
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
if (!empty($search_term)) {
    $like_term = "%" . $search_term . "%";
    $stmt = $conn->prepare("SELECT * FROM vendors WHERE vendor_name LIKE ? OR contact_person LIKE ? OR email LIKE ? ORDER BY vendor_name ASC");
    $stmt->bind_param("sss", $like_term, $like_term, $like_term);
    $stmt->execute();
    $vendors = $stmt->get_result();
} else {
    // Fetch all vendors if no search
    $vendors = $conn->query("SELECT * FROM vendors ORDER BY vendor_name ASC");
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/jpeg" href="../assets/favicon.jpeg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($page_title); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/admin_style.css">
</head>
<body>

<div class="sidebar">
<div class="sidebar-header">
    <img src="../assets/favicon.jpeg" alt="Kwekwe Polytechnic Logo" class="sidebar-logo">
</div>
    <nav class="sidebar-nav">
        <a href="admin_dashboard.php"><i class="icon fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="manage_users.php"><i class="icon fas fa-users"></i> Manage Users</a>
        <a href="manage_vendors.php" class="active"><i class="icon fas fa-store"></i> Manage Vendors</a>
        <a href="reports.php"><i class="icon fas fa-chart-pie"></i> Reports</a>
    </nav>
    <div class="sidebar-footer">
        <a href="../logout.php" class="btn btn-sm btn-outline-danger mt-2"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="main-content">
    <header class="main-header"><h1><?php echo e($page_title); ?></h1></header>
    
    <?php if ($success_msg): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo $success_msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>
    <?php if ($error_msg): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo $error_msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-list-ul text-primary"></i> Existing Vendors</h4>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#vendorModal">
                <i class="fas fa-plus me-2"></i> Add New Vendor
            </button>
        </div>
        <div class="card-body">
            <!-- Search Form -->
            <form method="GET" class="mb-3">
                <div class="input-group">
                    <input type="search" name="search" class="form-control" placeholder="Search by name, contact, or email..." value="<?php echo e($search_term); ?>">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
                    <?php if (!empty($search_term)): ?>
                        <a href="manage_vendors.php" class="btn btn-outline-secondary" title="Clear Search"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-light"><tr><th>Vendor Name</th><th>Contact Person</th><th>Contact Info</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if($vendors && $vendors->num_rows > 0): ?>
                            <?php while($vendor = $vendors->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo e($vendor['vendor_name']); ?></strong></td>
                                <td><?php echo e($vendor['contact_person']); ?></td>
                                <td>
                                    <div><i class="fas fa-envelope me-2 text-muted"></i><?php echo e($vendor['email']); ?></div>
                                    <div class="small text-muted"><i class="fas fa-phone me-2 text-muted"></i><?php echo e($vendor['phone']); ?></div>
                                </td>
                                <td>
                                    <div class="d-flex">
                                        <button class="btn btn-sm btn-outline-primary me-2 edit-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#vendorModal"
                                                data-id="<?php echo $vendor['id']; ?>"
                                                data-name="<?php echo e($vendor['vendor_name']); ?>"
                                                data-contact="<?php echo e($vendor['contact_person']); ?>"
                                                data-email="<?php echo e($vendor['email']); ?>"
                                                data-phone="<?php echo e($vendor['phone']); ?>"
                                                data-address="<?php echo e($vendor['address']); ?>"
                                                title="Edit Vendor"><i class="fas fa-edit"></i></button>
                                        
                                        <form method="POST" onsubmit="return confirm('Are you sure? This cannot be undone.');">
                                            <input type="hidden" name="vendor_id" value="<?php echo $vendor['id']; ?>">
                                            <button type="submit" name="delete_vendor" class="btn btn-sm btn-outline-danger" title="Delete Vendor"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center p-4">No vendors found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Vendor Modal -->
<div class="modal fade" id="vendorModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="vendorModalLabel">Add New Vendor</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="vendor-form" method="POST">
        <div class="modal-body">
            <input type="hidden" name="vendor_id" id="vendor_id" value="0">
            <div class="mb-3"><label class="form-label">Vendor Name</label><input type="text" name="vendor_name" id="vendor_name" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Contact Person</label><input type="text" name="contact_person" id="contact_person" class="form-control"></div>
            <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" id="email" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Phone</label><input type="text" name="phone" id="phone" class="form-control"></div>
            <div class="mb-3"><label class="form-label">Address</label><textarea name="address" id="address" class="form-control" rows="3"></textarea></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="save_vendor" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save Vendor</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const vendorModal = document.getElementById('vendorModal');
    const form = document.getElementById('vendor-form');
    const formTitle = document.getElementById('vendorModalLabel');
    const vendorIdInput = document.getElementById('vendor_id');

    // Logic to switch modal between "Add" and "Edit" mode
    vendorModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget; // Button that triggered the modal
        
        // Reset form for both Add and Edit
        form.reset();
        vendorIdInput.value = '0';

        if (button.classList.contains('edit-btn')) {
            // Edit Mode
            formTitle.innerHTML = '<i class="fas fa-edit text-primary"></i> Edit Vendor';
            const vendor = button.dataset;
            vendorIdInput.value = vendor.id;
            document.getElementById('vendor_name').value = vendor.name;
            document.getElementById('contact_person').value = vendor.contact;
            document.getElementById('email').value = vendor.email;
            document.getElementById('phone').value = vendor.phone;
            document.getElementById('address').value = vendor.address;
        } else {
            // Add Mode
            formTitle.innerHTML = '<i class="fas fa-plus-circle text-success"></i> Add New Vendor';
        }
    });
});
</script>
</body>
</html>