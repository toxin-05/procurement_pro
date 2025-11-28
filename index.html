<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

$page_title = 'Login';

// If user is already logged in, redirect them to their dashboard
if (isset($_SESSION['user_id'])) {
    redirectUser($_SESSION['role']);
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        // This query correctly finds the user by email
        $stmt = $conn->prepare("SELECT id, full_name, password, role, is_active FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            // CRITICAL: Checks password AND if the account is active
            if (password_verify($password, $user['password']) && $user['is_active'] == 1) {
                // Set session variables upon successful login
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                
                // Redirect user based on their role
                redirectUser($user['role']);
            } else {
                // Generic error for security purposes
                $error = "Invalid login credentials or account is inactive.";
            }
        } else {
            $error = "Invalid login credentials or account is inactive.";
        }
        $stmt->close();
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
    <link rel="stylesheet" href="assets/login_glass.css">
</head>
<body>

    <div class="login-container">
        <div class="login-header">
            <div class="logo">
            <img src="assets/favicon.jpeg" alt="Kwekwe Polytechnic Logo" class="logo-img">
            </div>
            <h2>Welcome Back!</h2>
            <p>Sign in to access your dashboard.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" style="text-align: left;"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="input-group">
                <i class="icon fas fa-envelope"></i>
                <input type="email" class="form-control" name="email" placeholder="Email Address" required>
            </div>
            <div class="input-group">
                <i class="icon fas fa-lock"></i>
                <input type="password" class="form-control" name="password" placeholder="Password" required>
            </div>
            <button type="submit" class="btn btn-primary">Login</button>
        </form>

        <div class="links">
            <a href="register.php">Register Here</a>
            <a href="forgot_password.php">Forgot Password?</a>
        </div>
    </div>
    
</body>
</html>
