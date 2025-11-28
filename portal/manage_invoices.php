<?php
$page_title = 'Manage Invoices';
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();
if (!hasRole('finance') && !hasRole('admin')) {
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
    
    // Handle Add Invoice
    if (isset($_POST['add_invoice'])) {
        $po_id = intval($_POST['po_id']);
        $invoice_number = trim($_POST['invoice_number']);
        $invoice_amount = floatval($_POST['invoice_amount']);
        $invoice_scan_path = null;
        
        $date_from_form = $_POST['invoice_date'];
        $timestamp = strtotime($date_from_form);
        
        if ($timestamp === false) {
            $_SESSION['error_msg'] = "The invoice date provided was not valid.";
        } else {
            $invoice_date = date('Y-m-d', $timestamp);

            if (isset($_FILES['invoice_scan']) && $_FILES['invoice_scan']['error'] == 0) {
                $target_dir = "../uploads/invoices/";
                if (!is_dir($target_dir)) { mkdir($target_dir, 0755, true); }
                $file_name = time() . '_' . basename($_FILES["invoice_scan"]["name"]);
                $target_file = $target_dir . $file_name;
                $db_path = 'uploads/invoices/' . $file_name;
                if (move_uploaded_file($_FILES["invoice_scan"]["tmp_name"], $target_file)) {
                    $invoice_scan_path = $db_path;
                }
            }

            $stmt = $conn->prepare("INSERT INTO invoices (po_id, invoice_number, invoice_date, invoice_amount, invoice_scan_path, status) VALUES (?, ?, ?, ?, ?, 'Unpaid')");
            $stmt->bind_param("isids", $po_id, $invoice_number, $invoice_date, $invoice_amount, $invoice_scan_path);
            if ($stmt->execute()) {
                $_SESSION['success_msg'] = "Invoice logged successfully.";
            } else {
                 $_SESSION['error_msg'] = "Database Error: " . $stmt->error;
            }
            $stmt->close();
        }
    }

    // Handle Mark as Paid
    else if (isset($_POST['mark_paid'])) {
        $invoice_id = intval($_POST['invoice_id']);

        $conn->begin_transaction();
        try {
            // Step 1: Mark the invoice as Paid
            $stmt1 = $conn->prepare("UPDATE invoices SET status = 'Paid' WHERE id = ?");
            $stmt1->bind_param("i", $invoice_id);
            $stmt1->execute();
            $stmt1->close();

            // Step 2: Find the related request and update its status to Completed
            $stmt2 = $conn->prepare("UPDATE requests r JOIN purchase_orders po ON r.id = po.request_id JOIN invoices i ON po.id = i.po_id SET r.status = 'Completed' WHERE i.id = ?");
            $stmt2->bind_param("i", $invoice_id);
            $stmt2->execute();
            $stmt2->close();
            
            // Step 3: Update the Purchase Order status to Completed
            $stmt3 = $conn->prepare("UPDATE purchase_orders SET status = 'Completed' WHERE id = (SELECT po_id FROM invoices WHERE id = ?)");
            $stmt3->bind_param("i", $invoice_id);
            $stmt3->execute();
            $stmt3->close();

            $conn->commit();
            $_SESSION['success_msg'] = "Invoice paid and PO has been completed.";

        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            $_SESSION['error_msg'] = "Error updating status: " . $exception->getMessage();
        }
    }

    // Handle Delete Invoice
    else if (isset($_POST['delete_invoice'])) {
        $invoice_id = intval($_POST['invoice_id']);
        
        $stmt_select = $conn->prepare("SELECT invoice_scan_path FROM invoices WHERE id = ?");
        $stmt_select->bind_param("i", $invoice_id);
        $stmt_select->execute();
        $result = $stmt_select->get_result();
        if($file = $result->fetch_assoc()){
            if(!empty($file['invoice_scan_path']) && file_exists('../' . $file['invoice_scan_path'])){
                unlink('../' . $file['invoice_scan_path']);
            }
        }
        $stmt_select->close();
        
        $stmt_delete = $conn->prepare("DELETE FROM invoices WHERE id = ?");
        $stmt_delete->bind_param("i", $invoice_id);
        if ($stmt_delete->execute()) {
            $_SESSION['success_msg'] = "Invoice deleted successfully.";
        }
        $stmt_delete->close();
    }

    header("Location: manage_invoices.php");
    exit();
}

