<?php
// Start session
session_start();

// Include database connection
include('../db_connect.php');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // Return error as JSON
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid request method']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($data['user_id']) || !isset($data['status'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing required fields']);
    exit();
}

// Extract data
$userId = (int)$data['user_id'];
$status = $data['status'];
$reason = isset($data['reason']) ? $data['reason'] : null;

// Validate status
if (!in_array($status, ['pending', 'approved', 'rejected'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid status']);
    exit();
}

try {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE id = :user_id");
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    $user = $stmt->fetch();
    
    if (!$user) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'User not found']);
        exit();
    }
    
    // Update user status
    $stmt = $pdo->prepare("UPDATE users SET status = :status WHERE id = :user_id");
    $stmt->bindParam(':status', $status, PDO::PARAM_STR);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    // If there's a rejection reason, store it in a separate table or log it
    if ($status === 'rejected' && !empty($reason)) {
        // This might require an additional table in your database
        // For now, we'll just log it
        error_log("User {$user['username']} (ID: {$userId}) rejected. Reason: {$reason}");
    }
    
    // If status is approved, you might want to send an email notification
    if ($status === 'approved') {
        // Placeholder for email notification
        // mail($user['email'], 'Account Approved', 'Your account has been approved.');
    }
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'User status updated successfully',
        'username' => $user['username'],
        'status' => $status
    ]);
    
} catch(PDOException $e) {
    // Return error as JSON
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>