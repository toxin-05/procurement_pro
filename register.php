<?php
$page_title = 'Register';
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    header("Location: portal/user_dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $department = trim($_POST['department']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];

    if ($password !== $password_confirm) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $error = "An account with this email already exists.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_stmt = $conn->prepare("INSERT INTO users (full_name, email, department, password) VALUES (?, ?, ?, ?)");
            $insert_stmt->bind_param("ssss", $full_name, $email, $department, $hashed_password);
            
            if ($insert_stmt->execute()) {
                $success = "Registration successful! You can now <a href='login.php'>log in</a>.";
            } else {
                $error = "Registration failed. Please try again.";
            }
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
    <link rel="stylesheet" href="assets/auth_style.css">
</head>
<body>

    <div class="auth-container">
        <div class="auth-header">
            <div class="logo">
                <i class="fas fa-user-plus"></i>
            </div>
            <h2>Create Account</h2>
            <p>Join the ProcurementPRO system.</p>
        </div>

        <?php if(isset($error)): ?>
            <div class="alert alert-danger" style="text-align: left;"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if(isset($success)): ?>
            <div class="alert alert-success" style="text-align: left;"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (!isset($success)): // Hide form after successful registration ?>
        <form action="register.php" method="POST">
            <div class="input-group">
                <i class="icon fas fa-user"></i>
                <input type="text" class="form-control" name="full_name" placeholder="Full Name" required>
            </div>
            <div class="input-group">
                <i class="icon fas fa-envelope"></i>
                <input type="email" class="form-control" name="email" placeholder="Email Address" required>
            </div>
             <div class="input-group">
                <i class="icon fas fa-building"></i>
                <input type="text" class="form-control" name="department" placeholder="Department" required>
            </div>
            <div class="input-group">
                <i class="icon fas fa-lock"></i>
                <input type="password" class="form-control" name="password" placeholder="Password" required>
            </div>
             <div class="input-group">
                <i class="icon fas fa-lock"></i>
                <input type="password" class="form-control" name="password_confirm" placeholder="Confirm Password" required>
            </div>
            <button type="submit" class="btn btn-primary">Create Account</button>
        </form>
        <?php endif; ?>

        <div class="links">
            <a href="login.php">Already have an account? Login</a>
        </div>
    </div>
    
</body>
</html>