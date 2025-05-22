<?php
// Start session
session_start();
include('../db_connect.php'); // Changed to include from parent directory

// Check if student is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../index.php"); // Redirect if not a student
    exit();
}

// Initialize search filters
$departments = [];
$subjects = [];
$course_codes = [];
$semesters = [];
$years = [];

// Get all available filter options from database
$dept_query = mysqli_query($conn, "SELECT DISTINCT department FROM question_papers ORDER BY department");
while ($row = mysqli_fetch_assoc($dept_query)) {
    $departments[] = $row['department'];
}

$subj_query = mysqli_query($conn, "SELECT DISTINCT subject FROM question_papers ORDER BY subject");
while ($row = mysqli_fetch_assoc($subj_query)) {
    $subjects[] = $row['subject'];
}

$code_query = mysqli_query($conn, "SELECT DISTINCT course_code FROM question_papers ORDER BY course_code");
while ($row = mysqli_fetch_assoc($code_query)) {
    $course_codes[] = $row['course_code'];
}

$sem_query = mysqli_query($conn, "SELECT DISTINCT semester FROM question_papers ORDER BY semester");
while ($row = mysqli_fetch_assoc($sem_query)) {
    $semesters[] = $row['semester'];
}

$year_query = mysqli_query($conn, "SELECT DISTINCT paper_year FROM question_papers ORDER BY paper_year DESC");
while ($row = mysqli_fetch_assoc($year_query)) {
    $years[] = $row['paper_year'];
}

// Process search and filter parameters
$search_query = isset($_GET['search']) ? $_GET['search'] : '';
$filter_department = isset($_GET['filter_department']) ? $_GET['filter_department'] : '';
$filter_year = isset($_GET['filter_year']) ? $_GET['filter_year'] : '';
$filter_semester = isset($_GET['filter_semester']) ? $_GET['filter_semester'] : '';
$filter_subject = isset($_GET['filter_subject']) ? $_GET['filter_subject'] : '';

// Get department, year, semester, and subject options for the new filter section
$department_result = mysqli_query($conn, "SELECT DISTINCT department FROM question_papers ORDER BY department");
$year_result = mysqli_query($conn, "SELECT DISTINCT paper_year FROM question_papers ORDER BY paper_year DESC");
$semester_result = mysqli_query($conn, "SELECT DISTINCT semester FROM question_papers ORDER BY semester");
$subject_result = mysqli_query($conn, "SELECT DISTINCT subject FROM question_papers ORDER BY subject");

// Process search filters
$where_conditions = [];
$params = [];

