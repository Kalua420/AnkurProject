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
if (!isset($data['user_id']) || !isset($data['username']) || !isset($data['email']) || 
    !isset($data['role']) || !isset($data['status'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing required fields']);
    exit();
}

// Extract data
$userId = (int)$data['user_id'];
$username = trim($data['username']);
$email = trim($data['email']);
$password = isset($data['password']) ? $data['password'] : null;
$role = $data['role'];
$status = $data['status'];

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid email format']);
    exit();
}

// Validate role
if (!in_array($role, ['admin', 'teacher', 'student'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid role']);
    exit();
}

// Validate status
if (!in_array($status, ['pending', 'approved', 'rejected'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid status']);
    exit();
}

try {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = :user_id");
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'User not found']);
        exit();
    }
    
    // Check if username already exists for another user
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username AND id != :user_id");
    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Username already exists']);
        exit();
    }
    
    // Check if email already exists for another user
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :user_id");
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Email already exists']);
        exit();
    }
    
    // Prepare update SQL - depends on whether password is being updated
    if (!empty($password)) {
        // Hash new password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $sql = "UPDATE users SET 
                username = :username, 
                email = :email, 
                password = :password, 
                role = :role, 
                status = :status 
                WHERE id = :user_id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':password', $hashed_password, PDO::PARAM_STR);
    } else {
        $sql = "UPDATE users SET 
                username = :username, 
                email = :email, 
                role = :role, 
                status = :status 
                WHERE id = :user_id";
        
        $stmt = $pdo->prepare($sql);
    }
    
    // Bind parameters
    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->bindParam(':role', $role, PDO::PARAM_STR);
    $stmt->bindParam(':status', $status, PDO::PARAM_STR);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    
    // Execute update
    $stmt->execute();
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'User updated successfully'
    ]);
    
} catch(PDOException $e) {
    // Return error as JSON
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>