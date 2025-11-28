<?php require_once 'db.php'; ?>
<?php require_once 'functions.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/jpeg" href="../assets/favicon.jpeg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? e($page_title) : 'Procurement System'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
  <div class="container">
  <a class="navbar-brand" href="#">
    <img src="../assets/logo.png" alt="Kwekwe Polytechnic Logo" style="height: 40px;">
</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <?php if (isLoggedIn()): ?>
            <li class="nav-item"><a class="nav-link" href="../portal/user_dashboard.php">Dashboard</a></li>
            <?php if(hasRole('requester')): ?>
              <li class="nav-item"><a class="nav-link" href="../portal/submit_request.php">New Request</a></li>
            <?php endif; ?>
            <li class="nav-item"><span class="nav-link text-white">Welcome, <?php echo e($_SESSION['full_name']); ?>!</span></li>
            <li class="nav-item"><a class="btn btn-danger" href="../logout.php">Logout</a></li>
        <?php else: ?>
            <li class="nav-item"><a class="nav-link" href="../procurement_pro/login.php">Login</a></li>
            <li class="nav-item"><a class="nav-link" href="../procurement_pro/register.php">Register</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<div class="container">