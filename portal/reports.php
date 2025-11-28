<?php
$page_title = 'Reports & Analytics';
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();
if (!hasRole('admin')) {
    header("Location: user_dashboard.php");
    exit();
}

$report_data = [];
$report_title = '';
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : '';
$summary_stats = [];

// --- Report Generation Logic ---
if (!empty($report_type)) {
    switch ($report_type) {
        
        case 'requests_by_status':
            $report_title = 'Requests by Status';
            $sql = "
                SELECT 
                    r.status, 
                    COUNT(r.id) as request_count, 
                    COALESCE(SUM(ri.quantity * ri.estimated_price), 0) as total_value
                FROM requests r
                LEFT JOIN request_items ri ON r.id = ri.request_id
                GROUP BY r.status
                ORDER BY FIELD(r.status, 'Pending Approval', 'Finance Approved', 'pproved', 'Principal Approved', 'Processing', 'Completed', 'Rejected')
            ";
            $report_data = $conn->query($sql);

            // Calculate summary stats for this report
            $total_requests = 0;
            $total_value = 0;
            if ($report_data && $report_data->num_rows > 0) {
                foreach ($report_data as $row) {
                    $total_requests += $row['request_count'];
                    $total_value += $row['total_value'];
                }
                $report_data->data_seek(0); // Rewind data pointer for table loop
            }
            $summary_stats = [
                ['label' => 'Total Requests', 'value' => $total_requests, 'icon' => 'fa-file-alt', 'color' => 'bg-primary'],
                ['label' => 'Total Est. Value', 'value' => '$' . number_format($total_value, 2), 'icon' => 'fa-dollar-sign', 'color' => 'bg-success']
            ];
            break;

        case 'spending_by_vendor':
            $report_title = 'Actual Spending by Vendor';
            $sql = "
                SELECT 
                    v.vendor_name, 
                    COUNT(i.id) as invoice_count, 
                    COALESCE(SUM(i.invoice_amount), 0) as total_spent
                FROM vendors v
                LEFT JOIN purchase_orders po ON v.id = po.vendor_id
                LEFT JOIN invoices i ON po.id = i.po_id AND i.status = 'Paid'
                GROUP BY v.id, v.vendor_name
                HAVING total_spent > 0
                ORDER BY total_spent DESC
            ";
            $report_data = $conn->query($sql);
            
            $total_invoices = 0;
            $total_spent = 0;
            if ($report_data && $report_data->num_rows > 0) {
                foreach ($report_data as $row) {
                    $total_invoices += $row['invoice_count'];
                    $total_spent += $row['total_spent'];
                }
                $report_data->data_seek(0);
            }
             $summary_stats = [
                ['label' => 'Total Paid Invoices', 'value' => $total_invoices, 'icon' => 'fa-receipt', 'color' => 'bg-info'],
                ['label' => 'Total Confirmed Spend', 'value' => '$' . number_format($total_spent, 2), 'icon' => 'fa-check-circle', 'color' => 'bg-success']
            ];
            break;
    }
}

