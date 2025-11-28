<?php
$page_title = 'Request Details';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// --- ADDED: Include PHPMailer ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP; 

// Load Composer's autoloader
require '../vendor/autoload.php';
// --- END OF ADDITION ---

requireLogin();

$user_role = $_SESSION['role'];

// Determine the correct dashboard link based on user role
$dashboard_link = 'user_dashboard.php'; // Default for requester
switch ($user_role) {
    case 'admin':            $dashboard_link = 'admin_dashboard.php'; break;
    case 'finance':          $dashboard_link = 'finance_dashboard.php'; break;
    case 'procurement_head': $dashboard_link = 'procurement_head_dashboard.php'; break;
    case 'principal':        $dashboard_link = 'principal_dashboard.php'; break;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: " . $dashboard_link);
    exit();
}
$request_id = intval($_GET['id']);

// --- MOVED UP: Fetch request details FIRST ---
$sql = "
    SELECT r.*, u.full_name as requester_name, u.department,
           fin.full_name as finance_approver_name,
           hop.full_name as procurement_head_approver_name,
           princ.full_name as principal_approver_name
    FROM requests r 
    JOIN users u ON r.user_id = u.id
    LEFT JOIN users fin ON r.finance_approver_id = fin.id
    LEFT JOIN users hop ON r.procurement_head_approver_id = hop.id
    LEFT JOIN users princ ON r.principal_approver_id = princ.id
    WHERE r.id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();

if (!$request) { 
    $_SESSION['error_msg'] = "The requested record could not be found.";
    header("Location: " . $dashboard_link);
    exit();
}

// Handle session flash messages
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

