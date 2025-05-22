<?php
$host = "localhost";
$user = "root"; // your DB username
$password = ""; // your DB password
$dbname = "paper_archive";

// Create DB connection
$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $paper_code = $_POST['paper_code'];
    $subject = $_POST['subject'];
    $year = $_POST['year'];
    $file_path = $_POST['file_path']; // You can enhance this to handle real file uploads

    // Check for duplicate
    $stmt = $conn->prepare("SELECT * FROM question_papers WHERE paper_code = ? AND subject = ?");
    $stmt->bind_param("ss", $paper_code, $subject);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo "Error: A paper with this code and subject already exists.";
    } else {
        $stmt = $conn->prepare("INSERT INTO question_papers (paper_code, subject, year, file_path) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssis", $paper_code, $subject, $year, $file_path);
        
        if ($stmt->execute()) {
            echo "Question paper uploaded successfully.";
        } else {
            echo "Error: " . $stmt->error;
        }
    }

    $stmt->close();
}

$conn->close();
?>
