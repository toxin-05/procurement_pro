<?php
$page_title = 'Forgot Password';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP; // Added for debug output

// Load Composer's autoloader. This is required for PHPMailer to work.
require 'vendor/autoload.php';

// If user is already logged in, redirect them
if (isset($_SESSION['user_id'])) {
    redirectUser($_SESSION['role']);
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Generate a secure, user-facing token
        $token = bin2hex(random_bytes(50));
        
        // Use a consistent hash for the token (sha256)
        $hashed_token = hash('sha256', $token);
        
        // Set a 1-hour expiry
        $expiry = date("Y-m-d H:i:s", time() + 3600);
        
        $update_stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE email = ?");
        $update_stmt->bind_param("sss", $hashed_token, $expiry, $email);
        $update_stmt->execute();

        // --- Secure Email Sending with PHPMailer ---
        $mail = new PHPMailer(true);
        try {
            // -- Server settings for GMAIL --
            
            // ★★★ THIS IS FOR DEBUGGING - IT WILL PRINT THE FULL CONNECTION LOG ★★★
            // $mail->SMTPDebug = SMTP::DEBUG_SERVER; 
            
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'tinashemuzenda368@gmail.com';         // ★★★ REPLACE THIS with your full Gmail address
            $mail->Password   = 'kazu kfou ywbv ogqw'; // ★★★ REPLACE THIS with your Google App Password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            // --- End of Server settings ---

            // -- Recipients --
            $mail->setFrom('tinashemuzenda@gmail.com', 'ProcurementPRO Support'); // ★★★ REPLACE THIS
            $mail->addAddress($email);

            // -- Content --
            $reset_link = "http://localhost/procurement_pro/reset_password.php?token=" . $token;
            $mail->isHTML(true);
            $mail->Subject = 'Your Password Reset Request';
            $mail->Body    = "Hi,<br><br>A request has been made to reset your password. Please click the link below to proceed:<br><br>";
            $mail->Body   .= "<a href='{$reset_link}' style='padding:10px 15px; background-color:#0d6efd; color:white; text-decoration:none; border-radius:5px;'>Reset Your Password</a><br><br>";
            $mail->Body   .= "This link will expire in one hour.<br><br>If you did not request this, please ignore this email.";

            $mail->send();

        } catch (Exception $e) {
            // In a real app, you would log this error.
            // We'll set the message to the error so you can see it.
            $message = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }
    }
    
    // If the message wasn't set by an error, set the success message.
    if (empty($message)) {
        $message = "If an account with that email exists, you will receive a password reset link shortly.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/jpeg" href="../assets/favicon.jpeg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($page_title); ?> - ProcurementPRO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <!-- Link to the same stylesheet as your login page -->
    <link rel="stylesheet" href="assets/login_glass.css">
</head>
<body>

    <div class="login-container">
        <div class="login-header">
            <div class="logo">
                <!-- Using a key icon, which is more appropriate for password reset -->
                <i class="fas fa-key"></i>
            </div>
            <h2>Forgot Password</h2>
            <p>Enter your email to receive a reset link.</p>
        </div>

        <?php if (!empty($message)): ?>
            <!-- Displaying the info/error message -->
            <div class="alert alert-info" style="text-align: left;"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="input-group">
                <i class="icon fas fa-envelope"></i>
                <input type="email" class="form-control" name="email" placeholder="Your Email Address" required>
            </div>
            <button type="submit" class="btn btn-primary">Send Reset Link</button>
        </form>

        <div class="links">
            <!-- Updated links to be relevant to this page -->
            <a href="login.php">Remember your password? Sign In</a>
        </div>
    </div>
    
</body>
</html>