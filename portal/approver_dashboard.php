<?php
$page_title = 'Approver Dashboard';
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

// This page is for approvers and admins only.
if (!hasRole('approver') && !hasRole('admin')) {
    header("Location: user_dashboard.php");
    exit();
}

// This query correctly fetches all requests that are pending.
$pending_requests = $conn->query("
    SELECT r.id, r.subject, r.created_at, u.full_name, u.department
    FROM requests r
    JOIN users u ON r.user_id = u.id
    WHERE r.status = 'Pending Approval'
    ORDER BY r.created_at ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/jpeg" href="../assets/favicon.jpeg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale-1.0">
    <title><?php echo e($page_title); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/admin_style.css">
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">
        <i class="fas fa-rocket"></i> ProcurementPRO
    </div>
    <nav class="sidebar-nav">
        <a href="user_dashboard.php" class="active"><i class="icon fas fa-tachometer-alt"></i> Dashboard</a>
    </nav>
    <div class="sidebar-footer">
        <p>&copy; <?php echo date('Y'); ?>. All Rights Reserved.</p>
        <a href="../logout.php" class="btn btn-sm btn-outline-danger mt-2">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>

<div class="main-content">
    <header class="main-header">
        <h1>Approver Dashboard</h1>
        <p class="lead">The following requests are awaiting your review and approval.</p>
    </header>

    <div class="table-container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4><i class="fas fa-hourglass-half text-warning"></i> Requests Pending Approval</h4>
        </div>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Request ID</th>
                        <th>Subject</th>
                        <th>Requester</th>
                        <th>Department</th>
                        <th>Date Submitted</th>
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
                            <td><?php echo e($row['department']); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></td>
                            <td>
                                <a href="request_details.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i> Review Request</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center p-4">
                                <i class="fas fa-check-circle fa-2x text-success"></i>
                                <p class="mt-2">There are no pending requests to approve.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>