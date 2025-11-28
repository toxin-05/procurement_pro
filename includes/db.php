<?php
// Database configuration
$dbHost = 'localhost';
$dbUsername = 'root';
$dbPassword = ''; // Default XAMPP password is empty
$dbName = 'procurement_pro_db';

// Create database connection
$conn = new mysqli($dbHost, $dbUsername, $dbPassword, $dbName);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Start the session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>