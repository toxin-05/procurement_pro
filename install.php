<?php
// --- Database Configuration ---
$dbHost = 'localhost';
$dbUsername = 'root';
$dbPassword = '';
$dbName = 'procurement_pro_db';

// --- Establish Connection ---
$conn = new mysqli($dbHost, $dbUsername, $dbPassword);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- Create Database ---
$sqlCreateDb = "CREATE DATABASE IF NOT EXISTS $dbName";
if ($conn->query($sqlCreateDb) === TRUE) {
    echo "Database created successfully or already exists.<br>";
} else {
    die("Error creating database: " . $conn->error . "<br>");
}
$conn->select_db($dbName);

// --- SQL to create tables ---
$sql = "
-- Users Table: Stores user accounts and their roles
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `department` VARCHAR(100) NOT NULL,
  `role` ENUM('requester', 'approver', 'admin') NOT NULL DEFAULT 'requester',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Requests Table: Stores the main details of each procurement request
CREATE TABLE `requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `status` ENUM('Pending Approval', 'Approved', 'Rejected', 'Processing', 'Completed') NOT NULL DEFAULT 'Pending Approval',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `approver_id` INT NULL,
  `rejection_reason` TEXT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Request Items Table: Stores individual items for each request
CREATE TABLE `request_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `request_id` INT NOT NULL,
  `item_name` VARCHAR(255) NOT NULL,
  `quantity` INT NOT NULL,
  `estimated_price` DECIMAL(10, 2) NOT NULL,
  FOREIGN KEY (`request_id`) REFERENCES `requests`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;
";

// --- Execute Multi Query ---
if ($conn->multi_query($sql)) {
    do {
        // Store first result set
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->next_result());
    echo "Tables created successfully.<br>";
} else {
    die("Error creating tables: " . $conn->error . "<br>");
}

// --- Create a Default Admin User ---
$admin_name = 'Admin User';
$admin_email = 'admin@procurement.com';
$admin_pass = 'Admin@123';
$admin_department = 'Administration';
$admin_role = 'admin';
$hashed_password = password_hash($admin_pass, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO users (full_name, email, password, department, role) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sssss", $admin_name, $admin_email, $hashed_password, $admin_department, $admin_role);

if ($stmt->execute()) {
    echo "Default admin user created successfully.<br>";
    echo "<strong>Username:</strong> admin@procurement.com<br>";
    echo "<strong>Password:</strong> Admin@123<br>";
    echo "<h3>IMPORTANT: Delete this install.php file now!</h3>";
} else {
    echo "Error creating admin user: " . $stmt->error . "<br>";
}

$stmt->close();
$conn->close();
?>