<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Function to check if a user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: ../login.php");
        exit();
    }
}

// Function to check if user has a specific role
function hasRole($role) {
    if (isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] == $role) {
        return true;
    }
    return false;
}

// Function to check if user is an admin or approver
function isApproverOrAdmin() {
    return hasRole('approver') || hasRole('admin');
}

// Sanitize output to prevent XSS
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// --- ADDED TO FIX THE ERROR ---
/**
 * Redirects the user to the appropriate dashboard based on their role.
 * @param string $role The user's role.
 */
function redirectUser($role) {
    switch ($role) {
        case 'admin':
            header('Location: portal/admin_dashboard.php');
            break;
        case 'requester':
            header('Location: portal/user_dashboard.php');
            break;
        case 'finance':
            header('Location: portal/finance_dashboard.php');
            break;
        case 'procurement':
            header('Location: portal/procurement_dashboard.php');
            break;
        case 'procurement_head':
            header('Location: portal/procurement_head_dashboard.php');
            break;
        case 'principal':
            header('Location: portal/principal_dashboard.php');
            break;
        default:
            // Fallback to login page if role is unknown
            header('Location: login.php');
            break;
    }
    exit();
}
