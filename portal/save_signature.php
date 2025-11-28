<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();
if (!hasRole('procurement') && !hasRole('admin')) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied.');
}

if (isset($_POST['po_id']) && isset($_POST['signature'])) {
    $po_id = intval($_POST['po_id']);
    $signature_data = $_POST['signature'];

    // Decode the base64 signature image data
    list($type, $data) = explode(';', $signature_data);
    list(, $data)      = explode(',', $data);
    $data = base64_decode($data);

    // Create a unique filename
    $upload_dir = '../uploads/';
    $filename = 'signature_' . $po_id . '_' . time() . '.png';
    $filepath = $upload_dir . $filename;

    // Save the image to the server
    if (file_put_contents($filepath, $data)) {
        // Update the database with the path to the signature image
        $db_path = 'uploads/' . $filename;
        $stmt = $conn->prepare("UPDATE purchase_orders SET signature_path = ? WHERE id = ?");
        $stmt->bind_param("si", $db_path, $po_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'path' => '../' . $db_path]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update database.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save image file.']);
    }
} else {
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid request.');
}
?>