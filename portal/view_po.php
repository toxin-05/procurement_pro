<?php
$page_title = 'View Purchase Order';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Ensure the user is logged in
requireLogin();

// 1. VALIDATE INPUT: Check if ID is provided and is a valid number
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    // Redirect if the ID is missing or invalid
    header("Location: manage_pos.php");
    exit();
}
$po_id = intval($_GET['id']);

// 2. FETCH PURCHASE ORDER DETAILS: Use a prepared statement to prevent SQL injection
$sql = "
    SELECT 
        po.po_number, po.created_at as po_date,
        v.vendor_name, v.address as vendor_address, v.email as vendor_email, v.phone as vendor_phone,
        r.subject, r.id as request_id,
        principal.full_name as principal_name,
        principal.signature_path as principal_signature 
    FROM purchase_orders po
    JOIN vendors v ON po.vendor_id = v.id
    JOIN requests r ON po.request_id = r.id
    LEFT JOIN users principal ON r.principal_approver_id = principal.id
    WHERE po.id = ?
";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    // A prepared statement error is a critical server issue
    die("Error preparing the purchase order query: " . $conn->error);
}
$stmt->bind_param("i", $po_id);
$stmt->execute();
$result = $stmt->get_result();
$po_details = $result->fetch_assoc();

// Check if a purchase order was actually found
if (!$po_details) {
    die("Error: Purchase Order with ID {$po_id} could not be found.");
}

// 3. FETCH ASSOCIATED ITEMS: Use the request_id from the PO details
$request_id = $po_details['request_id'];
$items_sql = "SELECT item_name, quantity, estimated_price FROM request_items WHERE request_id = ?";
$items_stmt = $conn->prepare($items_sql);
if ($items_stmt === false) {
    die("Error preparing the items query: " . $conn->error);
}
$items_stmt->bind_param("i", $request_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result(); // Changed variable name to avoid confusion

// Initialize total cost
$total_cost = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/jpeg" href="../assets/favicon.jpeg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($page_title) . ' - ' . e($po_details['po_number']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background-color: #f0f2f5; }
        .po-container { max-width: 850px; margin: auto; }
        .po-document { background: #fff; border: 1px solid #dee2e6; padding: 2.5rem; }
        .po-header img { max-height: 70px; }
        .signature-block { margin-top: 60px; text-align: right; }
        .signature-block img { max-height: 50px; margin-bottom: -5px; }
        .table thead th { text-transform: uppercase; font-size: 0.85em; }

        @media print {
            body { background-color: #fff !important; }
            .no-print { display: none !important; }
            .po-document { border: none !important; box-shadow: none !important; }
        }
    </style>
</head>
<body>

<div class="container py-4 po-container">
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <a href="manage_pos.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Back to List</a>
        <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print me-2"></i>Print Purchase Order</button>
    </div>

    <div class="po-document shadow-sm">
        <header class="row align-items-center pb-3 mb-4 border-bottom po-header">
            <div class="col-md-6">
                <h1 class="h3 mb-0">Purchase Order</h1>
                <p class="text-muted mb-0">Kwekwe Polytechnic</p>
            </div>
            <div class="col-md-6 text-md-end">
                <h2 class="h4 mb-1"><?php echo e($po_details['po_number']); ?></h2>
                <p class="mb-0"><strong>Date:</strong> <?php echo date('F j, Y', strtotime($po_details['po_date'])); ?></p>
                <p class="text-muted mb-0 small">Ref Request ID: #<?php echo e($po_details['request_id']); ?></p>
            </div>
        </header>

        <!-- Vendor and Shipping Info -->
        <div class="row mb-4">
            <div class="col-sm-6">
                <h5 class="text-uppercase small">Vendor</h5>
                <address class="mb-0">
                    <strong><?php echo e($po_details['vendor_name']); ?></strong><br>
                    <?php echo nl2br(e($po_details['vendor_address'])); ?><br>
                    <i class="fas fa-phone-alt fa-fw me-1"></i> <?php echo e($po_details['vendor_phone']); ?><br>
                    <i class="fas fa-envelope fa-fw me-1"></i> <?php echo e($po_details['vendor_email']); ?>
                </address>
            </div>
            <div class="col-sm-6 text-sm-end">
                <h5 class="text-uppercase small">Ship To</h5>
                <address class="mb-0">
                    <strong>Kwekwe Polytechnic</strong><br>
                    P.O. Box 399, Kwekwe<br>
                    Midlands Province, Zimbabwe
                </address>
            </div>
        </div>

        <!-- Items Table -->
        <table class="table table-bordered">
            <thead class="table-light">
                <tr>
                    <th scope="col">Item Description</th>
                    <th scope="col" class="text-center">Quantity</th>
                    <th scope="col" class="text-end">Unit Price</th>
                    <th scope="col" class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($items_result->num_rows > 0): ?>
                    <?php while($item = $items_result->fetch_assoc()): ?>
                        <?php $line_total = $item['quantity'] * $item['estimated_price']; $total_cost += $line_total; ?>
                        <tr>
                            <td><?php echo e($item['item_name']); ?></td>
                            <td class="text-center"><?php echo e($item['quantity']); ?></td>
                            <td class="text-end">$<?php echo number_format($item['estimated_price'], 2); ?></td>
                            <td class="text-end">$<?php echo number_format($line_total, 2); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="text-center text-muted">No items were found for this request. (Debug: Searched for request_id: <?php echo e($request_id); ?>)</td></tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="2"></th>
                    <th class="text-end">Subtotal:</th>
                    <th class="text-end">$<?php echo number_format($total_cost, 2); ?></th>
                </tr>
                <tr>
                    <th colspan="2"></th>
                    <th class="text-end">Tax (0%):</th>
                    <th class="text-end">$0.00</th>
                </tr>
                <tr class="table-light fw-bold fs-5">
                    <th colspan="2"></th>
                    <th class="text-end">Grand Total:</th>
                    <th class="text-end">$<?php echo number_format($total_cost, 2); ?></th>
                </tr>
            </tfoot>
        </table>

        <!-- Notes and Signature -->
        <div class="row mt-4">
            <div class="col-md-6">
                <h6 class="text-uppercase small">Notes</h6>
                <p class="text-muted small">Please include the PO number on all invoices and correspondence. Payment terms are 30 days from date of invoice.</p>
            </div>
            <div class="col-md-6">
                <div class="signature-block">
                    <?php if (!empty($po_details['principal_signature'])): ?>
                        <img src="../<?php echo e($po_details['principal_signature']); ?>" alt="Signature">
                    <?php endif; ?>
                    <div class="border-top border-dark mt-2 pt-2">
                        <strong><?php echo e($po_details['principal_name']); ?></strong><br>
                        <small class="text-muted">Principal, Kwekwe Polytechnic</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>