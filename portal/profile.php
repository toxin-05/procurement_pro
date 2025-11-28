<?php
$page_title = 'My Profile';
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();
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

// Handle Signature Data Upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['signature_data'])) {
    $signature_data = $_POST['signature_data'];

    if (preg_match('/^data:image\/png;base64,/', $signature_data)) {
        $img_data = str_replace('data:image/png;base64,', '', $signature_data);
        $img_data = str_replace(' ', '+', $img_data);
        $decoded_img = base64_decode($img_data);

        $upload_dir = '../uploads/signatures/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_name = 'user_' . $user_id . '_signature.png';
        $file_path = $upload_dir . $file_name;
        // IMPROVED: Store a path relative to the project root for better portability
        $db_path = 'uploads/signatures/' . $file_name;

        if (file_put_contents($file_path, $decoded_img)) {
            $stmt = $conn->prepare("UPDATE users SET signature_path = ? WHERE id = ?");
            $stmt->bind_param("si", $db_path, $user_id);
            if ($stmt->execute()) {
                $_SESSION['success_msg'] = "Signature saved successfully!";
            } else {
                $_SESSION['error_msg'] = "Database error: Could not save signature path.";
            }
            $stmt->close();
        } else {
            $_SESSION['error_msg'] = "Failed to save the signature image.";
        }
    } else {
        $_SESSION['error_msg'] = "Invalid signature data received.";
    }

    header("Location: profile.php");
    exit();
}

// Fetch current user's data
$user_stmt = $conn->prepare("SELECT full_name, email, department, role, signature_path FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();

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
  
    <style>
        .signature-pad-container {
            border: 1px dashed #ccc;
            border-radius: 5px;
            cursor: crosshair;
            touch-action: none;
        }
        canvas {
            width: 100%;
            height: 200px;
        }
    </style>
</head>
<body>

<div class="sidebar">
<div class="sidebar-header">
    <img src="../assets/favicon.jpeg" alt="Kwekwe Polytechnic Logo" class="sidebar-logo">
</div>
    <nav class="sidebar-nav">
        <a href="principal_dashboard.php"><i class="icon fas fa-arrow-left"></i> Back to Dashboard</a>
    </nav>
    <div class="sidebar-footer">
        <a href="../logout.php" class="btn btn-sm btn-outline-danger mt-2"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="main-content">
    <header class="main-header"><h1><?php echo e($page_title); ?></h1></header>
    
    <?php if ($success_msg): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo $success_msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <?php if ($error_msg): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo $error_msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h4 class="mb-0"><i class="fas fa-user-circle text-primary"></i> Your Information</h4>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Name:</strong> <?php echo e($user['full_name']); ?></p>
                    <p><strong>Email:</strong> <?php echo e($user['email']); ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Department:</strong> <?php echo e($user['department']); ?></p>
                    <p><strong>Role:</strong> <span class="badge bg-secondary"><?php echo e(ucwords(str_replace('_', ' ', $user['role']))); ?></span></p>
                </div>
            </div>
            <hr>
            
            <h4 class="mb-3"><i class="fas fa-signature text-success"></i> Manage Your Signature</h4>
            <div class="row">
                <div class="col-md-7">
                    <p class="text-muted">Draw your signature in the box below. It will be used on documents you approve.</p>
                    <div class="signature-pad-container">
                        <canvas id="signature-pad"></canvas>
                    </div>
                    <div class="mt-2">
                        <button id="clear-button" type="button" class="btn btn-sm btn-secondary"><i class="fas fa-eraser me-1"></i> Clear</button>
                    </div>
                </div>
                <div class="col-md-5 text-center">
                    <h6>Current Signature on File</h6>
                    <?php if (!empty($user['signature_path'])): ?>
                        <img src="../<?php echo e($user['signature_path']); ?>" alt="Current Signature" class="img-fluid border rounded p-2" style="max-height: 150px;">
                    <?php else: ?>
                        <div class="d-flex align-items-center justify-content-center text-muted border rounded p-4" style="min-height: 150px;">
                            <span>No signature uploaded yet.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <hr>
            <form id="signature-form" method="POST">
                <input type="hidden" name="signature_data" id="signature-data">
                <button id="save-button" type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i> Save New Signature</button>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('signature-pad');
    const signaturePad = new SignaturePad(canvas, {
        backgroundColor: 'rgb(255, 255, 255)'
    });

    function resizeCanvas() {
        const ratio =  Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext("2d").scale(ratio, ratio);
        signaturePad.clear(); // Clear signature on resize
    }

    window.addEventListener("resize", resizeCanvas);
    resizeCanvas();

    document.getElementById('clear-button').addEventListener('click', function () {
        signaturePad.clear();
    });

    document.getElementById('signature-form').addEventListener('submit', function (event) {
        if (signaturePad.isEmpty()) {
            alert("Please provide a signature first.");
            event.preventDefault();
        } else {
            const dataURL = signaturePad.toDataURL('image/png');
            document.getElementById('signature-data').value = dataURL;
        }
    });
});
</script>

</body>
</html>