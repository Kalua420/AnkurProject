<?php
session_start();
include('../db_connect.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Function to handle errors with better messaging
function handle_error($message, $redirect = "../index.php") {
    $_SESSION['error'] = $message;
    header("Location: $redirect");
    exit();
}

// Check if ID and type are set with better validation
if (empty($_GET['id']) || empty($_GET['type'])) {
    handle_error("Invalid request. Document ID and type are required.", "index.php");
}

// Validate and sanitize inputs
$id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
if ($id === false || $id <= 0) {
    handle_error("Invalid document ID provided.", "index.php");
}

$type = trim($_GET['type']);
if (empty($type)) {
    handle_error("Document type cannot be empty.", "index.php");
}

// Validate file type
$allowed_types = ['question_papers', 'assignments', 'papers'];
if (!in_array($type, $allowed_types)) {
    handle_error("Invalid document type requested. Allowed types: " . implode(', ', $allowed_types), "index.php");
}

// Get file path from database based on type
$sql = "SELECT * FROM `$type` WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    handle_error("Database error: " . mysqli_error($conn), "index.php");
}

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    handle_error("Document not found or access denied.", "index.php");
}

$row = mysqli_fetch_assoc($result);

// Get file path
$file_path = $row['file_path'];

if (empty($file_path)) {
    handle_error("File path not found in database.", "index.php");
}

// Enhanced file existence check
$final_file_path = null;

// Check original path
if (file_exists($file_path)) {
    $final_file_path = $file_path;
} else {
    // Try with relative path
    $relative_path = str_replace($_SERVER['DOCUMENT_ROOT'], '', $file_path);
    $server_path = $_SERVER['DOCUMENT_ROOT'] . $relative_path;
    
    if (file_exists($server_path)) {
        $final_file_path = $server_path;
    } else {
        // Try looking in uploads directory based on type
        $base_filename = basename($file_path);
        $type_folder = ($type == 'question_papers') ? 'question_papers' : $type;
        $search_paths = [
            "../uploads/$type_folder/$base_filename",
            "../uploads/$base_filename",
            "uploads/$type_folder/$base_filename",
            "uploads/$base_filename"
        ];
        
        foreach ($search_paths as $search_path) {
            if (file_exists($search_path)) {
                $final_file_path = $search_path;
                break;
            }
        }
    }
}

// If file still not found
if (!$final_file_path || !file_exists($final_file_path)) {
    handle_error("File not found on server. Please contact administrator. (File: " . basename($file_path) . ")", "index.php");
}

// Verify file is readable
if (!is_readable($final_file_path)) {
    handle_error("File exists but cannot be read. Please contact administrator.", "index.php");
}

// Get file information
$file_info = pathinfo($final_file_path);
$file_name = $file_info['basename'];
$file_ext = strtolower($file_info['extension']);
$file_size = filesize($final_file_path);

// Set appropriate content type based on file extension
$content_types = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'txt' => 'text/plain',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif'
];

$content_type = isset($content_types[$file_ext]) ? $content_types[$file_ext] : 'application/octet-stream';

// Security check - prevent access to certain file types
$blocked_extensions = ['php', 'exe', 'bat', 'sh', 'cmd'];
if (in_array($file_ext, $blocked_extensions)) {
    handle_error("File type not allowed for viewing.", "index.php");
}

// Update view count if available
if ($type == 'question_papers' && isset($row['view_count'])) {
    $update_sql = "UPDATE question_papers SET view_count = view_count + 1 WHERE id = ?";
    $update_stmt = mysqli_prepare($conn, $update_sql);
    if ($update_stmt) {
        mysqli_stmt_bind_param($update_stmt, "i", $id);
        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);
    }
}

// Clear any output buffer
if (ob_get_level()) {
    ob_end_clean();
}

