<?php
$page_title = 'Manage Purchase Orders';
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();
if (!hasRole('procurement') && !hasRole('procurement_head') && !hasRole('admin')) {
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
    
    // Handle PO Generation
    if (isset($_POST['generate_po'])) {
        $request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
        $vendor_id = isset($_POST['vendor_id']) ? intval($_POST['vendor_id']) : 0;
        
        if (empty($request_id) || empty($vendor_id)) {
            $_SESSION['error_msg'] = "Error: You must select both an approved request and a vendor.";
        } else {
            $year = date("Y");
            $last_po_sql = "SELECT po_number FROM purchase_orders WHERE po_number LIKE 'PO-$year-%' ORDER BY id DESC LIMIT 1";
            $last_po_result = $conn->query($last_po_sql);
            $last_po_number = 0;
            if ($last_po_result->num_rows > 0) {
                $last_po = $last_po_result->fetch_assoc();
                $last_po_number = intval(substr($last_po['po_number'], -4));
            }
            $new_po_number = 'PO-' . $year . '-' . str_pad($last_po_number + 1, 4, '0', STR_PAD_LEFT);

            $conn->begin_transaction();
            try {
                $stmt1 = $conn->prepare("INSERT INTO purchase_orders (request_id, vendor_id, po_number, status) VALUES (?, ?, ?, 'Processing')");
                $stmt1->bind_param("iis", $request_id, $vendor_id, $new_po_number);
                $stmt1->execute();
                
                $stmt2 = $conn->prepare("UPDATE requests SET status = 'Processing' WHERE id = ?");
                $stmt2->bind_param("i", $request_id);
                $stmt2->execute();
                
                $conn->commit();
                $_SESSION['success_msg'] = "Purchase Order " . $new_po_number . " generated successfully!";

            } catch (mysqli_sql_exception $exception) {
                $conn->rollback();
                $_SESSION['error_msg'] = "Database Error: " . $exception->getMessage();
            }
        }
    }
    // Handle PO Deletion
    else if (isset($_POST['delete_po'])) {
        $po_id = intval($_POST['po_id']);
        $request_id = intval($_POST['request_id']);

        $conn->begin_transaction();
        try {
            $stmt1 = $conn->prepare("DELETE FROM purchase_orders WHERE id = ?");
            $stmt1->bind_param("i", $po_id);
            $stmt1->execute();
            
            $stmt2 = $conn->prepare("UPDATE requests SET status = 'Principal Approved' WHERE id = ?");
            $stmt2->bind_param("i", $request_id);
            $stmt2->execute();
            
            $conn->commit();
            $_SESSION['success_msg'] = "Purchase Order deleted and original request has been reset.";

        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            $_SESSION['error_msg'] = "Error deleting PO. It may be linked to an existing invoice.";
        }
    }
    
    header("Location: manage_pos.php");
    exit();
}

// --- Data Fetching ---
$approved_requests = $conn->query("SELECT id, subject FROM requests WHERE status = 'Principal Approved'");
$vendors = $conn->query("SELECT id, vendor_name FROM vendors ORDER BY vendor_name ASC");

$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$sql = "
    SELECT po.*, r.subject, v.vendor_name 
    FROM purchase_orders po 
    LEFT JOIN requests r ON po.request_id = r.id 
    LEFT JOIN vendors v ON po.vendor_id = v.id 
