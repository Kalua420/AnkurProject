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
if (!isset($data['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing user_id']);
    exit();
}

// Extract user ID
$userId = (int)$data['user_id'];

// Prevent admin from deleting themselves
if ($userId === (int)$_SESSION['user_id']) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'You cannot delete your own account']);
    exit();
}

try {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE id = :user_id");
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    $user = $stmt->fetch();
    
    if (!$user) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'User not found']);
        exit();
    }
    
    // Delete user
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = :user_id");
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'User deleted successfully',
        'username' => $user['username']
    ]);
    
} catch(PDOException $e) {
    // Return error as JSON
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>