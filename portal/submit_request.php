<?php
$page_title = 'Submit New Request';

require_once '../includes/db.php';
require_once '../includes/functions.php';

// --- ADDED: Include PHPMailer ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP; // For detailed error reports

// Load Composer's autoloader
require '../vendor/autoload.php';
// --- END OF ADDITION ---

requireLogin();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $subject = trim($_POST['subject']);
    $user_id = $_SESSION['user_id'];
    
    // Use a transaction
    $conn->begin_transaction();
    
    try {
        // 1. Insert the main request
        $stmt_req = $conn->prepare("INSERT INTO requests (user_id, subject, status) VALUES (?, ?, 'Pending Approval')");
        $stmt_req->bind_param("is", $user_id, $subject);
        $stmt_req->execute();
        
        $request_id = $stmt_req->insert_id;
        
        // 2. Insert all the items
        $stmt_item = $conn->prepare("INSERT INTO request_items (request_id, item_name, quantity, estimated_price) VALUES (?, ?, ?, ?)");
        
        foreach ($_POST['item_name'] as $key => $itemName) {
            if (!empty($itemName)) {
                $quantity = $_POST['quantity'][$key];
                $price = $_POST['estimated_price'][$key];
                
                $stmt_item->bind_param("isid", $request_id, $itemName, $quantity, $price);
                $stmt_item->execute();
            }
        }
        
        // If everything was successful, commit the changes
        $conn->commit();
        
        // --- ADDED: Email Notification Logic ---
        try {
            // Find all active finance approvers
            $approvers_stmt = $conn->prepare("SELECT email, full_name FROM users WHERE role = 'finance' AND is_active = 1");
            $approvers_stmt->execute();
            $approvers = $approvers_stmt->get_result();

            if ($approvers->num_rows > 0) {
                $mail = new PHPMailer(true);

                // --- Server settings for GMAIL ---
                $mail->SMTPDebug = SMTP::DEBUG_SERVER;  // This line will show you the full error log
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'tinashemuzenda368@gmail.com';         // ★★★ REPLACE THIS with your full Gmail address
                $mail->Password   = 'kazu kfou ywbv ogqw'; // ★★★ REPLACE THIS with your NEW Google App Password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                // --- END OF SETTINGS ---

                // Recipients
                $mail->setFrom('tinashemuzenda368@gmail.com', 'Kwekwe Procurement System'); // ★★★ REPLACE THIS with your Gmail address
                while ($approver = $approvers->fetch_assoc()) {
                    $mail->addAddress($approver['email'], $approver['full_name']);
                }

                // Content
                $mail->isHTML(true);
                $mail->Subject = "New Procurement Request Pending Approval: #" . $request_id;
                
                $link = "http://localhost/procurement_pro/portal/request_details.php?id=" . $request_id;

                $mail->Body    = "Hello,<br><br>A new procurement request has been submitted by " . e($_SESSION['full_name']) . " and is pending your approval.<br><br>"
                               . "<b>Request ID:</b> #" . $request_id . "<br>"
                               . "<b>Subject:</b> " . e($subject) . "<br><br>"
                               . "Please review the request at your earliest convenience.<br><br>"
                               . "<a href='" . $link . "' style='padding:10px 15px; background-color:#0d6efd; color:white; text-decoration:none; border-radius:5px;'>View Request Details</a><br><br>"
                               . "Thank you,<br>The Procurement System";

                $mail->send();
            }
        } catch (Exception $e) {
            // Email failed, but the request was still saved.
            // Show the user a more specific error message.
            $success = "Request #" . $request_id . " submitted successfully, but the email notification to finance FAILED. Please notify them manually. (Debug Error: {$mail->ErrorInfo})";
        }
        // --- END OF EMAIL LOGIC ---

        if (empty($success)) {
             $success = "Request #" . $request_id . " submitted successfully! The finance team has been notified.";
        }
        
    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        $error = "Error: Could not submit the request. Please try again.";
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
    <nav class="sidebar-nav">
        <a href="user_dashboard.php"><i class="icon fas fa-tachometer-alt"></i> My Requests</a>
        <a href="submit_request.php" class="active"><i class="icon fas fa-plus-circle"></i> New Request</a>
    </nav>
    <div class="sidebar-footer">
        <p>&copy; <?php echo date('Y'); ?>. All Rights Reserved.</p>
        <a href="../logout.php" class="btn btn-sm btn-outline-danger mt-2"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="main-content">
    <header class="main-header">
        <h1>Submit a Procurement Request</h1>
        <p class="lead">Fill out the details below. You can add multiple items to a single request.</p>
    </header>

    <?php if(isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?> <a href="user_dashboard.php">Go to Dashboard</a></div>
    <?php endif; ?>
    <?php if(isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <form action="submit_request.php" method="POST">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <div class="mb-4">
                    <label for="subject" class="form-label fs-5"><strong>Request Subject/Title</strong></label>
                    <input type="text" class="form-control" name="subject" required placeholder="e.g., New Laptops for Marketing Team">
                </div>
                
                <h5 class="mb-3">Request Items</h5>
                <div id="items-container">
                    <div class="row g-3 item-row mb-3 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label">Item Name</label>
                            <input type="text" name="item_name[]" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Quantity</label>
                            <input type="number" name="quantity[]" class="form-control" min="1" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Est. Price (per item)</label>
                            <input type="number" name="estimated_price[]" class="form-control" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-danger remove-item-btn" disabled><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                </div>
                <hr>
                <div classd-flex justify-content-between">
                     <button type="button" id="add-item-btn" class="btn btn-secondary"><i class="fas fa-plus"></i> Add Another Item</button>
                     <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-paper-plane"></i> Submit Request</button>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('items-container');
    const addItemBtn = document.getElementById('add-item-btn');
    const updateRemoveButtons = () => {
        const itemRows = container.querySelectorAll('.item-row');
        itemRows.forEach((row, index) => {
            row.querySelector('.remove-item-btn').disabled = itemRows.length === 1;
        });
    };
    addItemBtn.addEventListener('click', () => {
        const itemRow = container.querySelector('.item-row').cloneNode(true);
        itemRow.querySelectorAll('input').forEach(input => input.value = '');
        container.appendChild(itemRow);
        updateRemoveButtons();
    });
    container.addEventListener('click', (e) => {
        if (e.target.closest('.remove-item-btn')) {
            e.target.closest('.item-row').remove();
            updateRemoveButtons();
        }
    });
    updateRemoveButtons();
});
</script>

</body>
</html>