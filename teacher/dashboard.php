<?php
// Start session
session_start();

// Check if user is logged in, redirect if not
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

// Handle file upload
if(isset($_POST['upload'])) {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    $course_code = mysqli_real_escape_string($conn, $_POST['course_code']);
    $paper_year = mysqli_real_escape_string($conn, $_POST['paper_year']);
    $semester = mysqli_real_escape_string($conn, $_POST['semester']);
    $subject = mysqli_real_escape_string($conn, $_POST['subject']);
    $credit = mysqli_real_escape_string($conn, $_POST['credit']);
    
    // Check if same title, semester and subject combination already exists
    $check_duplicate = "SELECT * FROM question_papers WHERE title = '$title' AND semester = '$semester' AND subject = '$subject' AND department = '$department' AND course_code = '$course_code' AND paper_year = '$paper_year'";
    $duplicate_result = $conn->query($check_duplicate);
    
    if($duplicate_result->num_rows > 0) {
        $upload_error = "A question paper with the same details already exists.";
    } else {
        // File upload handling
        $target_dir = "../uploads/question_papers/";
        
        // Create directory if it doesn't exist
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_name = basename($_FILES["question_paper"]["name"]);
        $target_file = $target_dir . time() . "_" . $file_name;
        $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        
        // Check if file is a PDF
        if($file_type != "pdf") {
            $upload_error = "Sorry, only PDF files are allowed.";
        } else {
            // Upload file
            if (move_uploaded_file($_FILES["question_paper"]["tmp_name"], $target_file)) {
                // Insert into database
                $sql = "INSERT INTO question_papers (title, department, course_code, paper_year, semester, subject, credit, file_path) 
                        VALUES ('$title', '$department', '$course_code', '$paper_year', '$semester', '$subject', '$credit', '$target_file')";
                
                if ($conn->query($sql) === TRUE) {
                    $upload_success = "Question paper has been uploaded successfully.";
                } else {
                    $upload_error = "Error: " . $sql . "<br>" . $conn->error;
                }
            } else {
                $upload_error = "Sorry, there was an error uploading your file.";
            }
        }
    }
}

