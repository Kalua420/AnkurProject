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

// Get filter status if provided
$status = isset($_GET['status']) ? $_GET['status'] : 'all';

try {
    // Prepare SQL based on filter
    if ($status === 'all') {
        $sql = "SELECT id, username, email, role, status, created_at, updated_at FROM users ORDER BY id DESC";
        $stmt = $pdo->prepare($sql);
    } else {
        $sql = "SELECT id, username, email, role, status, created_at, updated_at FROM users WHERE status = :status ORDER BY id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':status', $status, PDO::PARAM_STR);
    }
    
    // Execute the query
    $stmt->execute();
    
    // Fetch all users
    $users = $stmt->fetchAll();
    
    // Count users by role and status
    $sqlCounts = "SELECT 
                    COUNT(*) as total_users,
                    SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) as student_count,
                    SUM(CASE WHEN role = 'teacher' THEN 1 ELSE 0 END) as teacher_count,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count
                  FROM users";
    
    $stmtCounts = $pdo->prepare($sqlCounts);
    $stmtCounts->execute();
    $counts = $stmtCounts->fetch();
    
    // Return data as JSON
    header('Content-Type: application/json');
    echo json_encode([
        'users' => $users,
        'stats' => $counts
    ]);
} catch(PDOException $e) {
    // Return error as JSON
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>