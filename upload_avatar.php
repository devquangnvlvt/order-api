<?php
require_once 'auth.php';
restrictApiAccess();
if (!isAdmin()) {
    http_response_code(403);
    die(json_encode(['error' => 'Forbidden: admin only']));
}
header('Content-Type: application/json');

$config = include(__DIR__ . '/config.php');
$savePath = $_POST['savePath'] ?? ''; // This is the full path to the position folder
$position = $_POST['position'] ?? '';

if (!$savePath || !$position) {
    die(json_encode(['error' => 'Missing path or position']));
}

if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    die(json_encode(['error' => 'No file uploaded or upload error']));
}

$file = $_FILES['avatar'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

// Allow only images
if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp'])) {
    die(json_encode(['error' => 'Only PNG, JPG, JPEG, and WEBP files are allowed']));
}

$cleanSavePath = rtrim(str_replace(['..', '\\'], ['', '/'], $savePath), '/');
$targetFile = $cleanSavePath . '/avatar.png';

// Ensure the directory exists
if (!is_dir($cleanSavePath)) {
    if (!mkdir($cleanSavePath, 0777, true)) {
        die(json_encode(['error' => 'Could not create directory: ' . $cleanSavePath]));
    }
}

if (move_uploaded_file($file['tmp_name'], $targetFile)) {
    echo json_encode(['status' => 'success', 'url' => 'avatar.png?v=' . time()]);
} else {
    echo json_encode(['error' => 'Failed to save file to ' . $targetFile]);
}