// --- POST logic ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $approver_id = $_SESSION['user_id'];
    $current_status = $request['status'];
    $now = date('Y-m-d H:i:s');
    $update_stmt = false;
    $notify_role = ''; 

    // APPROVAL LOGIC
    if (isset($_POST['approve'])) {
        $next_status = '';
        $sql_update = '';

        if (($user_role == 'finance' || $user_role == 'admin') && $current_status == 'Pending Approval') {
            $next_status = 'Finance Approved';
            $sql_update = "UPDATE requests SET status = ?, finance_approver_id = ?, finance_approval_date = ? WHERE id = ?";
            $notify_role = 'procurement_head'; 
        } elseif (($user_role == 'procurement_head' || $user_role == 'admin') && $current_status == 'Finance Approved') {
            $next_status = 'PMU Approved';
            $sql_update = "UPDATE requests SET status = ?, procurement_head_approver_id = ?, procurement_head_approval_date = ? WHERE id = ?";
            $notify_role = 'principal'; 
        } elseif (($user_role == 'principal' || $user_role == 'admin') && $current_status == 'PMU Approved') {
            $next_status = 'Principal Approved';
            $sql_update = "UPDATE requests SET status = ?, principal_approver_id = ?, principal_approval_date = ? WHERE id = ?";
        }

        if ($sql_update) {
            $update_stmt = $conn->prepare($sql_update);
            $update_stmt->bind_param("sisi", $next_status, $approver_id, $now, $request_id);
            if ($update_stmt->execute()) {
                $_SESSION['success_msg'] = "Request #" . $request_id . " has been approved and forwarded.";

                // ★★★ EMBEDDED EMAIL NOTIFICATION LOGIC ★★★
                if ($notify_role) {
                    try {
                        $next_approvers_stmt = $conn->prepare("SELECT email, full_name FROM users WHERE role = ? AND is_active = 1");
                        $next_approvers_stmt->bind_param("s", $notify_role);
                        $next_approvers_stmt->execute();
                        $next_approvers = $next_approvers_stmt->get_result();

                        if ($next_approvers->num_rows > 0) {
                            $mail = new PHPMailer(true);
                            
                            // Server settings (using Gmail)
                            $mail->SMTPDebug = SMTP::DEBUG_SERVER; // ★★★ THIS WILL SHOW YOU ALL ERRORS ★★★
                            $mail->isSMTP();
                            $mail->Host       = 'smtp.gmail.com';
                            $mail->SMTPAuth   = true;
                            $mail->Username   = 'tinashemuzenda368@gmail.com';         // ★★★ REPLACE THIS
                            $mail->Password   = 'kazu kfou ywbv ogqw'; // ★★★ REPLACE THIS
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                            $mail->Port       = 587;

                            // Recipients
                            $mail->setFrom('tinashemuzenda368@gmail.com', 'Kwekwe Procurement System'); // ★★★ REPLACE THIS
                            while ($approver = $next_approvers->fetch_assoc()) {
                                $mail->addAddress($approver['email'], $approver['full_name']);
                            }

                            // Content
                            $mail->isHTML(true);
                            $mail->Subject = "Request Awaiting Your Approval: #" . $request_id;
                            $link = "http://localhost/procurement_pro/portal/request_details.php?id=" . $request_id;
                            
                            $mail->Body    = "Hello,<br><br>A procurement request has been approved and is now pending your review.<br><br>"
                                           . "<b>Request ID:</b> #" . $request_id . "<br>"
                                           . "<b>Subject:</b> " . e($request['subject']) . "<br><br>"
                                           . "Please review the request at your earliest convenience.<br><br>"
                                           . "<a href='" . $link . "' style='padding:10px 15px; background-color:#0d6efd; color:white; text-decoration:none; border-radius:5px;'>View Request Details</a><br><br>"
                                           . "Thank you,<br>The Procurement System";

                            $mail->send();
                        }
                    } catch (Exception $e) {
                        $_SESSION['success_msg'] = "Request #" . $request_id . " approved, but the email notification to the next approver FAILED. Please notify them manually.";
                        error_log("Mailer Error (to $notify_role): " . $mail->ErrorInfo);
                    }
                }
            } else {
                $_SESSION['error_msg'] = "Database error: Could not approve the request.";
            }
        } else {
            $_SESSION['error_msg'] = "You do not have permission to approve this request at its current stage.";
        }
    }

    // REJECTION LOGIC
    if (isset($_POST['reject'])) {
        $reason = trim($_POST['rejection_reason']);
        if (!empty($reason)) {
            $stmt_reject = $conn->prepare("UPDATE requests SET status = 'Rejected', rejection_reason = ? WHERE id = ?");
            $stmt_reject->bind_param("si", $reason, $request_id);
            if ($stmt_reject->execute()) {
                $_SESSION['success_msg'] = "Request #" . $request_id . " has been rejected.";
            } else {
                $_SESSION['error_msg'] = "Database error: Could not reject the request.";
            }
        } else {
             $_SESSION['error_msg'] = "A reason is required to reject a request.";
        }
    }
    
    header("Location: request_details.php?id=" . $request_id);
    exit();
}

// Fetch request items
$items_stmt = $conn->prepare("SELECT * FROM request_items WHERE request_id = ?");
$items_stmt->bind_param("i", $request_id);
$items_stmt->execute();
$items = $items_stmt->get_result();
$total_cost = 0;

// Logic to determine if the current user can take action
$can_take_action = false;
$request_status = $request['status'];

if ( ($user_role == 'finance' && $request_status == 'Pending Approval') ||
     ($user_role == 'procurement_head' && $request_status == 'Finance Approved') ||
     ($user_role == 'principal' && $request_status == 'PMU Approved') ||
     ($user_role == 'admin' && !in_array($request_status, ['Principal Approved', 'Rejected', 'Processing', 'Completed'])) ) {
    $can_take_action = true;
}

