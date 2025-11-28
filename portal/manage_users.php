<?php
$page_title = 'Manage Users';
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();
if (!hasRole('admin')) {
    header("Location: user_dashboard.php");
    exit();
}

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

// Handle All Form Submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Handle Activate/Deactivate User
    if (isset($_POST['toggle_status'])) {
        $user_id = intval($_POST['user_id']);
        if ($user_id != $_SESSION['user_id']) {
            $stmt = $conn->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $_SESSION['success_msg'] = "User status updated successfully.";
            }
            $stmt->close();
        } else {
            $_SESSION['error_msg'] = "You cannot change your own status.";
        }
    }
    // Handle Role Change
    else if (isset($_POST['change_role'])) {
        $user_id = intval($_POST['user_id']);
        $new_role = $_POST['role'];
        $valid_roles = ['requester', 'finance', 'procurement_head', 'principal', 'procurement', 'admin'];

        if (in_array($new_role, $valid_roles) && $user_id != $_SESSION['user_id']) {
            $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->bind_param("si", $new_role, $user_id);
            if ($stmt->execute()) {
                $_SESSION['success_msg'] = "User role updated successfully!";
            }
            $stmt->close();
        }
    }
    // Handle User Information Update
    else if (isset($_POST['update_user'])) {
        $user_id = intval($_POST['user_id']);
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $department = trim($_POST['department']);

        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check_stmt->bind_param("si", $email, $user_id);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
            $_SESSION['error_msg'] = "Error: Another user with that email address already exists.";
        } else {
            $update_stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, department = ? WHERE id = ?");
            $update_stmt->bind_param("sssi", $full_name, $email, $department, $user_id);
            if ($update_stmt->execute()) {
                $_SESSION['success_msg'] = "User information updated successfully!";
            }
            $update_stmt->close();
        }
        $check_stmt->close();
    }
    // Handle New User Creation
    else if (isset($_POST['add_user'])) {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $department = trim($_POST['department']);
        $role = $_POST['role'];
        
        if (empty($role)) {
            $_SESSION['error_msg'] = "Error: You must select a role for the new user.";
        } else {
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            $check_stmt->store_result();

            if ($check_stmt->num_rows > 0) {
                $_SESSION['error_msg'] = "Error: A user with that email address already exists.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $insert_stmt = $conn->prepare("INSERT INTO users (full_name, email, password, department, role) VALUES (?, ?, ?, ?, ?)");
                $insert_stmt->bind_param("sssss", $full_name, $email, $hashed_password, $department, $role);
                
                if ($insert_stmt->execute()) {
                    $_SESSION['success_msg'] = "New user created successfully!";
                } else {
                    $_SESSION['error_msg'] = "Error: Could not create the user.";
                }
                $insert_stmt->close();
            }
            $check_stmt->close();
        }
    }
    // Handle User Deletion
    else if (isset($_POST['delete_user'])) {
        $user_id_to_delete = intval($_POST['user_id']);
        if ($user_id_to_delete != $_SESSION['user_id']) {
            try {
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id_to_delete);
                $stmt->execute();
                $_SESSION['success_msg'] = "User permanently deleted successfully!";
            } catch (mysqli_sql_exception $e) {
                $_SESSION['error_msg'] = "Cannot delete user. They are linked to existing records (e.g., requests). Consider deactivating them instead.";
            }
        } else {
            $_SESSION['error_msg'] = "You cannot delete your own account.";
        }
    }
    
    header("Location: manage_users.php");
    exit();
}