// Handle delete via AJAX
if(isset($_POST['delete_paper']) && isset($_POST['paper_id'])) {
    $paper_id = mysqli_real_escape_string($conn, $_POST['paper_id']);
    
    // Get file path before deleting record
    $file_query = "SELECT file_path FROM question_papers WHERE id = '$paper_id'";
    $file_result = $conn->query($file_query);
    
    if ($file_result->num_rows > 0) {
        $file_row = $file_result->fetch_assoc();
        $file_path = $file_row['file_path'];
        
        // Delete from database
        $delete_query = "DELETE FROM question_papers WHERE id = '$paper_id'";
        if ($conn->query($delete_query) === TRUE) {
            // Delete file from server if it exists
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            echo json_encode(['status' => 'success', 'message' => 'Question paper deleted successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete question paper']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Question paper not found']);
    }
    exit; // Stop further execution after AJAX response
}

// Get available departments for autocomplete
$departments_query = "SELECT DISTINCT department FROM question_papers ORDER BY department";
$departments_result = $conn->query($departments_query);
$departments = [];
while($departments_result && $dept_row = $departments_result->fetch_assoc()) {
    $departments[] = $dept_row['department'];
}

// Get available subjects for autocomplete
$subjects_query = "SELECT DISTINCT subject FROM question_papers ORDER BY subject";
$subjects_result = $conn->query($subjects_query);
$subjects = [];
while($subjects_result && $subj_row = $subjects_result->fetch_assoc()) {
    $subjects[] = $subj_row['subject'];
}

// Get list of filters
$semester_query = "SELECT DISTINCT semester FROM question_papers ORDER BY semester";
$semester_result = $conn->query($semester_query);

$subject_query = "SELECT DISTINCT subject FROM question_papers ORDER BY subject";
$subject_result = $conn->query($subject_query);

$department_query = "SELECT DISTINCT department FROM question_papers ORDER BY department";
$department_result = $conn->query($department_query);

$year_query = "SELECT DISTINCT paper_year FROM question_papers ORDER BY paper_year DESC";
$year_result = $conn->query($year_query);

// Handle filters
$filter_semester = isset($_GET['filter_semester']) ? $_GET['filter_semester'] : '';
$filter_subject = isset($_GET['filter_subject']) ? $_GET['filter_subject'] : '';
$filter_department = isset($_GET['filter_department']) ? $_GET['filter_department'] : '';
$filter_year = isset($_GET['filter_year']) ? $_GET['filter_year'] : '';
$search_query = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Number of records per page
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Query to get question papers with filters
$query = "SELECT * FROM question_papers WHERE 1=1";

if (!empty($search_query)) {
    $query .= " AND (title LIKE '%$search_query%' OR department LIKE '%$search_query%' OR subject LIKE '%$search_query%' OR course_code LIKE '%$search_query%')";
}

if (!empty($filter_semester)) {
    $query .= " AND semester = '$filter_semester'";
}

if (!empty($filter_subject)) {
    $query .= " AND subject = '$filter_subject'";
}

if (!empty($filter_department)) {
    $query .= " AND department = '$filter_department'";
}

if (!empty($filter_year)) {
    $query .= " AND paper_year = '$filter_year'";
}

// Count total filtered records for pagination
$count_query = str_replace("SELECT *", "SELECT COUNT(*) as total", $query);
$count_result = $conn->query($count_query);
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Add pagination to main query
$query .= " ORDER BY uploaded_at DESC LIMIT $offset, $records_per_page";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - Question Papers</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.2.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/base/jquery-ui.min.css">
    <style>
        :root {
            --primary-color: #3a6ea5;
            --secondary-color: #f8f9fa;
            --accent-color: #28a745;
            --text-color: #343a40;
            --light-text: #6c757d;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-color);
            background-color: #f5f7fa;
        }
        
        .navbar {
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .dashboard-container {
            padding: 30px 15px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .card {
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
            border: none;
        }
        
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            font-weight: 600;
            padding: 15px 20px;
        }
        
        .collapsible-section {
            transition: all 0.3s ease;
        }
        
        .filter-section {
            margin-bottom: 20px;
        }
        
        .table-responsive {
            margin-top: 20px;
        }
        
        .upload-section {
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .no-papers {
            padding: 30px;
            text-align: center;
            background-color: #fff;
            border-radius: 8px;
        }
        
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover, .btn-primary:focus {
            background-color: #305a89;
            border-color: #305a89;
        }
        
        .table th {
            font-weight: 600;
            background-color: #f8f9fa;
        }
        
        .pagination {
            justify-content: center;
            margin-top: 20px;
        }
        
        .badge {
            font-weight: 500;
            padding: 0.4em 0.7em;
        }
        
        .search-bar {
            position: relative;
            margin-bottom: 20px;
        }
        
        .search-bar .form-control {
            padding-left: 40px;
            border-radius: 20px;
        }
        
        .search-bar .search-icon {
            position: absolute;
            left: 15px;
            top: 10px;
            color: #adb5bd;
        }
        
        .paper-card {
            transition: transform 0.2s;
        }
        
        .paper-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(58, 110, 165, 0.25);
        }
        
        .statistics-section {
            margin-bottom: 30px;
        }
        
        .stat-card {
            text-align: center;
            padding: 20px;
            border-radius: 8px;
        }
        
        .stat-card .icon {
            font-size: 2rem;
            margin-bottom: 10px;
            color: var(--primary-color);
        }
        
        .stat-card .stat-value {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .stat-card .stat-label {
            color: var(--light-text);
            font-size: 0.9rem;
        }
        
        /* Mobile responsive adjustments */
        @media (max-width: 768px) {
            .stat-card {
                margin-bottom: 15px;
            }
            
            .dashboard-container {
                padding: 15px 10px;
            }
            
            .header-section {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .table-responsive .btn {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }
        }
        
        /* Loading spinner */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            visibility: hidden;
            opacity: 0;
            transition: visibility 0s, opacity 0.3s;
        }
        
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Toast notifications */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1100;
        }
        
        .custom-toast {
            min-width: 250px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            border-left: 4px solid #3a6ea5;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .custom-toast.show {
            opacity: 1;
        }
        
        /* Animation for new items */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .new-item {
            animation: fadeIn 0.5s ease-out forwards;
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>
    
    <!-- Toast Container -->
    <div class="toast-container"></div>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-file-alt me-2"></i>Question Paper Archive
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="#">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#helpModal">
                            <i class="fas fa-question-circle me-1"></i>Help
                        </a>
                    </li>
                </ul>
                <div class="d-flex">
                    <span class="navbar-text me-3">
                        <i class="fas fa-user-circle me-1"></i>
                        Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Teacher'); ?>
                    </span>
                    <a href="../logout.php" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-sign-out-alt me-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container dashboard-container">
        <div class="header-section mb-4">
            <h1><i class="fas fa-book me-2"></i>Question Papers Management</h1>
        </div>
        
        <!-- Statistics Section -->
        <?php
        // Get statistics
        $total_papers_query = "SELECT COUNT(*) as total FROM question_papers";
        $total_papers_result = $conn->query($total_papers_query);
        $total_papers = $total_papers_result->fetch_assoc()['total'];
        
        $total_downloads_query = "SELECT SUM(download_count) as total FROM question_papers";
        $total_downloads_result = $conn->query($total_downloads_query);
        $total_downloads = $total_downloads_result->fetch_assoc()['total'] ?? 0;
        
        $recent_uploads_query = "SELECT COUNT(*) as total FROM question_papers WHERE uploaded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $recent_uploads_result = $conn->query($recent_uploads_query);
        $recent_uploads = $recent_uploads_result->fetch_assoc()['total'];
        
        $popular_paper_query = "SELECT title, download_count FROM question_papers ORDER BY download_count DESC LIMIT 1";
        $popular_paper_result = $conn->query($popular_paper_query);
        $popular_paper = $popular_paper_result->fetch_assoc();
        ?>
        
        <div class="statistics-section">
            <div class="row">
                <div class="col-md-3 col-sm-6">
                    <div class="card stat-card">
                        <div class="icon">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <div class="stat-value"><?php echo $total_papers; ?></div>
                        <div class="stat-label">Total Papers</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card stat-card">
                        <div class="icon">
                            <i class="fas fa-download"></i>
                        </div>
                        <div class="stat-value"><?php echo $total_downloads; ?></div>
                        <div class="stat-label">Total Downloads</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card stat-card">
                        <div class="icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="stat-value"><?php echo $recent_uploads; ?></div>
                        <div class="stat-label">Recent Uploads (30 days)</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card stat-card">
                        <div class="icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="stat-value"><?php echo $popular_paper ? $popular_paper['download_count'] : 0; ?></div>
                        <div class="stat-label">Most Popular Paper</div>
                        <small class="text-muted"><?php echo $popular_paper ? (strlen($popular_paper['title']) > 20 ? substr($popular_paper['title'], 0, 20).'...' : $popular_paper['title']) : 'None'; ?></small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Upload Section -->
        <div class="card upload-section mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="m-0">
                    <i class="fas fa-upload me-2"></i>Upload New Question Paper
                </h3>
                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#uploadCollapse" aria-expanded="true" aria-controls="uploadCollapse">
                    <i class="fas fa-chevron-down" id="uploadCollapseIcon"></i>
                </button>
            </div>
            
            <div class="collapse show" id="uploadCollapse">
                <div class="card-body">
                    <?php if(isset($upload_success)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i><?php echo $upload_success; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if(isset($upload_error)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo $upload_error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" enctype="multipart/form-data" id="uploadForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="title" class="form-label">Paper Title</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="department" class="form-label">Department</label>
                                <input type="text" class="form-control" id="department" name="department" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="course_code" class="form-label">Course Code</label>
                                <input type="text" class="form-control" id="course_code" name="course_code" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="subject" class="form-label">Subject</label>
                                <input type="text" class="form-control" id="subject" name="subject" required>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label for="paper_year" class="form-label">Year</label>
                                <input type="number" class="form-control" id="paper_year" name="paper_year" min="2000" max="<?php echo date('Y'); ?>" value="<?php echo date('Y'); ?>" required>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label for="semester" class="form-label">Semester</label>
                                <select class="form-select" id="semester" name="semester" required>
                                    <option value="">Select</option>
                                    <option value="Semester 1">Semester 1</option>
                                    <option value="Semester 2">Semester 2</option>
                                    <option value="Semester 3">Semester 3</option>
                                    <option value="Semester 4">Semester 4</option>
                                    <option value="Semester 5">Semester 5</option>
                                    <option value="Semester 6">Semester 6</option>
                                    <option value="Semester 7">Semester 7</option>
                                    <option value="Semester 8">Semester 8</option>
                                </select>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label for="credit" class="form-label">Credits</label>
                                <input type="number" class="form-control" id="credit" name="credit" min="1" max="10" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="question_paper" class="form-label">Question Paper (PDF only)</label>
                            <input type="file" class="form-control" id="question_paper" name="question_paper" accept=".pdf" required>
                            <div class="form-text">Maximum file size: 10MB</div>
                        </div>
                        <button type="submit" name="upload" class="btn btn-primary">
                            <i class="fas fa-upload me-1"></i>Upload Question Paper
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Filter Section -->
        <div class="card filter-section mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="m-0">
                    <i class="fas fa-search me-2"></i>Find Question Papers
                </h3>
                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="true" aria-controls="filterCollapse">
                    <i class="fas fa-chevron-down" id="filterCollapseIcon"></i>
                </button>
            </div>
            
            <div class="collapse show" id="filterCollapse">
                <div class="card-body">
                    <div class="search-bar">
                        <form method="get" id="searchForm">
                            <div class="input-group mb-3">
                                <span class="search-icon">
                                    <i class="fas fa-search"></i>
                                </span>
                                <input type="text" class="form-control" placeholder="Search by title, department, subject or course code" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                                <button class="btn btn-outline-secondary" type="submit">Search</button>
                            </div>
                            
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Department</label>
                                    <select class="form-select filter-select" name="filter_department">
                                        <option value="">All Departments</option>
                                        <?php while($department_result && $department_row = $department_result->fetch_assoc()): ?>
                                            <option value="<?php echo $department_row['department']; ?>" <?php echo ($filter_department == $department_row['department']) ? 'selected' : ''; ?>>
                                                <?php echo $department_row['department']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Year</label>
                                    <select class="form-select filter-select" name="filter_year">
                                        <option value="">All Years</option>
                                        <?php while($year_result && $year_row = $year_result->fetch_assoc()): ?>
                                            <option value="<?php echo $year_row['paper_year']; ?>" <?php echo ($filter_year == $year_row['paper_year']) ? 'selected' : ''; ?>>
                                                <?php echo $year_row['paper_year']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Semester</label>
                                    <select class="form-select filter-select" name="filter_semester">
                                        <option value="">All Semesters</option>
                                        <?php while($semester_result && $semester_row = $semester_result->fetch_assoc()): ?>
                                            <option value="<?php echo $semester_row['semester']; ?>" <?php echo ($filter_semester == $semester_row['semester']) ? 'selected' : ''; ?>>
                                                <?php echo $semester_row['semester']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Subject</label>
                                    <select class="form-select filter-select" name="filter_subject">
                                        <option value="">All Subjects</option>
                                        <?php while($subject_result && $subject_row = $subject_result->fetch_assoc()): ?>
                                            <option value="<?php echo $subject_row['subject']; ?>" <?php echo ($filter_subject == $subject_row['subject']) ? 'selected' : ''; ?>>
                                                <?php echo $subject_row['subject']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="fas fa-filter me-1"></i>Apply Filters
                                    </button>
                                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary">
                                        <i class="fas fa-redo me-1"></i>Reset Filters
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Question Papers Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="m-0">
                    <i class="fas fa-list me-2"></i>Available Question Papers
                    <span class="badge bg-primary ms-2"><?php echo $total_records; ?> Results</span>
                </h3>
            </div>
            <div class="card-body">
                <?php if($result && $result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="papersTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Title</th>
                                    <th>Details</th>
                                    <th>Subject & Code</th>
                                    <th>Upload Date</th>
                                    <th class="text-center">Downloads</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $counter = $offset + 1;
                                while($row = $result->fetch_assoc()): 
                                ?>
                                    <tr class="paper-item" data-paper-id="<?php echo $row['id']; ?>">
                                        <td><?php echo $counter++; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['title']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="d-block">
                                                <i class="fas fa-university text-muted me-1"></i> 
                                                <?php echo htmlspecialchars($row['department']); ?>
                                            </span>
                                            <span class="d-block">
                                                <i class="fas fa-calendar text-muted me-1"></i> 
                                                <?php echo htmlspecialchars($row['paper_year']); ?>
                                            </span>
                                            <span class="badge bg-info">
                                                <?php echo htmlspecialchars($row['semester']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="d-block">
                                                <i class="fas fa-book text-muted me-1"></i> 
                                                <?php echo htmlspecialchars($row['subject']); ?>
                                            </span>
                                            <span class="d-block">
                                                <i class="fas fa-hashtag text-muted me-1"></i> 
                                                <?php echo htmlspecialchars($row['course_code']); ?>
                                            </span>
                                            <span class="badge bg-secondary">
                                                <?php echo htmlspecialchars($row['credit']); ?> Credits
                                            </span>
                                        </td>
                                        <td>
                                            <i class="fas fa-clock text-muted me-1"></i>
                                            <?php echo date('M d, Y', strtotime($row['uploaded_at'])); ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-success fs-6">
                                                <i class="fas fa-download me-1"></i>
                                                <?php echo $row['download_count']; ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <a href="../download_paper.php?id=<?php echo $row['id']; ?>" class="btn btn-outline-primary btn-sm view-btn" target="_blank">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                <button type="button" class="btn btn-outline-danger btn-sm delete-btn" data-paper-id="<?php echo $row['id']; ?>">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if($total_pages > 1): ?>
                    <nav aria-label="Question papers pagination" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page-1; ?>&filter_semester=<?php echo $filter_semester; ?>&filter_subject=<?php echo $filter_subject; ?>&filter_department=<?php echo $filter_department; ?>&filter_year=<?php echo $filter_year; ?>&search=<?php echo $search_query; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $start_page + 4);
                            $start_page = max(1, $end_page - 4);
                            
                            for($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&filter_semester=<?php echo $filter_semester; ?>&filter_subject=<?php echo $filter_subject; ?>&filter_department=<?php echo $filter_department; ?>&filter_year=<?php echo $filter_year; ?>&search=<?php echo $search_query; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page+1; ?>&filter_semester=<?php echo $filter_semester; ?>&filter_subject=<?php echo $filter_subject; ?>&filter_department=<?php echo $filter_department; ?>&filter_year=<?php echo $filter_year; ?>&search=<?php echo $search_query; ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="no-papers">
                        <div class="text-center my-5">
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <h4>No question papers found.</h4>
                            <p class="text-muted">Try changing your filter settings or upload a new question paper.</p>
                            
                            <?php if(!empty($filter_semester) || !empty($filter_subject) || !empty($filter_department) || !empty($filter_year) || !empty($search_query)): ?>
                                <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-primary mt-3">
                                    <i class="fas fa-redo me-1"></i>Clear All Filters
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Help Modal -->
        <div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="helpModalLabel">
                            <i class="fas fa-question-circle me-2"></i>Help & Instructions
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="accordion" id="helpAccordion">
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingOne">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                        How to upload a question paper
                                    </button>
                                </h2>
                                <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#helpAccordion">
                                    <div class="accordion-body">
                                        <ol>
                                            <li>Fill in all the required fields in the "Upload New Question Paper" section.</li>
                                            <li>Make sure to provide the correct department, course code, and subject details.</li>
                                            <li>The question paper must be in PDF format and not exceed 10MB in size.</li>
                                            <li>Click the "Upload Question Paper" button to submit.</li>
                                        </ol>
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i>Each paper must have a unique combination of title, semester, subject, and year.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingTwo">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                        How to search and filter papers
                                    </button>
                                </h2>
                                <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#helpAccordion">
                                    <div class="accordion-body">
                                        <p>You can search and filter papers in several ways:</p>
                                        <ul>
                                            <li><strong>Search bar:</strong> Enter keywords to search in title, department, subject, or course code.</li>
                                            <li><strong>Department filter:</strong> Filter papers by specific department.</li>
                                            <li><strong>Year filter:</strong> Filter papers by the year they were created.</li>
                                            <li><strong>Semester filter:</strong> Filter papers by semester.</li>
                                            <li><strong>Subject filter:</strong> Filter papers by specific subject.</li>
                                        </ul>
                                        <p>You can combine multiple filters for more specific results.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingThree">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                        Managing question papers
                                    </button>
                                </h2>
                                <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#helpAccordion">
                                    <div class="accordion-body">
                                        <p>You can manage the question papers using these actions:</p>
                                        <ul>
                                            <li><strong>View:</strong> Opens the question paper in a new tab for viewing.</li>
                                            <li><strong>Delete:</strong> Removes the question paper from the system.</li>
                                        </ul>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle me-2"></i>Deleting a question paper is permanent and cannot be undone.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/jquery-ui.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.2.3/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Toggle collapse icons
            $('.collapse').on('show.bs.collapse', function () {
                const iconId = $(this).attr('id') + 'Icon';
                $('#' + iconId).removeClass('fa-chevron-down').addClass('fa-chevron-up');
            });
            
            $('.collapse').on('hide.bs.collapse', function () {
                const iconId = $(this).attr('id') + 'Icon';
                $('#' + iconId).removeClass('fa-chevron-up').addClass('fa-chevron-down');
            });
            
            // Loading overlay
            function showLoading() {
                $('#loadingOverlay').css('visibility', 'visible').css('opacity', '1');
            }
            
            function hideLoading() {
                $('#loadingOverlay').css('opacity', '0');
                setTimeout(function() {
                    $('#loadingOverlay').css('visibility', 'hidden');
                }, 300);
            }
            
            // Toast notification
            function showToast(message, type = 'success') {
                const toastId = 'toast-' + Date.now();
                const iconClass = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
                const borderColor = type === 'success' ? '#28a745' : '#dc3545';
                
                const toast = `
                    <div class="toast custom-toast" id="${toastId}" role="alert" aria-live="assertive" aria-atomic="true" style="border-left-color: ${borderColor}">
                        <div class="toast-header">
                            <i class="fas ${iconClass} me-2" style="color: ${borderColor}"></i>
                            <strong class="me-auto">Notification</strong>
                            <small>Just now</small>
                            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                        <div class="toast-body">
                            ${message}
                        </div>
                    </div>
                `;
                
                $('.toast-container').append(toast);
                const toastElement = document.getElementById(toastId);
                const bsToast = new bootstrap.Toast(toastElement, {
                    autohide: true,
                    delay: 5000
                });
                
                bsToast.show();
                
                // Add show class manually for animation
                setTimeout(() => {
                    $('#' + toastId).addClass('show');
                }, 100);
                
                // Remove the toast element after it's hidden
                toastElement.addEventListener('hidden.bs.toast', function () {
                    $(this).remove();
                });
            }
            
            // Autocomplete for departments
            const departments = <?php echo json_encode($departments); ?>;
            $("#department").autocomplete({
                source: departments,
                minLength: 1
            });
            
            // Autocomplete for subjects
            const subjects = <?php echo json_encode($subjects); ?>;
            $("#subject").autocomplete({
                source: subjects,
                minLength: 1
            });
            
            // Live filter update
            $('.filter-select').change(function() {
                $('#searchForm').submit();
            });
            
            // Upload form submission with validation and loading
            $('#uploadForm').submit(function(e) {
                // Basic validation
                const fileInput = $('#question_paper')[0];
                const fileSize = fileInput.files[0]?.size / 1024 / 1024; // in MB
                
                if (fileSize > 10) {
                    e.preventDefault();
                    showToast('File size exceeds 10MB limit. Please select a smaller file.', 'error');
                    return false;
                }
                
                showLoading();
                // Form will submit normally
            });
            
            // Handle delete button with AJAX
            $('.delete-btn').click(function() {
                const paperId = $(this).data('paper-id');
                const row = $(this).closest('tr');
                
                if (confirm('Are you sure you want to delete this question paper? This action cannot be undone.')) {
                    showLoading();
                    
                    $.ajax({
                        url: window.location.href,
                        type: 'POST',
                        data: {
                            delete_paper: true,
                            paper_id: paperId
                        },
                        dataType: 'json',
                        success: function(response) {
                            hideLoading();
                            
                            if (response.status === 'success') {
                                row.fadeOut(400, function() {
                                    $(this).remove();
                                    
                                    // Update count display if no papers left
                                    if ($('#papersTable tbody tr').length === 0) {
                                        location.reload(); // Reload to show "no papers" message
                                    }
                                });
                                
                                showToast(response.message);
                            } else {
                                showToast(response.message, 'error');
                            }
                        },
                        error: function() {
                            hideLoading();
                            showToast('An error occurred while deleting the question paper.', 'error');
                        }
                    });
                }
            });
            
            // View button tracking
            $('.view-btn').click(function() {
                // Highlight row when viewing
                $(this).closest('tr').addClass('table-primary');
                setTimeout(() => {
                    $(this).closest('tr').removeClass('table-primary');
                }, 3000);
            });
            
            // Add new item animation when page loads
            if (<?php echo isset($upload_success) ? 'true' : 'false'; ?>) {
                setTimeout(() => {
                    $('#papersTable tbody tr:first-child').addClass('new-item');
                }, 300);
            }
            
            // Mobile responsive adjustments
            function adjustForMobile() {
                if (window.innerWidth < 768) {
                    // Simplify table for mobile
                    $('#papersTable thead th:nth-child(3), #papersTable tbody td:nth-child(3)').hide();
                    $('#papersTable thead th:nth-child(5), #papersTable tbody td:nth-child(5)').hide();
                } else {
                    // Show all columns on desktop
                    $('#papersTable thead th:nth-child(3), #papersTable tbody td:nth-child(3)').show();
                    $('#papersTable thead th:nth-child(5), #papersTable tbody td:nth-child(5)').show();
                }
            }
            
            // Run on page load and window resize
            adjustForMobile();
            $(window).resize(adjustForMobile);
        });
    </script>

<?php
// Close connection
$conn->close();
?>