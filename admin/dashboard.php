<?php
// Start session
session_start();

// Include database connection
include('../db_connect.php');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // Redirect to login page
    header("Location: ../index.php");
    exit();
}

// Process user approval
if (isset($_POST['approve_user'])) {
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
    if ($user_id) {
        $stmt = $conn->prepare("UPDATE users SET status = 'approved', updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $success_message = "User has been approved successfully.";
        } else {
            $error_message = "Error approving user: " . $conn->error;
        }
        $stmt->close();
    }
}

// Process user rejection
if (isset($_POST['reject_user'])) {
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
    $rejection_reason = filter_input(INPUT_POST, 'rejection_reason', FILTER_SANITIZE_STRING);
    
    if ($user_id) {
        // Update to store the rejection reason
        $stmt = $conn->prepare("UPDATE users SET status = 'rejected', rejection_reason = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $rejection_reason, $user_id);
        if ($stmt->execute()) {
            $success_message = "User registration has been rejected.";
        } else {
            $error_message = "Error rejecting user: " . $conn->error;
        }
        $stmt->close();
    }
}

// Get user counts for dashboard stats
$student_count = 0;
$teacher_count = 0;
$pending_teacher_count = 0;

$stmt = $conn->prepare("SELECT role, status, COUNT(*) as count FROM users GROUP BY role, status");
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    if ($row['role'] == 'student') {
        $student_count += $row['count'];
    } else if ($row['role'] == 'teacher') {
        $teacher_count += $row['count'];
        if ($row['status'] == 'pending') {
            $pending_teacher_count = $row['count'];
        }
    }
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management Dashboard</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.2.3/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/jquery.dataTables.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.2.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin-dashboard.css">
</head>
<body>
    <!-- Alert Container for notifications -->
    <div class="alert-container" id="alertContainer">
        <?php if (isset($success_message)): ?>
            <div class="custom-alert alert-success">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="custom-alert alert-danger">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Sidebar Toggle Button for Mobile -->
    <button class="btn btn-primary toggle-sidebar" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3>Admin Dashboard</h3>
            <p class="mb-0 text-light">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
        </div>
        <a href="#" class="active">
            <i class="fas fa-users"></i> User Management
        </a>
        <a href="../logout.php" class="mt-5">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
    
    <!-- Page Content -->
    <div class="content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>User Management</h1>
            </div>
            
            <!-- Stats Cards -->
            <div class="row">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-stats bg-primary text-white">
                            <i class="fas fa-user-graduate"></i>
                            <h2 id="studentCount"><?php echo $student_count; ?></h2>
                            <p>Students</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-stats bg-warning text-white">
                            <i class="fas fa-chalkboard-teacher"></i>
                            <h2 id="teacherCount"><?php echo $teacher_count; ?></h2>
                            <p>Teachers</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-stats bg-info text-white">
                            <i class="fas fa-user-clock"></i>
                            <h2 id="pendingTeacherCount"><?php echo $pending_teacher_count; ?></h2>
                            <p>Pending Teacher Approvals</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- User Tabs -->
            <div class="card">
                <div class="card-header bg-white">
                    <ul class="nav nav-tabs card-header-tabs" id="userTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="students-tab" data-bs-toggle="tab" data-bs-target="#students" type="button" role="tab" aria-controls="students" aria-selected="true">
                                <i class="fas fa-user-graduate me-2"></i>Students
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="teachers-tab" data-bs-toggle="tab" data-bs-target="#teachers" type="button" role="tab" aria-controls="teachers" aria-selected="false">
                                <i class="fas fa-chalkboard-teacher me-2"></i>Teachers
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab" aria-controls="pending" aria-selected="false">
                                <i class="fas fa-user-clock me-2"></i>Pending Approvals
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="userTabsContent">
                        <!-- Students Tab -->
                        <div class="tab-pane fade show active" id="students" role="tabpanel" aria-labelledby="students-tab">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">All Students</h5>
                                <div class="input-group" style="width: 300px;">
                                    <input type="text" class="form-control student-search" placeholder="Search students...">
                                    <button class="btn btn-outline-secondary" type="button">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover" id="studentsTable">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Status</th>
                                            <th>Registration Date</th>
                                            <th>Last Updated</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="studentTableBody">
                                        <?php
                                        // Fetch all students
                                        $stmt = $conn->prepare("SELECT id, username, email, status, created_at, updated_at FROM users WHERE role = 'student' ORDER BY id DESC");
                                        $stmt->execute();
                                        $result = $stmt->get_result();
                                        
                                        if ($result->num_rows > 0) {
                                            while ($row = $result->fetch_assoc()) {
                                                // Create status badge
                                                if ($row['status'] == 'approved') {
                                                    $statusBadge = '<span class="approved">approved</span>';
                                                } elseif ($row['status'] == 'pending') {
                                                    $statusBadge = '<span class="pending">pending</span>';
                                                } else {
                                                    $statusBadge = '<span class="rejected">rejected</span>';
                                                }
                                                
                                                echo '<tr>';
                                                echo '<td>' . $row['id'] . '</td>';
                                                echo '<td>' . htmlspecialchars($row['username']) . '</td>';
                                                echo '<td>' . htmlspecialchars($row['email']) . '</td>';
                                                echo '<td>' . $statusBadge . '</td>';
                                                echo '<td>' . $row['created_at'] . '</td>';
                                                echo '<td>' . $row['updated_at'] . '</td>';
                                                echo '<td>
                                                        <button class="btn btn-sm btn-info view-btn" data-id="' . $row['id'] . '">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    </td>';
                                                echo '</tr>';
                                            }
                                        } else {
                                            echo '<tr><td colspan="7" class="text-center">No students found</td></tr>';
                                        }
                                        $stmt->close();
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Teachers Tab -->
                        <div class="tab-pane fade" id="teachers" role="tabpanel" aria-labelledby="teachers-tab">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">All Teachers</h5>
                                <div class="input-group" style="width: 300px;">
                                    <input type="text" class="form-control teacher-search" placeholder="Search teachers...">
                                    <button class="btn btn-outline-secondary" type="button">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover" id="teachersTable">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Status</th>
                                            <th>Registration Date</th>
                                            <th>Last Updated</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="teacherTableBody">
                                        <?php
                                        // Fetch all teachers
                                        $stmt = $conn->prepare("SELECT id, username, email, status, created_at, updated_at FROM users WHERE role = 'teacher' ORDER BY id DESC");
                                        $stmt->execute();
                                        $result = $stmt->get_result();
                                        
                                        if ($result->num_rows > 0) {
                                            while ($row = $result->fetch_assoc()) {
                                                // Create status badge
                                                if ($row['status'] == 'approved') {
                                                    $statusBadge = '<span class="approved">approved</span>';
                                                } elseif ($row['status'] == 'pending') {
                                                    $statusBadge = '<span class="pending">pending</span>';
                                                } else {
                                                    $statusBadge = '<span class="rejected">rejected</span>';
                                                }
                                                
                                                echo '<tr>';
                                                echo '<td>' . $row['id'] . '</td>';
                                                echo '<td>' . htmlspecialchars($row['username']) . '</td>';
                                                echo '<td>' . htmlspecialchars($row['email']) . '</td>';
                                                echo '<td>' . $statusBadge . '</td>';
                                                echo '<td>' . $row['created_at'] . '</td>';
                                                echo '<td>' . $row['updated_at'] . '</td>';
                                                echo '<td>
                                                        <button class="btn btn-sm btn-info view-btn" data-id="' . $row['id'] . '">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    </td>';
                                                echo '</tr>';
                                            }
                                        } else {
                                            echo '<tr><td colspan="7" class="text-center">No teachers found</td></tr>';
                                        }
                                        $stmt->close();
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Pending Approvals Tab -->
                        <div class="tab-pane fade" id="pending" role="tabpanel" aria-labelledby="pending-tab">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">Pending Teacher Approvals</h5>
                                <div class="input-group" style="width: 300px;">
                                    <input type="text" class="form-control pending-search" placeholder="Search pending...">
                                    <button class="btn btn-outline-secondary" type="button">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover" id="pendingTable">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Registration Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="pendingTableBody">
                                        <?php
                                        // Fetch pending teachers
                                        $stmt = $conn->prepare("SELECT id, username, email, created_at FROM users WHERE role = 'teacher' AND status = 'pending' ORDER BY created_at DESC");
                                        $stmt->execute();
                                        $result = $stmt->get_result();
                                        
                                        if ($result->num_rows > 0) {
                                            while ($row = $result->fetch_assoc()) {
                                                echo '<tr>';
                                                echo '<td>' . $row['id'] . '</td>';
                                                echo '<td>' . htmlspecialchars($row['username']) . '</td>';
                                                echo '<td>' . htmlspecialchars($row['email']) . '</td>';
                                                echo '<td>' . $row['created_at'] . '</td>';
                                                echo '<td>
                                                        <button class="btn btn-sm btn-success approve-btn" data-id="' . $row['id'] . '">
                                                            <i class="fas fa-check"></i> Approve
                                                        </button>
                                                        <button class="btn btn-sm btn-danger reject-btn" data-id="' . $row['id'] . '">
                                                            <i class="fas fa-times"></i> Reject
                                                        </button>
                                                    </td>';
                                                echo '</tr>';
                                            }
                                        } else {
                                            echo '<tr><td colspan="5" class="text-center">No pending teacher approvals</td></tr>';
                                        }
                                        $stmt->close();
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- View User Details Modal -->
    <div class="modal fade" id="viewUserModal" tabindex="-1" aria-labelledby="viewUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewUserModalLabel">User Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3 text-center">
                        <i class="fas fa-user-circle fa-5x text-primary"></i>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Username:</label>
                        <p id="viewUsername" class="border-bottom pb-2"></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Email:</label>
                        <p id="viewEmail" class="border-bottom pb-2"></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Role:</label>
                        <p id="viewRole" class="border-bottom pb-2"></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Status:</label>
                        <p id="viewStatus" class="border-bottom pb-2"></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Registration Date:</label>
                        <p id="viewCreatedAt" class="border-bottom pb-2"></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Last Updated:</label>
                        <p id="viewUpdatedAt" class="border-bottom pb-2"></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Approve Confirmation Modal -->
    <div class="modal fade" id="approveUserModal" tabindex="-1" aria-labelledby="approveUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="approveUserModalLabel">Confirm Approval</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to approve this teacher's registration?</p>
                    <p class="text-muted">Once approved, the teacher will be able to log in to the system.</p>
                    <form id="approveForm" method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        <input type="hidden" id="approveUserId" name="user_id">
                        <input type="hidden" name="approve_user" value="1">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmApproveBtn">Approve Teacher</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Reject Confirmation Modal -->
    <div class="modal fade" id="rejectUserModal" tabindex="-1" aria-labelledby="rejectUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rejectUserModalLabel">Confirm Rejection</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to reject this teacher's registration?</p>
                    <form id="rejectForm" method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        <div class="mb-3">
                            <label for="rejectionReason" class="form-label">Rejection Reason (Optional)</label>
                            <textarea class="form-control" id="rejectionReason" name="rejection_reason" rows="3" placeholder="Provide a reason for rejecting this teacher registration"></textarea>
                        </div>
                        <input type="hidden" id="rejectUserId" name="user_id">
                        <input type="hidden" name="reject_user" value="1">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmRejectBtn">Reject Registration</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Function to show alerts
        function showAlert(message, type) {
            const alertHTML = `
                <div class="custom-alert alert-${type}">
                    ${message}
                </div>
            `;
            $('#alertContainer').html(alertHTML);

            // Auto-hide the alert after 5 seconds
            setTimeout(function() {
                $('.custom-alert').fadeOut('slow', function() {
                    $(this).remove();
                });
            }, 5000);
        }
        
        $(document).ready(function() {
            // Initialize DataTables
            $('#studentsTable').DataTable({
                "paging": true,
                "searching": true,
                "ordering": true,
                "info": true,
                "pageLength": 10
            });
            
            $('#teachersTable').DataTable({
                "paging": true,
                "searching": true,
                "ordering": true,
                "info": true,
                "pageLength": 10
            });
            
            $('#pendingTable').DataTable({
                "paging": true,
                "searching": true,
                "ordering": true,
                "info": true,
                "pageLength": 10
            });
            
            // Sidebar toggle functionality for mobile
            $('#sidebarToggle').click(function() {
                $('.sidebar').toggleClass('active');
                $('.content').toggleClass('active');
            });
            
            // View User Details
            $(document).on('click', '.view-btn', function() {
                const userId = $(this).data('id');
                
                $.ajax({
                    url: 'get_user_details.php',
                    type: 'POST',
                    data: { user_id: userId },
                    dataType: 'json',
                    success: function(user) {
                        $('#viewUsername').text(user.username);
                        $('#viewEmail').text(user.email);
                        $('#viewRole').text(user.role);
                        
                        // Status badge
                        let statusBadge = '';
                        if (user.status === 'approved') {
                            statusBadge = `<span class="approved">${user.status}</span>`;
                        } else if (user.status === 'pending') {
                            statusBadge = `<span class="pending">${user.status}</span>`;
                        } else {
                            statusBadge = `<span class="rejected">${user.status}</span>`;
                        }
                        $('#viewStatus').html(statusBadge);
                        
                        $('#viewCreatedAt').text(user.created_at);
                        $('#viewUpdatedAt').text(user.updated_at);
                        
                        $('#viewUserModal').modal('show');
                    },
                    error: function() {
                        showAlert('Error fetching user details', 'danger');
                    }
                });
            });
            
            // Approve Teacher
            $(document).on('click', '.approve-btn', function() {
                const userId = $(this).data('id');
                $('#approveUserId').val(userId);
                $('#approveUserModal').modal('show');
            });
            
            // Submit approve form
            $('#confirmApproveBtn').click(function() {
                $('#approveForm').submit();
            });
            
            // Reject Teacher
            $(document).on('click', '.reject-btn', function() {
                const userId = $(this).data('id');
                $('#rejectUserId').val(userId);
                $('#rejectUserModal').modal('show');
            });
            
            // Submit reject form
            $('#confirmRejectBtn').click(function() {
                $('#rejectForm').submit();
            });
        });
    </script>
</body>
</html>