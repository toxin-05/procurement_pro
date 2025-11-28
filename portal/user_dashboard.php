<?php
$page_title = 'User Dashboard';
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();
$role = $_SESSION['role'];

if ($role != 'requester') {
    redirectUser($role);
    exit();
}

$user_id = $_SESSION['user_id'];

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

// Handle Request Deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_request'])) {
    $request_id_to_delete = intval($_POST['request_id']);

    $check_stmt = $conn->prepare("SELECT status FROM requests WHERE id = ? AND user_id = ?");
    $check_stmt->bind_param("ii", $request_id_to_delete, $user_id);
    $check_stmt->execute();
    $request = $check_stmt->get_result()->fetch_assoc();

    if ($request && $request['status'] == 'Pending Approval') {
        $conn->begin_transaction();
        try {
            // Deletion is handled by ON DELETE CASCADE in the database schema
            $stmt_req = $conn->prepare("DELETE FROM requests WHERE id = ?");
            $stmt_req->bind_param("i", $request_id_to_delete);
            $stmt_req->execute();

            $conn->commit();
            $_SESSION['success_msg'] = "Request #" . $request_id_to_delete . " has been successfully deleted.";
        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            $_SESSION['error_msg'] = "Error: Could not delete the request.";
        }
    } else {
        $_SESSION['error_msg'] = "Error: You can only delete your own requests that are still pending approval.";
    }
    header("Location: user_dashboard.php");
    exit();
}

// --- Efficient Data Fetching for Dashboard Widgets ---
$counts_sql = "
    SELECT
        COUNT(id) as total,
        SUM(CASE WHEN status NOT IN ('Completed', 'Rejected') THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected
    FROM requests
    WHERE user_id = ?
";
$stmt_counts = $conn->prepare($counts_sql);
$stmt_counts->bind_param("i", $user_id);
$stmt_counts->execute();
$counts = $stmt_counts->get_result()->fetch_assoc();

$total_requests = $counts['total'] ?? 0;
$pending_requests_count = $counts['pending'] ?? 0;
$completed_requests_count = $counts['completed'] ?? 0;
$rejected_requests_count = $counts['rejected'] ?? 0;


// Fetch all requests for the current user
$stmt = $conn->prepare("
    SELECT r.id, r.subject, r.status, r.created_at, r.rejection_reason,
           GROUP_CONCAT(ri.item_name SEPARATOR ', ') as items
    FROM requests r
    LEFT JOIN request_items ri ON r.id = ri.request_id
    WHERE r.user_id = ?
    GROUP BY r.id
    ORDER BY r.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$requests = $stmt->get_result();
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
        <a href="user_dashboard.php" class="active"><i class="icon fas fa-tachometer-alt"></i> My Requests</a>
        <a href="submit_request.php"><i class="icon fas fa-plus-circle"></i> New Request</a>
        
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

    <?php if (!empty($success_msg)): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo $success_msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <?php if (!empty($error_msg)): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo $error_msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

    <div class="row">
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="stat-card bg-primary">
                <i class="fas fa-file-alt"></i>
                <div>Total Requests<span><?php echo $total_requests; ?></span></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="stat-card bg-warning">
                <i class="fas fa-hourglass-half"></i>
                <div>Pending / In-Progress<span><?php echo $pending_requests_count; ?></span></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="stat-card bg-success">
                <i class="fas fa-check-double"></i>
                <div>Completed<span><?php echo $completed_requests_count; ?></span></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="stat-card bg-danger">
                <i class="fas fa-times-circle"></i>
                <div>Rejected<span><?php echo $rejected_requests_count; ?></span></div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-history text-primary me-2"></i> My Submission History</h4>
            <a href="submit_request.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i> Create New Request</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 10%;">Request ID</th>
                            <th style="width: 45%;">Subject & Items</th>
                            <th class="text-center" style="width: 15%;">Status</th>
                            <th style="width: 15%;">Date Submitted</th>
                            <th class="text-center pe-4" style="width: 15%;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($requests && $requests->num_rows > 0): ?>
                            <?php while($row = $requests->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4">
                                    <span class="fw-bold">#<?php echo e($row['id']); ?></span>
                                </td>
                                <td>
                                    <strong class="d-block"><?php echo e($row['subject']); ?></strong>
                                    <small class="text-muted" data-bs-toggle="tooltip" title="<?php echo e($row['items'] ?? 'No items'); ?>">
                                        <?php echo e(mb_strimwidth($row['items'] ?? 'No items listed', 0, 70, "...")); ?>
                                    </small>
                                    <?php if ($row['status'] == 'Rejected' && !empty($row['rejection_reason'])): ?>
                                        <div class="mt-2 text-danger fst-italic small">
                                            <i class="fas fa-comment-dots me-1"></i>
                                            <strong>Reason:</strong> <?php echo e($row['rejection_reason']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php
                                    $status = e($row['status']);
                                    $badgeClass = 'secondary';
                                    $icon = 'fa-question-circle';
                                    switch ($status) {
                                        case 'Pending Approval': $badgeClass = 'warning text-dark'; $icon = 'fa-hourglass-half'; break;
                                        case 'Approved':         $badgeClass = 'info'; $icon = 'fa-check'; break;
                                        case 'Processing':       $badgeClass = 'primary'; $icon = 'fa-cogs'; break;
                                        case 'Completed':        $badgeClass = 'success'; $icon = 'fa-check-double'; break;
                                        case 'Rejected':         $badgeClass = 'danger'; $icon = 'fa-times-circle'; break;
                                    }
                                    ?>
                                    <span class="badge rounded-pill bg-<?php echo $badgeClass; ?> px-2 py-1">
                                        <i class="fas <?php echo $icon; ?> me-1"></i> <?php echo $status; ?>
                                    </span>
                                </td>
                                <td data-bs-toggle="tooltip" title="<?php echo date('F j, Y, g:i a', strtotime($row['created_at'])); ?>">
                                    <?php echo date('M j, Y', strtotime($row['created_at'])); ?>
                                </td>
                                <td class="text-center pe-4">
                                    <a href="request_details.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary me-1" title="View Details">
                                        <i class="fas fa-eye me-1"></i> View
                                    </a>
                                    <?php if ($row['status'] == 'Pending Approval'): ?>
                                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this request?');" class="d-inline">
                                        <input type="hidden" name="request_id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" name="delete_request" class="btn btn-sm btn-outline-danger" title="Delete Request">
                                            <i class="fas fa-trash me-1"></i> Delete
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center p-5">
                                    <i class="fas fa-folder-open fa-4x text-muted mb-3"></i>
                                    <h5 class="mb-1">No Requests Found</h5>
                                    <p class="text-muted">Click the button below to create your first procurement request.</p>
                                    <a href="submit_request.php" class="btn btn-success mt-2"><i class="fas fa-plus me-2"></i>Create Your First Request</a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Initialize Bootstrap Tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })
</script>
</body>
</html>