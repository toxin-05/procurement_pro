<?php
$page_title = 'All System Requests';
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();
if (!hasRole('procurement_head') && !hasRole('admin')) {
    header("Location: user_dashboard.php");
    exit();
}

// Fetch all requests, joining with user table to get requester's name
$all_requests = $conn->query("
    SELECT r.id, r.subject, r.status, r.created_at, u.full_name as requester_name
    FROM requests r
    JOIN users u ON r.user_id = u.id
    ORDER BY r.created_at DESC
");

function getStatusBadgeClass($status) {
    switch ($status) {
        case 'Pending Approval': return 'bg-warning text-dark';
        case 'Finance Approved': return 'bg-info text-dark';
        case 'PMU Approved': return 'bg-primary';
        case 'Principal Approved': return 'bg-success';
        case 'Processing': return 'bg-dark';
        case 'Completed': return 'bg-secondary';
        case 'Rejected': return 'bg-danger';
        default: return 'bg-light text-dark';
    }
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
        <a href="procurement_head_dashboard.php"><i class="icon fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="manage_pos.php"><i class="icon fas fa-file-invoice"></i> Generate POs</a>
        <a href="view_all_requests.php" class="active"><i class="icon fas fa-folder-open"></i> View All Requests</a>
    </nav>
    <div class="sidebar-footer">
        <a href="../logout.php" class="btn btn-sm btn-outline-danger mt-2"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="main-content">
    <header class="main-header"><h1><?php echo e($page_title); ?></h1></header>
    <div class="table-container">
        <h4><i class="fas fa-list-ul text-primary"></i> Complete Request History</h4>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>ID</th><th>Subject</th><th>Requester</th><th>Status</th><th>Submitted</th><th>Action</th></tr></thead>
                <tbody>
                    <?php if ($all_requests && $all_requests->num_rows > 0): ?>
                        <?php while($row = $all_requests->fetch_assoc()): ?>
                        <tr>
                            <td><strong>#<?php echo e($row['id']); ?></strong></td>
                            <td><?php echo e($row['subject']); ?></td>
                            <td><?php echo e($row['requester_name']); ?></td>
                            <td><span class="badge <?php echo getStatusBadgeClass($row['status']); ?>"><?php echo e($row['status']); ?></span></td>
                            <td><?php echo date('Y-m-d', strtotime($row['created_at'])); ?></td>
                            <td><a href="request_details.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i> View</a></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center p-4">No requests found in the system.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>