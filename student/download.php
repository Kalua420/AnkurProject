<?php
session_start();
include('../db_connect.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Check if ID and type are set
if (!isset($_GET['id']) || !isset($_GET['type'])) {
    $_SESSION['error'] = "Invalid request. Missing parameters.";
    header("Location: ../index.php");
    exit();
}

$id = mysqli_real_escape_string($conn, $_GET['id']);
$type = mysqli_real_escape_string($conn, $_GET['type']);

// Validate file type
$allowed_types = ['question_papers', 'assignments', 'papers'];
if (!in_array($type, $allowed_types)) {
    $_SESSION['error'] = "Invalid document type requested.";
    header("Location: ../index.php");
    exit();
}

// Get file path from database based on type
$sql = "SELECT * FROM question_papers WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $paper_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0) 
        $paper = $result->fetch_assoc();
        $file_path = $paper['file_path'];
        
        // Construct full file path
        $base_path = __DIR__ . '/';
        $full_path = $base_path . $file_path;

// Update download count (if available in that table)
if ($type == 'question_papers') {
    $update_sql = "UPDATE question_papers SET download_count = download_count + 1 WHERE id = ?";
    $update_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($update_stmt, "i", $id);
    mysqli_stmt_execute($update_stmt);
}

// Check if file exists
if (!file_exists($file_path)) {
    // Try with a relative path - this often fixes the issue
    $relative_path = str_replace($_SERVER['DOCUMENT_ROOT'], '', $file_path);
    $server_path = $_SERVER['DOCUMENT_ROOT'] . $relative_path;
    
    if (!file_exists($server_path)) {
        // Try looking for the file in the uploads directory
        $base_filename = basename($file_path);
        $search_path = "../uploads/question_papers/" . $base_filename;
        
        if (!file_exists($search_path)) {
            $_SESSION['error'] = "File not found. Please contact administrator.";
            header("Location: ../index.php");
            exit();
        } else {
            $file_path = $search_path;
        }
    } else {
        $file_path = $server_path;
    }
}

// Get file information
$file_info = pathinfo($file_path);
$file_name = $file_info['basename'];
$file_ext = strtolower($file_info['extension']);

// Set appropriate content type
$content_type = 'application/octet-stream';

// Set headers for download
header('Content-Description: File Transfer');
header('Content-Type: ' . $content_type);
header('Content-Disposition: attachment; filename="' . $file_name . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($file_path));

// Output file content
readfile($file_path);
exit();
?>