";
if (!empty($search_term)) {
    $like_term = "%" . $search_term . "%";
    $sql .= " WHERE po.po_number LIKE ? OR r.subject LIKE ? OR v.vendor_name LIKE ?";
    $sql .= " ORDER BY po.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $like_term, $like_term, $like_term);
    $stmt->execute();
    $purchase_orders = $stmt->get_result();
} else {
    $sql .= " ORDER BY po.created_at DESC";
    $purchase_orders = $conn->query($sql);
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
        <?php if (hasRole('admin')): ?>
            <a href="admin_dashboard.php"><i class="icon fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="manage_users.php"><i class="icon fas fa-users"></i> Manage Users</a>
            <a href="manage_vendors.php"><i class="icon fas fa-store"></i> Manage Vendors</a>
            <a href="manage_pos.php" class="active"><i class="icon fas fa-file-invoice"></i> Purchase Orders</a>
            <a href="reports.php"><i class="icon fas fa-chart-pie"></i> Reports</a>
        <?php elseif (hasRole('procurement_head')): ?>
            <a href="procurement_head_dashboard.php"><i class="icon fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="manage_pos.php" class="active"><i class="icon fas fa-file-invoice"></i> Generate POs</a>
            <a href="view_all_requests.php"><i class="icon fas fa-folder-open"></i> View All Requests</a>
        <?php elseif (hasRole('procurement')): ?>
            <a href="procurement_dashboard.php"><i class="icon fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="manage_pos.php" class="active"><i class="icon fas fa-file-invoice"></i> Purchase Orders</a>
        <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
        <a href="../logout.php" class="btn btn-sm btn-outline-danger mt-2"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="main-content">
    <header class="main-header"><h1><?php echo e($page_title); ?></h1></header>
    
    <?php if ($success_msg): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo $success_msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <?php if ($error_msg): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo $error_msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h4 class="mb-0"><i class="fas fa-plus-circle text-success"></i> Generate New Purchase Order</h4>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label">Approved Request</label>
                        <select name="request_id" class="form-select" required>
                            <option value="" disabled selected>-- Select a fully approved request --</option>
                            <?php if ($approved_requests->num_rows > 0): ?>
                                <?php while($req = $approved_requests->fetch_assoc()): ?>
                                    <option value="<?php echo $req['id']; ?>">Request #<?php echo $req['id']; ?> - <?php echo e($req['subject']); ?></option>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <option disabled>No requests are ready for PO generation.</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Vendor</label>
                        <select name="vendor_id" class="form-select" required>
                            <option value="" disabled selected>-- Select a vendor --</option>
                            <?php while($ven = $vendors->fetch_assoc()): ?>
                                <option value="<?php echo $ven['id']; ?>"><?php echo e($ven['vendor_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" name="generate_po" class="btn btn-primary w-100" <?php if ($approved_requests->num_rows == 0) echo 'disabled'; ?>>Generate PO</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h4 class="mb-0"><i class="fas fa-list-ul text-primary"></i> Existing Purchase Orders</h4>
        </div>
        <div class="card-body">
            <form method="GET" class="mb-3">
                <div class="input-group">
                    <input type="search" name="search" class="form-control" placeholder="Search by PO #, request, or vendor..." value="<?php echo e($search_term); ?>">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
                    <?php if (!empty($search_term)): ?>
                        <a href="manage_pos.php" class="btn btn-outline-secondary" title="Clear Search"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead class="table-light">
                        <tr><th>PO Number</th><th>Request Subject</th><th>Vendor</th><th>Date Created</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php if($purchase_orders && $purchase_orders->num_rows > 0): ?>
                            <?php while($po = $purchase_orders->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo e($po['po_number']); ?></strong></td>
                                <td>Request #<?php echo $po['request_id']; ?>: <?php echo e($po['subject'] ?? 'N/A'); ?></td>
                                <td><?php echo e($po['vendor_name'] ?? 'N/A'); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($po['created_at'])); ?></td>
                                <td><span class="badge bg-primary"><?php echo e($po['status']); ?></span></td>
                                <td>
                                    <div class="d-flex">
                                        <a href="view_po.php?id=<?php echo $po['id']; ?>" class="btn btn-sm btn-outline-secondary me-2" title="View/Print PO"><i class="fas fa-eye"></i></a>
                                        <form method="POST" onsubmit="return confirm('Are you sure? This will reset the original request.');">
                                            <input type="hidden" name="po_id" value="<?php echo $po['id']; ?>">
                                            <input type="hidden" name="request_id" value="<?php echo $po['request_id']; ?>">
                                            <button type="submit" name="delete_po" class="btn btn-sm btn-outline-danger" title="Delete PO"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center p-4">No Purchase Orders found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>