// Add search query condition
if (!empty($search_query)) {
    $where_conditions[] = "(title LIKE ? OR department LIKE ? OR subject LIKE ? OR course_code LIKE ?)";
    $search_param = "%" . $search_query . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Add filter conditions
if (!empty($filter_department)) {
    $where_conditions[] = "department = ?";
    $params[] = $filter_department;
}

if (!empty($filter_subject)) {
    $where_conditions[] = "subject = ?";
    $params[] = $filter_subject;
}

if (!empty($filter_year)) {
    $where_conditions[] = "paper_year = ?";
    $params[] = $filter_year;
}

if (!empty($filter_semester)) {
    $where_conditions[] = "semester = ?";
    $params[] = $filter_semester;
}

// Legacy filter support
if (isset($_GET['department']) && $_GET['department'] != '') {
    $where_conditions[] = "department = ?";
    $params[] = $_GET['department'];
}

if (isset($_GET['subject']) && $_GET['subject'] != '') {
    $where_conditions[] = "subject = ?";
    $params[] = $_GET['subject'];
}

if (isset($_GET['course_code']) && $_GET['course_code'] != '') {
    $where_conditions[] = "course_code = ?";
    $params[] = $_GET['course_code'];
}

if (isset($_GET['semester']) && $_GET['semester'] != '') {
    $where_conditions[] = "semester = ?";
    $params[] = $_GET['semester'];
}

if (isset($_GET['year']) && $_GET['year'] != '') {
    $where_conditions[] = "paper_year = ?";
    $params[] = $_GET['year'];
}

// For pagination
$items_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Count total records for pagination
$count_sql = "SELECT COUNT(*) as total FROM question_papers";
if (!empty($where_conditions)) {
    $count_sql .= " WHERE " . implode(" AND ", $where_conditions);
}

$count_stmt = mysqli_prepare($conn, $count_sql);
if (!empty($params)) {
    $types = str_repeat('s', count($params));
    mysqli_stmt_bind_param($count_stmt, $types, ...$params);
}
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$count_row = mysqli_fetch_assoc($count_result);
$total_records = $count_row['total'];
$total_pages = ceil($total_records / $items_per_page);

// Define base URL for file viewing
$base_url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --primary-hover: #3a56d4;
            --secondary-color: #3f37c9;
            --accent-color: #4cc9f0;
            --danger-color: #ef476f;
            --success-color: #06d6a0;
            --warning-color: #ffd166;
            --dark-color: #1c1c33;
            --light-color: #f8f9fa;
            --gray-color: #6c757d;
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.1), 0 1px 3px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
            --border-radius: 8px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f0f2f5;
            color: #333;
            line-height: 1.6;
        }

        .dashboard-container {
            width: 95%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px 10px;
        }

        /* Header Styles */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e1e4e8;
        }

        .dashboard-header h1 {
            font-size: 28px;
            color: var(--dark-color);
            font-weight: 600;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-info span {
            font-weight: 500;
            color: var(--dark-color);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background-color: var(--primary-color);
            border-radius: 50%;
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            font-weight: bold;
        }

        .logout-btn {
            background-color: var(--danger-color);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .logout-btn:hover {
            background-color: #d64161;
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        /* Table Styles */
        .paper-table-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            overflow: hidden;
            margin-bottom: 25px;
        }
        
        .paper-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }
        
        .paper-table th, .paper-table td {
            padding: 15px;
            text-align: left;
            font-size: 14px;
        }
        
        .paper-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--dark-color);
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .paper-table tbody tr {
            border-bottom: 1px solid #e9ecef;
            transition: var(--transition);
        }
        
        .paper-table tbody tr:last-child {
            border-bottom: none;
        }
        
        .paper-table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .view-btn, .download-btn {
            padding: 6px 12px;
            border-radius: var(--border-radius);
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: var(--transition);
            margin-right: 5px;
        }

        .view-btn {
            background: var(--accent-color);
            color: white;
        }

        .view-btn:hover {
            background: #3ab7db;
            transform: translateY(-2px);
            color: white;
            text-decoration: none;
        }

        .download-btn {
            background: var(--success-color);
            color: white;
        }

        .download-btn:hover {
            background: #05b386;
            transform: translateY(-2px);
            color: white;
            text-decoration: none;
        }

        .actions-cell {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        /* Stats Cards */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--card-shadow);
            display: flex;
            flex-direction: column;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .stat-title {
            font-size: 16px;
            color: var(--gray-color);
            font-weight: 500;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 18px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 5px;
        }

        .stat-desc {
            font-size: 13px;
            color: var(--gray-color);
        }

        .bg-purple {
            background-color: rgba(149, 76, 233, 0.1);
            color: #954ce9;
        }

        .bg-blue {
            background-color: rgba(67, 97, 238, 0.1);
            color: var(--primary-color);
        }

        .bg-teal {
            background-color: rgba(6, 214, 160, 0.1);
            color: var(--success-color);
        }

        .bg-orange {
            background-color: rgba(255, 209, 102, 0.1);
            color: var(--warning-color);
        }

        /* Responsive Table Container */
        .table-responsive {
            overflow-x: auto;
            position: relative;
        }

        /* Error Message */
        .error-message {
            background-color: #feecf0;
            color: var(--danger-color);
            padding: 15px;
            margin-bottom: 20px;
            border-radius: var(--border-radius);
            border-left: 4px solid var(--danger-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .error-message i {
            font-size: 20px;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 20px;
        }

        .pagination a, .pagination span {
            padding: 8px 12px;
            border-radius: var(--border-radius);
            text-decoration: none;
            transition: var(--transition);
            font-size: 14px;
        }

        .pagination a {
            background: white;
            color: var(--dark-color);
        }

        .pagination a:hover {
            background: var(--accent-color);
            color: white;
        }

        .pagination .active {
            background: var(--primary-color);
            color: white;
        }

        .pagination .disabled {
            background: #f8f9fa;
            color: #ccc;
            cursor: not-allowed;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray-color);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 18px;
            margin-bottom: 10px;
            color: var(--dark-color);
        }

        .empty-state p {
            font-size: 14px;
            max-width: 400px;
            margin: 0 auto;
        }

        /* New Filter Section */
        .filter-section {
            background: white;
            border-radius: var(--border-radius);
            margin-bottom: 25px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }

        .filter-section .card-header {
            background-color: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
        }

        .filter-section h3 {
            font-size: 18px;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }

        .filter-section .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 5px 10px;
            font-size: 14px;
        }

        .filter-section .btn-outline-primary:hover {
            background-color: var(--primary-color);
            color: white;
        }

        .filter-section .card-body {
            padding: 20px;
        }

        .filter-section .search-bar {
            width: 100%;
        }

        .filter-section .input-group {
            position: relative;
        }

        .filter-section .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-color);
            z-index: 10;
        }

        .filter-section input.form-control {
            padding-left: 40px;
            border-radius: var(--border-radius) 0 0 var(--border-radius);
            border: 1px solid #ddd;
            font-size: 14px;
            height: 42px;
        }

        .filter-section .btn-outline-secondary {
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
            border: 1px solid #ddd;
            border-left: none;
            color: var(--dark-color);
            background: white;
            font-size: 14px;
        }

        .filter-section .btn-outline-secondary:hover {
            background-color: #f8f9fa;
            color: var(--dark-color);
        }

        .filter-section .row {
            margin-top: 15px;
        }

        .filter-section .form-label {
            font-size: 14px;
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 8px;
        }

        .filter-section .form-select {
            border: 1px solid #ddd;
            font-size: 14px;
            padding: 10px;
            border-radius: var(--border-radius);
        }

        .filter-section .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            margin-top: 15px;
        }

        .filter-section .btn-primary:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
        }

        .filter-section .btn-secondary {
            background-color: #f8f9fa;
            border-color: #ddd;
            color: var(--gray-color);
            margin-top: 15px;
        }

        .filter-section .btn-secondary:hover {
            background-color: #e9ecef;
            color: var(--dark-color);
        }

        /* Responsive Styles */
        @media (max-width: 992px) {
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .user-info {
                width: 100%;
                justify-content: space-between;
            }

            .stats-row {
                grid-template-columns: 1fr;
            }
            
            .filter-section .row > div {
                margin-bottom: 15px;
            }

            .actions-cell {
                flex-direction: column;
                align-items: flex-start;
            }

            .view-btn, .download-btn {
                margin-right: 0;
                margin-bottom: 5px;
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 576px) {
            .dashboard-container {
                padding: 10px 5px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1><i class="fas fa-graduation-cap"></i> Student Dashboard</h1>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                </div>
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
                <a href="../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?php 
                    echo htmlspecialchars($_SESSION['error']); 
                    unset($_SESSION['error']); 
                ?>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Question Papers</span>
                    <div class="stat-icon bg-purple">
                        <i class="fas fa-file-alt"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $total_records; ?></div>
                <div class="stat-desc">Total question papers available</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Subjects</span>
                    <div class="stat-icon bg-blue">
                        <i class="fas fa-book"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo count($subjects); ?></div>
                <div class="stat-desc">Different subjects available</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Departments</span>
                    <div class="stat-icon bg-teal">
                        <i class="fas fa-building"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo count($departments); ?></div>
                <div class="stat-desc">Academic departments</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Years</span>
                    <div class="stat-icon bg-orange">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo count($years); ?></div>
                <div class="stat-desc">Different exam years</div>
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
                        <form method="get" id="advancedSearchForm">
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
                                        <?php 
                                        mysqli_data_seek($department_result, 0); // Reset pointer
                                        while ($dept = mysqli_fetch_assoc($department_result)): 
                                        ?>
                                            <option value="<?php echo htmlspecialchars($dept['department']); ?>" <?php echo $filter_department == $dept['department'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($dept['department']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-3">
                                    <label class="form-label">Subject</label>
                                    <select class="form-select filter-select" name="filter_subject">
                                        <option value="">All Subjects</option>
                                        <?php 
                                        mysqli_data_seek($subject_result, 0); // Reset pointer
                                        while ($subj = mysqli_fetch_assoc($subject_result)): 
                                        ?>
                                            <option value="<?php echo htmlspecialchars($subj['subject']); ?>" <?php echo $filter_subject == $subj['subject'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($subj['subject']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-3">
                                    <label class="form-label">Year</label>
                                    <select class="form-select filter-select" name="filter_year">
                                        <option value="">All Years</option>
                                        <?php 
                                        mysqli_data_seek($year_result, 0); // Reset pointer
                                        while ($year = mysqli_fetch_assoc($year_result)): 
                                        ?>
                                            <option value="<?php echo htmlspecialchars($year['paper_year']); ?>" <?php echo $filter_year == $year['paper_year'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($year['paper_year']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-3">
                                    <label class="form-label">Semester</label>
                                    <select class="form-select filter-select" name="filter_semester">
                                        <option value="">All Semesters</option>
                                        <?php 
                                        mysqli_data_seek($semester_result, 0); // Reset pointer
                                        while ($sem = mysqli_fetch_assoc($semester_result)): 
                                        ?>
                                            <option value="<?php echo htmlspecialchars($sem['semester']); ?>" <?php echo $filter_semester == $sem['semester'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($sem['semester']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-end mt-3">
                                <a href="index.php" class="btn btn-secondary me-2">
                                    <i class="fas fa-sync-alt me-1"></i> Reset
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter me-1"></i> Apply Filters
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Question Papers Table -->
        <div class="paper-table-container">
            <div class="table-responsive">
                <?php
                // Main query for retrieving question papers
                $sql = "SELECT * FROM question_papers";
                if (!empty($where_conditions)) {
                    $sql .= " WHERE " . implode(" AND ", $where_conditions);
                }
                $sql .= " ORDER BY uploaded_at DESC LIMIT ? OFFSET ?";
                
                $stmt = mysqli_prepare($conn, $sql);
                
                // Bind all the params including pagination
                if (!empty($params)) {
                    $params[] = $items_per_page;
                    $params[] = $offset;
                    $types = str_repeat('s', count($params) - 2) . 'ii'; // All strings plus two integers
                    mysqli_stmt_bind_param($stmt, $types, ...$params);
                } else {
                    mysqli_stmt_bind_param($stmt, "ii", $items_per_page, $offset);
                }
                
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                // Check if there are any results
                if (mysqli_num_rows($result) > 0) {
                ?>
                <table class="paper-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Department</th>
                            <th>Subject</th>
                            <th>Course Code</th>
                            <th>Semester</th>
                            <th>Year</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                            <td><?php echo htmlspecialchars($row['department']); ?></td>
                            <td><?php echo htmlspecialchars($row['subject']); ?></td>
                            <td><?php echo htmlspecialchars($row['course_code']); ?></td>
                            <td><?php echo htmlspecialchars($row['semester']); ?></td>
                            <td><?php echo htmlspecialchars($row['paper_year']); ?></td>
                            <td class="actions-cell">
                                <a href="view_document.php?id=<?php echo $row['id']; ?>&type=question_papers" class="view-btn" target="_blank">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php
                } else {
                    // No results found
                    echo '<div class="empty-state">
                            <i class="fas fa-search"></i>
                            <h3>No Question Papers Found</h3>
                            <p>Try adjusting your search filters or try a different search term.</p>
                          </div>';
                }
                ?>
            </div>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=1<?php echo !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) : ''; ?>">
                    <i class="fas fa-angle-double-left"></i>
                </a>
                <a href="?page=<?php echo $page - 1; ?><?php echo !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) : ''; ?>">
                    <i class="fas fa-angle-left"></i>
                </a>
            <?php else: ?>
                <span class="disabled"><i class="fas fa-angle-double-left"></i></span>
                <span class="disabled"><i class="fas fa-angle-left"></i></span>
            <?php endif; ?>
            
            <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                for ($i = $start_page; $i <= $end_page; $i++):
            ?>
                <?php if ($i == $page): ?>
                    <span class="active"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?page=<?php echo $i; ?><?php echo !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?><?php echo !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) : ''; ?>">
                    <i class="fas fa-angle-right"></i>
                </a>
                <a href="?page=<?php echo $total_pages; ?><?php echo !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) : ''; ?>">
                    <i class="fas fa-angle-double-right"></i>
                </a>
            <?php else: ?>
                <span class="disabled"><i class="fas fa-angle-right"></i></span>
                <span class="disabled"><i class="fas fa-angle-double-right"></i></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle filter collapse icon
        document.querySelector('[data-bs-toggle="collapse"]').addEventListener('click', function() {
            const icon = document.getElementById('filterCollapseIcon');
            if (icon.classList.contains('fa-chevron-down')) {
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            } else {
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            }
        });

        // Dynamic filtering - update available options based on selections
        const filterSelects = document.querySelectorAll('.filter-select');
        
        filterSelects.forEach(select => {
            select.addEventListener('change', function() {
                const department = document.querySelector('select[name="filter_department"]').value;
                const subject = document.querySelector('select[name="filter_subject"]').value;
                const year = document.querySelector('select[name="filter_year"]').value;
                const semester = document.querySelector('select[name="filter_semester"]').value;
                
                // You can implement AJAX here to dynamically update other filter options
                // based on the current selection
                console.log('Filters changed:', { department, subject, year, semester });
            });
        });
    </script>
</body>
</html>