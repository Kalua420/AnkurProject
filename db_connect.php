<?php
$servername = "localhost"; // Change this if your database is hosted elsewhere
$username = "root"; // Change to your MySQL username
$password = ""; // Change to your MySQL password
$dbname = "paper_archive"; // Change to your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
