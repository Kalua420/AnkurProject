<?php
// Start session
session_start();

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Database connection
$servername = "localhost";
$username = "root"; // Change as per your configuration
$password = ""; // Change as per your configuration
$dbname = "paper_archive"; // Change to your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if paper ID is provided
if(isset($_GET['id'])) {
    $paper_id = $_GET['id'];
    
    // Get paper details from database
    $sql = "SELECT * FROM question_papers WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $paper_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0) {
        $paper = $result->fetch_assoc();
        $file_path = $paper['file_path'];
        
        // Construct full file path
        $base_path = __DIR__ . '/';
        $full_path = $base_path . $file_path;
        
        // Delete the file if it exists
        if(file_exists($full_path)) {
            if(unlink($full_path)) {
                // File deleted successfully, now remove from database
                $delete_sql = "DELETE FROM question_papers WHERE id = ?";
                $delete_stmt = $conn->prepare($delete_sql);
                $delete_stmt->bind_param("i", $paper_id);
                
                if($delete_stmt->execute()) {
                    // Success message and redirect
                    $_SESSION['delete_success'] = "Question paper deleted successfully.";
                } else {
                    // Database error
                    $_SESSION['delete_error'] = "Error deleting record from database: " . $conn->error;
                }
            } else {
                // Error deleting file
                $_SESSION['delete_error'] = "Error deleting file. Please check file permissions.";
            }
        } else {
            // File doesn't exist, just delete the database record
            $delete_sql = "DELETE FROM question_papers WHERE id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("i", $paper_id);
            
            if($delete_stmt->execute()) {
                $_SESSION['delete_success'] = "Question paper record deleted successfully. File was not found on server.";
            } else {
                $_SESSION['delete_error'] = "Error deleting record from database: " . $conn->error;
            }
        }
    } else {
        // Paper not found in database
        $_SESSION['delete_error'] = "Error: Question paper not found.";
    }
} else {
    // No ID provided
    $_SESSION['delete_error'] = "Error: Invalid request.";
}

// Redirect back to the main page
header("Location: dashboard.php");
exit();

// Close connection
$conn->close();
?>