// Data Fetching with Search (includes is_active)
$search = $_GET['search'] ?? '';
$sql = "SELECT id, full_name, email, department, role, is_active FROM users";
if (!empty($search)) {
    $sql .= " WHERE full_name LIKE ? OR email LIKE ? OR department LIKE ?";
    $stmt = $conn->prepare($sql);
    $searchTerm = "%{$search}%";
    $stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
    $stmt->execute();
    $users = $stmt->get_result();
} else {
    $sql .= " ORDER BY full_name";
    $users = $conn->query($sql);
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
        <a href="admin_dashboard.php"><i class="icon fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="manage_users.php" class="active"><i class="icon fas fa-users"></i> Manage Users</a>
        <a href="manage_vendors.php"><i class="icon fas fa-store"></i> Manage Vendors</a>
        <a href="reports.php"><i class="icon fas fa-chart-pie"></i> Reports</a>
    </nav>
    <div class="sidebar-footer">
        <a href="../logout.php" class="btn btn-sm btn-outline-danger mt-2"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="main-content">
    <header class="main-header"><h1>User Management</h1></header>
    
    <?php if ($success_msg): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo $success_msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <?php if ($error_msg): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo $error_msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-users text-primary"></i> All System Users</h4>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal"><i class="fas fa-plus me-2"></i> Add New User</button>
        </div>
        <div class="card-body">
            <form method="GET" class="mb-3">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Search by name, email, or department..." value="<?php echo e($search); ?>">
                    <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
                    <?php if(!empty($search)): ?>
                        <a href="manage_users.php" class="btn btn-outline-danger" title="Clear Search"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr><th>User Details</th><th>Role</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php if ($users && $users->num_rows > 0): ?>
                            <?php while ($user = $users->fetch_assoc()): ?>
                            <tr class="<?php if(!$user['is_active']) echo 'table-secondary text-muted'; ?>">
                                <td>
                                    <strong><?php echo e($user['full_name']); ?></strong><br>
                                    <small><?php echo e($user['email']); ?></small><br>
                                    <small>Dept: <?php echo e($user['department']); ?></small>
                                </td>
                                <td><span class="badge bg-dark"><?php echo e(ucwords(str_replace('_', ' ', $user['role']))); ?></span></td>
                                <td>
                                    <?php if ($user['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <div class="d-flex flex-wrap">
                                        <button class="btn btn-sm btn-outline-secondary me-2 mb-1" data-bs-toggle="modal" data-bs-target="#editUserModal" data-user-id="<?php echo $user['id']; ?>" data-full-name="<?php echo e($user['full_name']); ?>" data-email="<?php echo e($user['email']); ?>" data-department="<?php echo e($user['department']); ?>" title="Edit User Details"><i class="fas fa-edit"></i></button>

                                        <form method="POST" class="d-flex align-items-center me-2 mb-1">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <select name="role" class="form-select form-select-sm" onchange="this.form.submit()" title="Change Role">
                                                <option value="requester" <?php if($user['role'] == 'requester') echo 'selected'; ?>>Requester</option>
                                                <option value="finance" <?php if($user['role'] == 'finance') echo 'selected'; ?>>Finance</option>
                                                <option value="procurement_head" <?php if($user['role'] == 'procurement_head') echo 'selected'; ?>>Head of Procurement</option>
                                                <option value="principal" <?php if($user['role'] == 'principal') echo 'selected'; ?>>Principal</option>
                                                <option value="procurement" <?php if($user['role'] == 'procurement') echo 'selected'; ?>>Procurement</option>
                                                <option value="admin" <?php if($user['role'] == 'admin') echo 'selected'; ?>>Admin</option>
                                            </select>
                                        </form>

                                        <form method="POST" class="me-2 mb-1">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <?php if ($user['is_active']): ?>
                                                <button type="submit" name="toggle_status" class="btn btn-sm btn-outline-warning" title="Deactivate User"><i class="fas fa-user-slash"></i></button>
                                            <?php else: ?>
                                                <button type="submit" name="toggle_status" class="btn btn-sm btn-outline-success" title="Activate User"><i class="fas fa-user-check"></i></button>
                                            <?php endif; ?>
                                        </form>
                                        
                                        <form method="POST" class="mb-1" onsubmit="return confirm('WARNING: This will permanently delete the user. This action cannot be undone. Are you sure?');">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="delete_user" class="btn btn-sm btn-outline-danger" title="Permanently Delete User"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-muted fst-italic"><i class="fas fa-user-shield"></i> (You)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center p-4">No users found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addUserModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add New User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <div class="modal-body">
            <div class="mb-3"><label class="form-label">Full Name</label><input type="text" class="form-control" name="full_name" required></div>
            <div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" required></div>
            <div class="mb-3"><label class="form-label">Password (min 8 characters)</label><input type="password" class="form-control" name="password" minlength="8" required></div>
            <div class="mb-3"><label class="form-label">Department</label><input type="text" class="form-control" name="department" required></div>
            <div class="mb-3"><label class="form-label">Role</label>
                <select name="role" class="form-select" required>
                    <option value="" disabled selected>-- Select a Role --</option>
                    <option value="requester">Requester</option>
                    <option value="finance">Finance</option>
                    <option value="procurement_head">Head of Procurement</option>
                    <option value="principal">Principal</option>
                    <option value="procurement">Procurement</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" name="add_user" class="btn btn-primary">Create User</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editUserModalLabel">Edit User Information</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <div class="modal-body">
            <input type="hidden" name="user_id" id="edit_user_id">
            <div class="mb-3"><label class="form-label">Full Name</label><input type="text" class="form-control" name="full_name" id="edit_full_name" required></div>
            <div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" id="edit_email" required></div>
            <div class="mb-3"><label class="form-label">Department</label><input type="text" class="form-control" name="department" id="edit_department" required></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="update_user" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const editUserModal = document.getElementById('editUserModal');
    if (editUserModal) {
        editUserModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-user-id');
            const fullName = button.getAttribute('data-full-name');
            const email = button.getAttribute('data-email');
            const department = button.getAttribute('data-department');

            const modalTitle = editUserModal.querySelector('.modal-title');
            const userIdInput = editUserModal.querySelector('#edit_user_id');
            const fullNameInput = editUserModal.querySelector('#edit_full_name');
            const emailInput = editUserModal.querySelector('#edit_email');
            const departmentInput = editUserModal.querySelector('#edit_department');

            modalTitle.textContent = 'Edit User: ' + fullName;
            userIdInput.value = userId;
            fullNameInput.value = fullName;
            emailInput.value = email;
            departmentInput.value = department;
        });
    }
});
</script>
</body>
</html>