<?php
$page_title = 'Admin Dashboard';
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();
if (!hasRole('admin')) {
    header("Location: user_dashboard.php");
    exit();
}

// MODIFIED: Stat cards now show user and vendor counts.
$active_user_count = $conn->query("SELECT COUNT(id) as count FROM users WHERE is_active = 1")->fetch_assoc()['count'];
$inactive_user_count = $conn->query("SELECT COUNT(id) as count FROM users WHERE is_active = 0")->fetch_assoc()['count'];
$vendor_count = $conn->query("SELECT COUNT(id) as count FROM vendors")->fetch_assoc()['count'];
$total_requests_count = $conn->query("SELECT COUNT(id) as count FROM requests")->fetch_assoc()['count'];


// MODIFIED: Chart data now shows users by role.
$chart_data_sql = "SELECT role, COUNT(id) as count FROM users GROUP BY role";
$chart_result = $conn->query($chart_data_sql);
$chart_labels = [];
$chart_values = [];
while ($row = $chart_result->fetch_assoc()) {
    $chart_labels[] = ucwords(str_replace('_', ' ', $row['role']));
    $chart_values[] = $row['count'];
}

// MODIFIED: Activity feed is now restricted to admin-related events.
$recent_activity_sql = "
    (SELECT 'user_created' as type, u.full_name as main_text, u.email as sub_text, u.created_at as activity_date, u.id FROM users u)
    UNION ALL
    (SELECT 'vendor_created' as type, v.vendor_name as main_text, v.contact_person as sub_text, v.created_at as activity_date, v.id FROM vendors v)
    ORDER BY activity_date DESC
    LIMIT 7
";
$recent_activity = $conn->query($recent_activity_sql);

// Helper function to display time in a "time ago" format
function time_ago($datetime) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;
    $string = ['y' => 'year','m' => 'month','w' => 'week','d' => 'day','h' => 'hour','i' => 'minute','s' => 'second'];
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }
    $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
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
        <a href="admin_dashboard.php" class="active"><i class="icon fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="manage_users.php"><i class="icon fas fa-users"></i> Manage Users</a>
        <a href="manage_vendors.php"><i class="icon fas fa-store"></i> Manage Vendors</a>
        <a href="reports.php"><i class="icon fas fa-chart-pie"></i> Reports</a>
    </nav>
    <div class="sidebar-footer">
        <a href="../logout.php" class="btn btn-sm btn-outline-danger mt-2"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="main-content">
    <header class="main-header">
        <h1>Admin Dashboard</h1>
    </header>
    
    <div class="row">
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="stat-card bg-success">
                <i class="fas fa-user-check"></i>
                <div>Active Users<span><?php echo $active_user_count; ?></span></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="stat-card bg-danger">
                <i class="fas fa-user-slash"></i>
                <div>Inactive Users<span><?php echo $inactive_user_count; ?></span></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="stat-card bg-info">
                <i class="fas fa-store"></i>
                <div>Total Vendors<span><?php echo $vendor_count; ?></span></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="stat-card bg-secondary">
                <i class="fas fa-file-alt"></i>
                <div>Total Requests<span><?php echo $total_requests_count; ?></span></div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light">
                    <h4 class="mb-0"><i class="fas fa-users-cog text-primary"></i> Users by Role</h4>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <canvas id="usersChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light">
                    <h4 class="mb-0"><i class="fas fa-history text-info"></i> Recent Admin Activity</h4>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php if ($recent_activity->num_rows > 0): ?>
                            <?php while($activity = $recent_activity->fetch_assoc()): ?>
                                <?php
                                    $icon = 'fa-question-circle';
                                    $title = 'New Event';
                                    $link = '#';

                                    switch ($activity['type']) {
                                        case 'user_created':
                                            $icon = 'fa-user-plus text-primary';
                                            $title = "New User: <strong>" . e($activity['main_text']) . "</strong>";
                                            $sub_text = "Email: " . e($activity['sub_text']);
                                            $link = "manage_users.php";
                                            break;
                                        case 'vendor_created':
                                            $icon = 'fa-store text-success';
                                            $title = "New Vendor: <strong>" . e($activity['main_text']) . "</strong>";
                                            $sub_text = "Contact: " . e($activity['sub_text']);
                                            $link = "manage_vendors.php";
                                            break;
                                    }
                                ?>
                                <li class="list-group-item d-flex align-items-center">
                                    <i class="fas <?php echo $icon; ?> fa-fw me-3 fs-5"></i>
                                    <div class="flex-grow-1">
                                        <a href="<?php echo $link; ?>" class="text-decoration-none text-dark stretched-link"><?php echo $title; ?></a>
                                        <small class="d-block text-muted"><?php echo $sub_text; ?></small>
                                        <small class="text-muted ms-2 text-nowrap"><?php echo time_ago($activity['activity_date']); ?></small>

                                    </div>
                                </li>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <li class="list-group-item text-center p-4">No recent admin activity.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('usersChart').getContext('2d');
    const usersChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [{
                label: '# of Users',
                data: <?php echo json_encode($chart_values); ?>,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.7)',
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(255, 206, 86, 0.7)',
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(153, 102, 255, 0.7)',
                    'rgba(255, 159, 64, 0.7)'
                ],
                borderColor: '#fff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                }
            }
        }
    });
});
</script>
</body>
</html>