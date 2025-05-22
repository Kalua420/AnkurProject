<?php
if (isset($_GET['q'])) {
    $query = urlencode($_GET['q']);
    $url = "http://localhost:8000/search?q=$query";
    $response = file_get_contents($url);
    $data = json_decode($response, true);

    echo "<h3>Search Results:</h3>";
    foreach ($data['results'] as $paper) {
        echo "<div style='border:1px solid #ccc; padding:10px; margin-bottom:10px'>";
        echo "<strong>Title:</strong> " . htmlspecialchars($paper['title']) . "<br>";
        echo "<strong>Subject:</strong> " . htmlspecialchars($paper['subject']) . "<br>";
        echo "<strong>Year:</strong> " . htmlspecialchars($paper['year']);
        echo "</div>";
    }
} else {
?>
<form method="get">
    <input type="text" name="q" placeholder="Search..." />
    <button type="submit">Search</button>
</form>
<?php
}
?>
