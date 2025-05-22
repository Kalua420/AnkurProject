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
        
        // Adjust file path if needed - assuming file_path in DB already has the correct relative path
        // If the path in the database doesn't include the base directory, construct the full local path
        $base_path = __DIR__ . '/'; // Current directory where this script is located
        $full_path = $base_path . $file_path;
        
        // Check if file exists
        if(file_exists($full_path)) {
            // Update download count
            $update_sql = "UPDATE question_papers SET download_count = download_count + 1 WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $paper_id);
            $update_stmt->execute();
            
            // Set headers for file download
            $file_name = basename($file_path);
            $file_title = $paper['title'];
            
            // Clean the title for use in filename
            $clean_title = preg_replace('/[^A-Za-z0-9_\-]/', '_', $file_title);
            $download_name = $clean_title . "_" . $paper['semester'] . "_" . $paper['subject'] . ".pdf";
            
            // Set appropriate headers for PDF file
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $download_name . '"');
            header('Content-Length: ' . filesize($full_path));
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: public');
            
            // Clear output buffer
            ob_clean();
            flush();
            
            // Output file
            readfile($full_path);
            exit();
        } else {
            // If file not found with the adjusted path, let's try a different approach
            // Try to access the file directly from the URL provided in the DB
            $file_url = "http://localhost/ankur/teacher/" . $file_path;
            
            // Redirect to this URL
            header("Location: $file_url");
            exit();
        }
    } else {
        // Paper not found in database
        echo "Error: Question paper not found.";
    }
} else {
    // No ID provided
    echo "Error: Invalid request.";
}

// Close connection
$conn->close();
?>