// For PDF files, we want to display them inline in the browser
if ($file_ext == 'pdf') {
    // Set headers to display PDF in browser
    header('Content-Type: ' . $content_type);
    header('Content-Disposition: inline; filename="' . $file_name . '"');
    header('Content-Length: ' . $file_size);
    header('Accept-Ranges: bytes');
    header('Cache-Control: public, max-age=3600');
    header('Pragma: public');
    
    // Prevent script timeout for large files
    set_time_limit(0);
    
    // Output file content
    if (!readfile($final_file_path)) {
        handle_error("Error reading file. Please try again.", "index.php");
    }
} else {
    // For non-PDF files, create a simple HTML viewer
    $document_title = isset($row['title']) ? htmlspecialchars($row['title']) : 'Document Viewer';
    $base_url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
    
    // Convert file path to web-accessible URL
    $web_file_path = str_replace($_SERVER['DOCUMENT_ROOT'], '', $final_file_path);
    $file_url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $web_file_path;
    
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $document_title; ?> - Document Viewer</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background-color: #f5f5f5;
                color: #333;
            }
            
            .viewer-header {
                background: white;
                padding: 15px 20px;
                border-bottom: 1px solid #ddd;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                position: sticky;
                top: 0;
                z-index: 1000;
            }
            
            .header-content {
                max-width: 1200px;
                margin: 0 auto;
                display: flex;
                justify-content: space-between;
                align-items: center;
                flex-wrap: wrap;
                gap: 15px;
            }
            
            .document-info h1 {
                font-size: 20px;
                color: #333;
                margin-bottom: 5px;
            }
            
            .document-meta {
                font-size: 14px;
                color: #666;
                display: flex;
                gap: 20px;
                flex-wrap: wrap;
            }
            
            .document-meta span {
                display: flex;
                align-items: center;
                gap: 5px;
            }
            
            .header-actions {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
            }
            
            .btn {
                padding: 8px 16px;
                border-radius: 6px;
                text-decoration: none;
                font-weight: 500;
                font-size: 14px;
                display: inline-flex;
                align-items: center;
                gap: 6px;
                transition: all 0.3s ease;
                border: none;
                cursor: pointer;
            }
            
            .btn-primary {
                background: #4361ee;
                color: white;
            }
            
            .btn-primary:hover {
                background: #3a56d4;
                transform: translateY(-2px);
            }
            
            .btn-secondary {
                background: #6c757d;
                color: white;
            }
            
            .btn-secondary:hover {
                background: #5a6268;
                transform: translateY(-2px);
            }
            
            .btn-success {
                background: #06d6a0;
                color: white;
            }
            
            .btn-success:hover {
                background: #05b386;
                transform: translateY(-2px);
            }
            
            .viewer-container {
                max-width: 1200px;
                margin: 20px auto;
                padding: 0 20px;
            }
            
            .document-viewer {
                background: white;
                border-radius: 8px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                overflow: hidden;
                min-height: 600px;
            }
            
            .file-preview {
                width: 100%;
                height: 80vh;
                min-height: 600px;
                border: none;
                background: #f8f9fa;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-direction: column;
                color: #666;
            }
            
            .file-icon {
                font-size: 48px;
                margin-bottom: 15px;
                opacity: 0.7;
            }
            
            .file-info {
                text-align: center;
            }
            
            .file-info h3 {
                margin-bottom: 10px;
                color: #333;
            }
            
            .file-info p {
                margin-bottom: 20px;
                color: #666;
            }
            
            .download-hint {
                background: #e3f2fd;
                border: 1px solid #90caf9;
                border-radius: 6px;
                padding: 15px;
                margin: 20px;
                text-align: center;
                color: #1565c0;
            }
            
            .error-message {
                background: #ffebee;
                border: 1px solid #ffcdd2;
                border-radius: 6px;
                padding: 15px;
                margin: 20px;
                text-align: center;
                color: #c62828;
            }
            
            @media (max-width: 768px) {
                .header-content {
                    flex-direction: column;
                    align-items: flex-start;
                }
                
                .document-meta {
                    flex-direction: column;
                    gap: 8px;
                }
                
                .header-actions {
                    width: 100%;
                    justify-content: flex-start;
                }
                
                .viewer-container {
                    padding: 0 10px;
                }
                
                .file-preview {
                    height: 60vh;
                    min-height: 400px;
                }
            }
        </style>
    </head>
    <body>
        <div class="viewer-header">
            <div class="header-content">
                <div class="document-info">
                    <h1><i class="fas fa-file-alt"></i> <?php echo $document_title; ?></h1>
                    <div class="document-meta">
                        <?php if (isset($row['department'])): ?>
                        <span><i class="fas fa-building"></i> <?php echo htmlspecialchars($row['department']); ?></span>
                        <?php endif; ?>
                        
                        <?php if (isset($row['subject'])): ?>
                        <span><i class="fas fa-book"></i> <?php echo htmlspecialchars($row['subject']); ?></span>
                        <?php endif; ?>
                        
                        <?php if (isset($row['paper_year'])): ?>
                        <span><i class="fas fa-calendar"></i> <?php echo htmlspecialchars($row['paper_year']); ?></span>
                        <?php endif; ?>
                        
                        <span><i class="fas fa-file"></i> <?php echo strtoupper($file_ext); ?> File</span>
                        <span><i class="fas fa-weight"></i> <?php echo formatBytes($file_size); ?></span>
                    </div>
                </div>
                
                <div class="header-actions">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <a href="download.php?id=<?php echo $id; ?>&type=<?php echo $type; ?>" class="btn btn-success">
                        <i class="fas fa-download"></i> Download
                    </a>
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            </div>
        </div>
        
        <div class="viewer-container">
            <div class="document-viewer">
                <?php if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                    <!-- Image Preview -->
                    <img src="<?php echo htmlspecialchars($file_url); ?>" 
                         alt="<?php echo htmlspecialchars($file_name); ?>" 
                         style="width: 100%; height: auto; display: block;">
                         
                <?php elseif ($file_ext == 'txt'): ?>
                    <!-- Text File Preview -->
                    <div style="padding: 20px; font-family: monospace; line-height: 1.6; white-space: pre-wrap; overflow-x: auto;">
                        <?php 
                        $text_content = file_get_contents($final_file_path);
                        echo htmlspecialchars($text_content); 
                        ?>
                    </div>
                    
                <?php else: ?>
                    <!-- Generic File Preview -->
                    <div class="file-preview">
                        <div class="file-icon">
                            <?php 
                            $icon_map = [
                                'doc' => 'fas fa-file-word',
                                'docx' => 'fas fa-file-word',
                                'xls' => 'fas fa-file-excel',
                                'xlsx' => 'fas fa-file-excel',
                                'ppt' => 'fas fa-file-powerpoint',
                                'pptx' => 'fas fa-file-powerpoint',
                                'zip' => 'fas fa-file-archive',
                                'rar' => 'fas fa-file-archive',
                                'default' => 'fas fa-file'
                            ];
                            $icon = isset($icon_map[$file_ext]) ? $icon_map[$file_ext] : $icon_map['default'];
                            ?>
                            <i class="<?php echo $icon; ?>"></i>
                        </div>
                        
                        <div class="file-info">
                            <h3><?php echo htmlspecialchars($file_name); ?></h3>
                            <p>File Type: <?php echo strtoupper($file_ext); ?> | Size: <?php echo formatBytes($file_size); ?></p>
                            
                            <div class="download-hint">
                                <i class="fas fa-info-circle"></i>
                                This file type cannot be previewed in the browser. Please download it to view the content.
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
            // Add some interactive features
            document.addEventListener('DOMContentLoaded', function() {
                // Add keyboard shortcuts
                document.addEventListener('keydown', function(e) {
                    // Ctrl+D or Cmd+D for download
                    if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
                        e.preventDefault();
                        window.location.href = 'download.php?id=<?php echo $id; ?>&type=<?php echo $type; ?>';
                    }
                    
                    // Escape key to go back
                    if (e.key === 'Escape') {
                        window.history.back();
                    }
                });
                
                // Add loading indicator for images
                const images = document.querySelectorAll('img');
                images.forEach(img => {
                    img.addEventListener('load', function() {
                        this.style.opacity = '1';
                    });
                    
                    img.addEventListener('error', function() {
                        this.parentElement.innerHTML = `
                            <div class="error-message">
                                <i class="fas fa-exclamation-triangle"></i>
                                Failed to load image. The file might be corrupted or moved.
                            </div>
                        `;
                    });
                    
                    img.style.opacity = '0';
                    img.style.transition = 'opacity 0.3s ease';
                });
            });
        </script>
    </body>
    </html>
    <?php
}

// Clean up
mysqli_stmt_close($stmt);
mysqli_close($conn);

// Helper function to format file sizes
function formatBytes($size, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $size >= 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    
    return round($size, $precision) . ' ' . $units[$i];
}
?>