// --- Data Fetching ---
$purchase_orders_list = $conn->query("SELECT po.id, po.po_number, r.subject FROM purchase_orders po JOIN requests r ON po.request_id = r.id WHERE po.status = 'Processing'");
$invoices = $conn->query("SELECT i.*, po.po_number FROM invoices i JOIN purchase_orders po ON i.po_id = po.id ORDER BY i.created_at DESC");
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
        <a href="finance_dashboard.php"><i class="icon fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="manage_invoices.php" class="active"><i class="icon fas fa-receipt"></i> Manage Invoices</a>
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
            <h4 class="mb-0"><i class="fas fa-plus-circle text-success"></i> Log New Invoice</h4>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <div class="row g-3">
                    <div class="col-md-3"><label class="form-label">Purchase Order</label><select name="po_id" class="form-select" required><option value="" disabled selected>-- Select PO --</option><?php while($po = $purchase_orders_list->fetch_assoc()): ?><option value="<?php echo $po['id']; ?>"><?php echo e($po['po_number']); ?> (<?php echo e($po['subject']); ?>)</option><?php endwhile; ?></select></div>
                    <div class="col-md-2"><label class="form-label">Invoice Number</label><input type="text" name="invoice_number" class="form-control" required></div>
                    <div class="col-md-2"><label class="form-label">Invoice Date</label><input type="date" name="invoice_date" class="form-control" required></div>
                    <div class="col-md-2"><label class="form-label">Amount ($)</label><input type="number" step="0.01" name="invoice_amount" class="form-control" required></div>
                    <div class="col-md-3"><label class="form-label">Upload Scan (PDF, JPG)</label><input type="file" name="invoice_scan" class="form-control" accept=".pdf,.jpg,.jpeg,.png"></div>
                </div>
                <div class="row mt-3"><div class="col-12"><button type="submit" name="add_invoice" class="btn btn-primary">Log Invoice</button></div></div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h4 class="mb-0"><i class="fas fa-list-ul text-primary"></i> Logged Invoices</h4>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-light"><tr><th>Invoice #</th><th>PO #</th><th>Amount</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if($invoices && $invoices->num_rows > 0): ?>
                            <?php while($invoice = $invoices->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo e($invoice['invoice_number']); ?></strong></td>
                                <td><?php echo e($invoice['po_number']); ?></td>
                                <td>$<?php echo number_format($invoice['invoice_amount'], 2); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($invoice['invoice_date'])); ?></td>
                                <td><span class="badge <?php echo $invoice['status'] == 'Paid' ? 'bg-success' : 'bg-warning text-dark'; ?>"><?php echo e($invoice['status']); ?></span></td>
                                <td>
                                    <div class="d-flex">
                                        <?php if($invoice['invoice_scan_path']): ?>
                                            <a href="../<?php echo e($invoice['invoice_scan_path']); ?>" class="btn btn-sm btn-outline-info me-2" target="_blank" title="View Scan"><i class="fas fa-file-alt"></i></a>
                                        <?php endif; ?>
                                        
                                        <?php if ($invoice['status'] == 'Unpaid'): ?>
                                        <form method="POST" class="me-2">
                                            <input type="hidden" name="invoice_id" value="<?php echo $invoice['id']; ?>">
                                            <button type="submit" name="mark_paid" class="btn btn-sm btn-outline-success" title="Mark as Paid"><i class="fas fa-check-circle"></i></button>
                                        </form>
                                        <?php endif; ?>
                                        
                                        <form method="POST" onsubmit="return confirm('Are you sure? This action cannot be undone.');">
                                            <input type="hidden" name="invoice_id" value="<?php echo $invoice['id']; ?>">
                                            <button type="submit" name="delete_invoice" class="btn btn-sm btn-outline-danger" title="Delete Invoice"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center p-4">No invoices have been logged yet.</td></tr>
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