function getStatusBadgeClass($status) {
    switch ($status) {
        case 'Pending Approval': return 'bg-warning text-dark';
        case 'Finance Approved':
        case 'pproved': return 'bg-info text-dark';
        case 'Principal Approved': return 'bg-success';
        case 'Processing': return 'bg-primary';
        case 'Completed': return 'bg-secondary';
        case 'Rejected': return 'bg-danger';
        default: return 'bg-light text-dark';
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
    
    <style>
        @media print {
            @page { size: A4; margin: 20mm; }
            body { background-color: #fff !important; font-size: 12pt; }
            .sidebar, .main-header, .report-form-card, .no-print { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 0 !important; }
            .card { box-shadow: none !important; border: 1px solid #dee2e6 !important; }
            * { color: #000 !important; background: transparent !important; }
            .print-header { display: block !important; margin-bottom: 2rem; text-align: center; }
            .table { width: 100%; }
        }
        .print-header { display: none; }
    </style>
</head>
<body>

<div class="sidebar">
<div class="sidebar-header">
    <img src="../assets/favicon.jpeg" alt="Kwekwe Polytechnic Logo" class="sidebar-logo">
</div>
    <nav class="sidebar-nav">
        <a href="admin_dashboard.php"><i class="icon fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="manage_users.php"><i class="icon fas fa-users"></i> Manage Users</a>
        <a href="manage_vendors.php"><i class="icon fas fa-store"></i> Manage Vendors</a>
        <a href="reports.php" class="active"><i class="icon fas fa-chart-pie"></i> Reports</a>
    </nav>
    <div class="sidebar-footer">
        <a href="../logout.php" class="btn btn-sm btn-outline-danger mt-2"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="main-content">
    <header class="main-header"><h1><?php echo e($page_title); ?></h1></header>

    <div class="card shadow-sm mb-4 report-form-card">
        <div class="card-header bg-light">
            <h4 class="mb-0"><i class="fas fa-search text-primary"></i> Generate a Report</h4>
        </div>
        <div class="card-body">
            <p>Select a report to view from the options below.</p>
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label for="report_type" class="form-label">Report Type</label>
                    <select name="report_type" id="report_type" class="form-select" required>
                        <option value="" disabled <?php if(empty($report_type)) echo 'selected'; ?>>-- Choose a report --</option>
                        <option value="requests_by_status" <?php if($report_type == 'requests_by_status') echo 'selected'; ?>>Requests by Status</option>
                        <option value="spending_by_vendor" <?php if($report_type == 'spending_by_vendor') echo 'selected'; ?>>Actual Spending by Vendor</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-cogs me-2"></i>Generate Report</button>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($report_type) && $report_data): ?>
    <div class="print-header">
        <h2>ProcurementPRO Inc.</h2>
        <h4><?php echo e($report_title); ?></h4>
        <p>Generated on: <?php echo date('F j, Y'); ?></p>
    </div>
    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-chart-bar text-success"></i> Report: <?php echo e($report_title); ?></h4>
            <button class="btn btn-sm btn-outline-secondary no-print" onclick="window.print();"><i class="fas fa-print me-2"></i>Print Report</button>
        </div>
        <div class="card-body">
            
            <?php if (!empty($summary_stats)): ?>
            <div class="row mb-4">
                <?php foreach($summary_stats as $stat): ?>
                <div class="col-md-6 mb-3">
                    <div class="stat-card <?php echo $stat['color']; ?>">
                        <i class="fas <?php echo $stat['icon']; ?>"></i>
                        <div><?php echo $stat['label']; ?><span><?php echo $stat['value']; ?></span></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="table-responsive">
                
                <?php if ($report_type == 'requests_by_status'): ?>
                <table class="table table-hover table-striped">
                    <thead class="table-light"><tr><th>Status</th><th class="text-center"># of Requests</th><th class="text-end">Total Est. Value</th></tr></thead>
                    <tbody>
                        <?php while($row = $report_data->fetch_assoc()): ?>
                        <tr>
                            <td><span class="badge <?php echo getStatusBadgeClass($row['status']); ?>"><?php echo e($row['status']); ?></span></td>
                            <td class="text-center"><?php echo $row['request_count']; ?></td>
                            <td class="text-end">$<?php echo number_format($row['total_value'], 2); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
                <?php elseif ($report_type == 'spending_by_vendor'): ?>
                <table class="table table-hover table-striped">
                    <thead class="table-light"><tr><th>Vendor Name</th><th class="text-center"># of Paid Invoices</th><th class="text-end">Total Confirmed Spend</th></tr></thead>
                    <tbody>
                        <?php while($row = $report_data->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo e($row['vendor_name']); ?></strong></td>
                            <td class="text-center"><?php echo $row['invoice_count']; ?></td>
                            <td class="text-end">$<?php echo number_format($row['total_spent'], 2); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php endif; ?>

            </div>
        </div>
    </div>
    <?php elseif (!empty($report_type)): ?>
        <div class="alert alert-warning">No data found for this report.</div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>