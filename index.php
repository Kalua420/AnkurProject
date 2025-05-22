<?php
// Start session
session_start();

// Database connection
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "paper_archive";

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize variables
$login_error = "";
$signup_error = "";
$signup_success = "";
$selected_role = isset($_GET['role']) ? $_GET['role'] : 'STUDENT'; // Default role

// Process login form
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['email']) && isset($_POST['password']) && !isset($_POST['username'])) {
    // Get form data
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $role = trim($_POST['login_role']); // Added role selection for login
    $remember = isset($_POST['remember']) ? 1 : 0;
    
    // Validate input
    if (empty($email) || empty($password)) {
        $login_error = "Email and password are required";
    } else {
        // Get user data with role filter
        $stmt = $conn->prepare("SELECT id, username, email, password, role, status FROM users WHERE email = ? AND role = ?");
        $stmt->bind_param("ss", $email, $role);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Check if user is approved
                if ($user['status'] !== 'approved') {
                    $login_error = "Your account is pending approval. Please contact the administrator.";
                } else {
                    // Login successful
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    
                    // Handle remember me
                    if ($remember) {
                        // Generate token
                        $token = bin2hex(random_bytes(32));
                        $token_hash = hash('sha256', $token);
                        
                        // Set expiry (30 days)
                        $expiry = date('Y-m-d H:i:s', time() + 30 * 24 * 60 * 60);
                        
                        // Save token to database
                        $update_stmt = $conn->prepare("UPDATE users SET remember_token = ?, token_expires = ? WHERE id = ?");
                        $update_stmt->bind_param("ssi", $token_hash, $expiry, $user['id']);
                        $update_stmt->execute();
                        $update_stmt->close();
                        
                        // Set cookie
                        setcookie('remember_me', $token, time() + 30 * 24 * 60 * 60, '/');
                    }
                    
                    // Check if AJAX request
                    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                        echo json_encode(['success' => true, 'message' => 'Login successful!', 'role' => $user['role']]);
                        exit;
                    }
                    
                    // Redirect based on role
                    switch ($user['role']) {
                        case 'admin':
                            header("Location: admin/dashboard.php");
                            break;
                        case 'teacher':
                            header("Location: teacher/dashboard.php");
                            break;
                        case 'student':
                        default:
                            header("Location: student/dashboard.php");
                            break;
                    }
                    exit;
                }
            } else {
                $login_error = "Invalid email or password";
            }
        } else {
            $login_error = "Invalid email or password for selected role";
        }
        $stmt->close();
    }
    
    // Check if AJAX request
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        echo json_encode(['success' => false, 'errors' => [$login_error]]);
        exit;
    }
}

// Process signup form
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['username']) && isset($_POST['confirm_password'])) {
    // Get form data
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $role = isset($_POST['role']) ? trim($_POST['role']) : 'STUDENT';
    $terms = isset($_POST['terms']) ? 1 : 0;
    
    $errors = [];
    
    // Validate input
    if (empty($username)) {
        $errors[] = "Username is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    if (!$terms) {
        $errors[] = "You must accept the terms and conditions";
    }
    
    // Validate role
    if (!in_array($role, ['admin', 'teacher', 'student'])) {
        $errors[] = "Invalid role selected";
    }
    
    // Check if email already exists for the selected role
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND role = ?");
    $stmt->bind_param("ss", $email, $role);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $errors[] = "Email is already registered with this role";
    }
    $stmt->close();
    
    // Determine account status
    $status = 'pending';
    if ($role === 'student') {
        $status = 'approved'; // Auto-approve students
    }
    
    // If no errors, proceed with registration
    if (empty($errors)) {
        // Hash password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Current date
        $created_at = date("Y-m-d H:i:s");
        
        // Insert user data with role
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $username, $email, $password_hash, $role, $status, $created_at);
        
        if ($stmt->execute()) {
            // Registration successful
            if ($status === 'pending') {
                $signup_success = "Registration successful! Your account is pending approval.";
            } else {
                $signup_success = "Registration successful! You can now login.";
            }
            
            // Check if AJAX request
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                echo json_encode(['success' => true, 'message' => $signup_success, 'role' => $role]);
                exit;
            }
        } else {
            $errors[] = "Registration failed: " . $stmt->error;
        }
        $stmt->close();
    }
    
    // Join errors into a single string
    if (!empty($errors)) {
        $signup_error = implode("<br>", $errors);
        
        // Check if AJAX request
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit;
        }
    }
}