if (!function_exists('getStatusBadgeClass')) {
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
    <div class="sidebar-header"><img src="../assets/favicon.jpeg" alt="Kwekwe Polytechnic Logo" class="sidebar-logo"></div>
    <nav class="sidebar-nav"><a href="<?php echo $dashboard_link; ?>"><i class="icon fas fa-arrow-left"></i> Back to Dashboard</a></nav>
    <div class="sidebar-footer"><a href="../logout.php" class="btn btn-sm btn-outline-danger mt-2"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
</div>
<div class="main-content">
    <header class="main-header"><h1>Request #<?php echo e($request['id']); ?> Details</h1></header>
    
    <?php if (!empty($success_msg)): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo $success_msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <?php if (!empty($error_msg)): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo $error_msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header"><h4><i class="fas fa-list-ul text-primary"></i> Requested Items</h4></div>
                <div class="card-body p-0">
                    <table class="table table-striped table-hover mb-0">
                        <thead class="table-light"><tr><th>Item Name</th><th>Quantity</th><th>Estimated Price</th><th>Subtotal</th></tr></thead>
                        <tbody>
                            <?php while($item = $items->fetch_assoc()): ?>
                            <?php $subtotal = ($item['quantity'] ?? 0) * ($item['estimated_price'] ?? 0); $total_cost += $subtotal; ?>
                            <tr>
                                <td><?php echo e($item['item_name']); ?></td>
                                <td><?php echo e($item['quantity']); ?></td>
                                <td>$<?php echo number_format($item['estimated_price'] ?? 0, 2); ?></td>
                                <td>$<?php echo number_format($subtotal, 2); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot><tr class="table-light"><th colspan="3" class="text-end">Total Estimated Cost:</th><th class="fs-5">$<?php echo number_format($total_cost, 2); ?></th></tr></tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm mb-4">
                 <div class="card-header"><h4><i class="fas fa-info-circle text-info"></i> Information</h4></div>
                 <ul class="list-group list-group-flush">
                     <li class="list-group-item"><strong>Subject:</strong> <?php echo e($request['subject']); ?></li>
                     <li class="list-group-item"><strong>Requester:</strong> <?php echo e($request['requester_name']); ?></li>
                     <li class="list-group-item"><strong>Status:</strong> <span class="badge <?php echo getStatusBadgeClass($request['status']); ?>"><?php echo e($request['status']); ?></span></li>
                 </ul>
            </div>
            
            <div class="card shadow-sm mb-4">
                  <div class="card-header"><h4><i class="fas fa-history text-secondary"></i> Approval History</h4></div>
                  <ul class="list-group list-group-flush">
                      <li class="list-group-item"><strong>Finance:</strong> 
                          <?php if($request['finance_approval_date']): ?><span class="text-success">Approved by <?php echo e($request['finance_approver_name']); ?></span>
                          <?php else: ?><span class="text-muted">Pending</span><?php endif; ?>
                      </li>
                      <li class="list-group-item"><strong>Head of Procurement:</strong> 
                          <?php if($request['procurement_head_approval_date']): ?><span class="text-success">Approved by <?php echo e($request['procurement_head_approver_name']); ?></span>
                          <?php else: ?><span class="text-muted">Pending</span><?php endif; ?>
                      </li>
                      <li class="list-group-item"><strong>Principal:</strong> 
                          <?php if($request['principal_approval_date']): ?><span class="text-success">Approved by <?php echo e($request['principal_approver_name']); ?></span>
                          <?php else: ?><span class="text-muted">Pending</span><?php endif; ?>
                      </li>
                  </ul>
            </div>
            
            <?php if ($can_take_action): ?>
            <div class="card shadow-sm">
                 <div class="card-header"><h4><i class="fas fa-tasks text-success"></i> Your Action</h4></div>
                 <div class="card-body">
                    <form action="request_details.php?id=<?php echo $request_id; ?>" method="POST" class="mb-3">
                        <button type="submit" name="approve" class="btn btn-success w-100"><i class="fas fa-check"></i> Approve & Forward</button>
                    </form>
                    <form action="request_details.php?id=<?php echo $request_id; ?>" method="POST">
                        <div class="mb-2"><label class="form-label">Reason for Rejection</label><textarea name="rejection_reason" class="form-control" required></textarea></div>
                        <button type="submit" name="reject" class="btn btn-danger w-100"><i class="fas fa-times"></i> Reject Request</button>
                    </form>
                 </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>