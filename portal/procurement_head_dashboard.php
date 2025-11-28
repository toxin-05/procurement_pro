<?php
$page_title = 'Head of Procurement Dashboard';
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();
if (!hasRole('procurement_head') && !hasRole('admin')) { 
    header("Location: user_dashboard.php"); 
    exit(); 
}

// --- Data Fetching for Dashboard Widgets ---

// 1. Get count of requests pending this user's approval
$pending_count_result = $conn->query("SELECT COUNT(id) as count FROM requests WHERE status = 'Finance Approved'");
$pending_count = $pending_count_result->fetch_assoc()['count'];

// 2. Get count of requests ready for PO generation
$ready_for_po_count_result = $conn->query("SELECT COUNT(id) as count FROM requests WHERE status = 'Principal Approved'");
$ready_for_po_count = $ready_for_po_count_result->fetch_assoc()['count'];

// 3. MODIFIED: This now excludes POs where the linked request was rejected.
$active_po_count_result = $conn->query("
    SELECT COUNT(po.id) as count 
    FROM purchase_orders po
    JOIN requests r ON po.request_id = r.id
    WHERE po.status = 'Processing' AND r.status != 'Rejected'
");
$active_po_count = $active_po_count_result->fetch_assoc()['count'];

// 4. Get count of COMPLETED Purchase Orders (based on request status)
$completed_po_count_result = $conn->query("
    SELECT COUNT(po.id) as count 
    FROM purchase_orders po
    JOIN requests r ON po.request_id = r.id
    WHERE r.status = 'Completed'
");
$completed_po_count = $completed_po_count_result->fetch_assoc()['count'];


// 5. Fetch the list of pending requests for the main table
$pending_requests = $conn->query("
    SELECT r.id, r.subject, r.created_at, u.full_name
    FROM requests r 
    JOIN users u ON r.user_id = u.id
    WHERE r.status = 'Finance Approved' 
    ORDER BY r.created_at ASC
");
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
        <a href="procurement_head_dashboard.php" class="active"><i class="icon fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="manage_pos.php"><i class="icon fas fa-file-invoice"></i> Generate POs</a>
        <a href="view_all_requests.php"><i class="icon fas fa-folder-open"></i> View All Requests</a>
    </nav>
    <div class="sidebar-footer">
        <a href="../logout.php" class="btn btn-sm btn-outline-danger mt-2"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="main-content">
    <header class="main-header">
        <h1><?php echo e($page_title); ?></h1>
        <span class="text-muted">Welcome back, <?php echo e($_SESSION['full_name']); ?>!</span>
    </header>

    <div class="row">
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="stat-card bg-info">
                <i class="fas fa-inbox"></i>
                <div>Pending Your Approval<span><?php echo $pending_count; ?></span></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="stat-card bg-success">
                <i class="fas fa-check-double"></i>
                <div>Ready for PO<span><?php echo $ready_for_po_count; ?></span></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="stat-card bg-primary">
                <i class="fas fa-cogs"></i>
                <div>Active POs<span><?php echo $active_po_count; ?></span></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="stat-card bg-secondary">
                <i class="fas fa-history"></i>
                <div>Completed POs<span><?php echo $completed_po_count; ?></span></div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h4 class="mb-0"><i class="fas fa-list-ul text-primary"></i> Requests Requiring Your Approval</h4>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Subject</th>
                            <th>Requester</th>
                            <th>Submitted</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($pending_requests && $pending_requests->num_rows > 0): ?>
                            <?php while($row = $pending_requests->fetch_assoc()): ?>
                            <tr>
                                <td><strong>#<?php echo $row['id']; ?></strong></td>
                                <td><?php echo e($row['subject']); ?></td>
                                <td><?php echo e($row['full_name']); ?></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></td>
                                <td>
                                    <a href="request_details.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye me-1"></i> Review & Approve
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center p-4 text-muted">There are no requests awaiting your approval.</td></tr>
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