// Auto-login with remember token
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    $token = $_COOKIE['remember_me'];
    $token_hash = hash('sha256', $token);
    
    // Get user with matching token that hasn't expired
    $stmt = $conn->prepare("SELECT id, username, email, role, status FROM users WHERE remember_token = ? AND token_expires > NOW()");
    $stmt->bind_param("s", $token_hash);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Check if user is approved
        if ($user['status'] === 'approved') {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            
            // Redirect based on role
            switch ($user['role']) {
                case 'admin':
                    header("Location: admin/dashboard.php");
                    break;
                case 'teacher':
                    header("Location: teacher/dashboard.php");
                    break;
                case 'student':
                default:
                    header("Location: student/dashboard.php");
                    break;
            }
            exit;
        }
    }
    $stmt->close();
}

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect based on role
    switch ($_SESSION['role']) {
        case 'admin':
            header("Location: admin/dashboard.php");
            break;
        case 'teacher':
            header("Location: teacher/dashboard.php");
            break;
        case 'student':
        default:
            header("Location: student/dashboard.php");
            break;
    }
    exit;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <!-- ===== Iconscout CSS ===== -->
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css" />
    <!-- ===== CSS ===== -->
    <link rel="stylesheet" href="style.css" />
    <title>Login & Registration Form</title>
    <style>
        /* Basic styles in case style.css is missing */
        .error-message {
            color: red;
            margin: 10px 0;
            display: none;
        }
        .success-message {
            color: green;
            margin: 10px 0;
        }
        .form.signup {
            opacity: 0;
            pointer-events: none;
        }
        .container.active .form.signup {
            opacity: 1;
            pointer-events: auto;
        }
        .container.active .form.login {
            opacity: 0;
            pointer-events: none;
        }
        .role-select {
            margin-bottom: 15px;
        }
        .role-select select {
            width: 100%;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #ddd;
            font-size: 16px;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .login-tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }
        .login-tab {
            flex: 1;
            padding: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .login-tab.active {
            border-bottom: 4px solid #0171d3;
            color: #0171d3;
            font-weight: bold;
        }
        .login-tab:hover {
            background-color: #f5f5f5;
        }
        .role-icon {
            margin-right:10px;
        }
    </style>
</head>
<body>
    <div class="container <?php echo (!empty($signup_error) || !empty($signup_success)) ? 'active' : ''; ?>">
        <div class="forms">
            <!-- Login Form -->
            <div class="form login">
                <span class="title">Login</span>
                
                <!-- Login Tabs for Role Selection -->
                <div class="login-tabs">
                    <div class="login-tab <?php echo $selected_role === 'student' ? 'active' : ''; ?>" data-role="student">
                        <i class="uil uil-user-circle role-icon"></i>STUDENT
                    </div>
                    <div class="login-tab <?php echo $selected_role === 'teacher' ? 'active' : ''; ?>" data-role="teacher">
                        <i class="uil uil-book-reader role-icon"></i>TEACHER
                    </div>
                    <div class="login-tab <?php echo $selected_role === 'admin' ? 'active' : ''; ?>" data-role="admin">
                        <i class="uil uil-shield role-icon"></i>ADMIN
                    </div>
                </div>
                
                <form id="loginForm" method="POST">
                    <input type="hidden" name="login_role" id="login_role" value="<?php echo $selected_role; ?>">
                    
                    <div class="input-field">
                        <input type="email" name="email" placeholder="Enter your email" required />
                        <i class="uil uil-envelope icon"></i>
                    </div>

                    <div class="input-field">
                        <input type="password" name="password" class="password" placeholder="Enter your password" required />
                        <i class="uil uil-lock icon"></i>
                        <i class="uil uil-eye-slash showHidePw"></i>
                    </div>

                    <div class="checkbox-text">
                        <div class="checkbox-content">
                            <input type="checkbox" id="logCheck" name="remember" />
                            <label for="logCheck" class="text">Remember me</label>
                        </div>

                        <a href="#" class="text">Forgot password?</a>
                    </div>

                    <div class="input-field button">
                        <input type="submit" value="Login" />
                    </div>

                    <?php if (!empty($login_error)): ?>
                    <div id="loginError" class="error-message" style="display: block;">
                        <?php echo $login_error; ?>
                    </div>
                    <?php else: ?>
                    <div id="loginError" class="error-message"></div>
                    <?php endif; ?>
                </form>

                <div class="login-signup">
                    <span class="text">
                        Not a member?
                        <a href="#" class="text signup-link">Signup Now</a>
                    </span>
                </div>
            </div>

            <!-- Registration Form -->
            <div class="form signup">
                <span class="title">Registration</span>

                <?php if (!empty($signup_success)): ?>
                <div class="success-message">
                    <?php echo $signup_success; ?>
                    <p>You can now <a href="#" class="login-link">login</a> with your credentials.</p>
                </div>
                <?php else: ?>

                <form id="signupForm" method="POST">
                    <div class="input-field">
                        <input type="text" name="username" placeholder="Enter your name" required />
                        <i class="uil uil-user"></i>
                    </div>
                    <div class="input-field">
                        <input type="email" name="email" placeholder="Enter your email" required />
                        <i class="uil uil-envelope icon"></i>
                    </div>
                    <div class="input-field">
                        <input type="password" name="password" class="password" placeholder="Create a password" required />
                        <i class="uil uil-lock icon"></i>
                    </div>
                    <div class="input-field">
                        <input type="password" name="confirm_password" class="password" placeholder="Confirm a password" required />
                        <i class="uil uil-lock icon"></i>
                        <i class="uil uil-eye-slash showHidePw"></i>
                    </div>

                    <div class="role-select">
                        <select name="role" id="role">
                            <option value="student">Student</option>
                            <option value="teacher">Teacher</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                    
                    <div id="roleNoteContainer" class="status-pending" style="display: none;">
                        <p id="roleNote">Note: Teacher and admin accounts require approval before login.</p>
                    </div>

                    <div class="checkbox-text">
                        <div class="checkbox-content">
                            <input type="checkbox" id="termCon" name="terms" required />
                            <label for="termCon" class="text">I accepted all terms and conditions</label>
                        </div>
                    </div>

                    <div class="input-field button">
                        <input type="submit" value="Signup" />
                    </div>

                    <?php if (!empty($signup_error)): ?>
                    <div id="signupError" class="error-message" style="display: block;">
                        <?php echo $signup_error; ?>
                    </div>
                    <?php else: ?>
                    <div id="signupError" class="error-message"></div>
                    <?php endif; ?>
                </form>

                <?php endif; ?>

                <div class="login-signup">
                    <span class="text">
                        Already a member?
                        <a href="#" class="text login-link">Login Now</a>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <script>
    const container = document.querySelector(".container");
    const pwShowHide = document.querySelectorAll(".showHidePw");
    const pwFields = document.querySelectorAll(".password");
    const signUp = document.querySelector(".signup-link");
    const login = document.querySelector(".login-link");
    const loginForm = document.getElementById("loginForm");
    const signupForm = document.getElementById("signupForm");
    const loginError = document.getElementById("loginError");
    const signupError = document.getElementById("signupError");
    const roleSelect = document.getElementById("role");
    const roleNoteContainer = document.getElementById("roleNoteContainer");
    const loginTabs = document.querySelectorAll(".login-tab");
    const loginRoleInput = document.getElementById("login_role");
    
    // Role tab selection
    loginTabs.forEach(tab => {
        tab.addEventListener("click", function() {
            // Remove active class from all tabs
            loginTabs.forEach(t => t.classList.remove("active"));
            
            // Add active class to clicked tab
            this.classList.add("active");
            
            // Update hidden input value
            loginRoleInput.value = this.dataset.role;
        });
    });

    // Show/hide password and change icon
    pwShowHide.forEach(eyeIcon => {
        eyeIcon.addEventListener("click", () => {
            pwFields.forEach(pwField => {
                if (pwField.type === "password") {
                    pwField.type = "text";
                    pwShowHide.forEach(icon => {
                        icon.classList.replace("uil-eye-slash", "uil-eye");
                    });
                } else {
                    pwField.type = "password";
                    pwShowHide.forEach(icon => {
                        icon.classList.replace("uil-eye", "uil-eye-slash");
                    });
                }
            });
        });
    });

    // Switch between sign up and login forms
    signUp.addEventListener("click", (e) => {
        e.preventDefault();
        container.classList.add("active");
    });

    login.addEventListener("click", (e) => {
        e.preventDefault();
        container.classList.remove("active");
    });

    // Show/hide role note based on selection
    if (roleSelect) {
        roleSelect.addEventListener("change", function() {
            if (this.value === "teacher" || this.value === "admin") {
                roleNoteContainer.style.display = "block";
            } else {
                roleNoteContainer.style.display = "none";
            }
        });
    }

    // Handle Ajax form submissions if JavaScript is enabled
    if (loginForm) {
        loginForm.addEventListener("submit", function(e) {
            // Only prevent default if we're using Ajax
            if (window.fetch) {
                e.preventDefault();
                
                const formData = new FormData(loginForm);
                
                fetch(window.location.href, {
                    method: "POST",
                    body: formData,
                    headers: {
                        "X-Requested-With": "XMLHttpRequest"
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Redirect based on role
                        switch (data.role) {
                            case 'admin':
                                window.location.href = "admin/dashboard.php";
                                break;
                            case 'teacher':
                                window.location.href = "teacher/dashboard.php";
                                break;
                            case 'student':
                            default:
                                window.location.href = "student/dashboard.php";
                                break;
                        }
                    } else {
                        loginError.innerHTML = data.errors.join("<br>");
                        loginError.style.display = "block";
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    loginError.innerHTML = "An error occurred. Please try again.";
                    loginError.style.display = "block";
                });
            }
        });
    }

    if (signupForm) {
        signupForm.addEventListener("submit", function(e) {
            // Only prevent default if we're using Ajax
            if (window.fetch) {
                e.preventDefault();
                
                const formData = new FormData(signupForm);
                
                // Password validation
                const password = formData.get("password");
                const confirmPassword = formData.get("confirm_password");
                
                if (password !== confirmPassword) {
                    signupError.innerHTML = "Passwords do not match";
                    signupError.style.display = "block";
                    return;
                }
                
                fetch(window.location.href, {
                    method: "POST",
                    body: formData,
                    headers: {
                        "X-Requested-With": "XMLHttpRequest"
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success message and reset form
                        signupForm.innerHTML = `
                            <div class="success-message">
                                ${data.message}
                                <p>You can now <a href="#" class="login-link">login</a> with your credentials.</p>
                            </div>
                        `;
                        
                        // Add event listener to the new login link
                        document.querySelector('.login-link').addEventListener('click', (e) => {
                            e.preventDefault();
                            container.classList.remove("active");
                            
                            // Set the correct tab active based on registration role
                            if (data.role) {
                                loginTabs.forEach(tab => {
                                    if (tab.dataset.role === data.role) {
                                        tab.click();
                                    }
                                });
                            }
                        });
                    } else {
                        signupError.innerHTML = data.errors.join("<br>");
                        signupError.style.display = "block";
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    signupError.innerHTML = "An error occurred. Please try again.";
                    signupError.style.display = "block";
                });
            }
        });
    }     
  
 
    </script>
</body>
</html>