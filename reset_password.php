<?php
$page_title = 'Reset Password';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$token_is_valid = false;
$error_msg = '';
$success_msg = '';
$token = $_GET['token'] ?? ''; // Get token from URL

if (!empty($token)) {
    // --- THIS IS THE CRITICAL FIX ---
    // Hash the token from the URL to match the one in the database
    $hashed_token = hash('sha256', $token);
    // --- END OF FIX ---

    $stmt = $conn->prepare("SELECT id, reset_token_expiry FROM users WHERE reset_token = ?");
    $stmt->bind_param("s", $hashed_token);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        // Check if the token is expired
        if (strtotime($user['reset_token_expiry']) > time()) {
            $token_is_valid = true;
        } else {
            $error_msg = "This password reset link has expired.";
        }
    } else {
        $error_msg = "Invalid password reset link. It may have already been used.";
    }
} else {
    $error_msg = "No reset token provided.";
}

// Handle the form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $token_is_valid) {
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];

    if (strlen($password) < 8) {
        $error_msg = "Password must be at least 8 characters long.";
    } elseif ($password !== $password_confirm) {
        $error_msg = "The passwords do not match.";
    } else {
        // Hash the new password for secure storage
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Update password AND destroy the token so it can't be used again
        $update_stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?");
        // We get the $user['id'] from the $user variable we fetched earlier
        $update_stmt->bind_param("si", $hashed_password, $user['id']); 
        
        if ($update_stmt->execute()) {
            $success_msg = "Your password has been reset successfully! You can now log in.";
            $token_is_valid = false; // Hide the form after success
        } else {
            $error_msg = "There was an error updating your password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/jpeg" href="assets/favicon.jpeg"> 
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($page_title); ?> - ProcurementPRO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/login_glass.css">
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo"><i class="fas fa-lock-open"></i></div>
            <h2>Set New Password</h2>
        </div>

        <?php if ($success_msg): ?>
            <div class="alert alert-success"><?php echo $success_msg; ?></div>
            <div class="links"><a href="login.php" class="btn btn-primary">Proceed to Sign In</a></div>
        <?php elseif ($error_msg): ?>
            <div class="alert alert-danger"><?php echo $error_msg; ?></div>
             <div class="links"><a href="forgot_password.php">Request a new link</a></div>
        <?php endif; ?>

        <?php if ($token_is_valid): ?>
        <p style="color: #fff;">Please enter your new password below.</p>
        
        <form action="reset_password.php?token=<?php echo e($token); ?>" method="POST">
            <div class="input-group">
                <i class="icon fas fa-lock"></i>
                <input type="password" class="form-control" name="password" id="password" placeholder="New Password" required>
            </div>
             <div class="input-group">
                <i class="icon fas fa-lock"></i>
                <input type="password" class="form-control" name="password_confirm" id="password_confirm" placeholder="Confirm New Password" required>
            </div>
            <button type="submit" class="btn btn-primary">Reset Password</button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>