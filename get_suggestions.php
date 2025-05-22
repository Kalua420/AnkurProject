<?php
$conn = new mysqli('localhost', 'root', '', 'paper_archive'); // Update credentials

$allowedFields = ['subject', 'department', 'course_code', 'semester', 'year'];

if (isset($_POST['query'], $_POST['field']) && in_array($_POST['field'], $allowedFields)) {
    $query = $conn->real_escape_string($_POST['query']);
    $field = $_POST['field'];

    $sql = "SELECT DISTINCT $field FROM question_papers WHERE $field LIKE '%$query%' LIMIT 5";
    $result = $conn->query($sql);

    while ($row = $result->fetch_assoc()) {
        $value = htmlspecialchars($row[$field]);
        echo "<div onclick=\"document.getElementById('$field').value='$value'; document.getElementById('$field-suggestions').innerHTML = ''\">$value</div>";
    }
}
?>