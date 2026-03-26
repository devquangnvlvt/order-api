<?php
require_once 'auth.php';
restrictApiAccess();
header('Content-Type: application/json');

$config = include(__DIR__ . '/config.php');
$basePath = $config['upload_path'] ?? '';
$subPath = $_GET['path'] ?? '';

if (!$basePath || !is_dir($basePath)) {
    echo json_encode([]);
    exit;
}

$targetPath = $basePath;
if ($subPath) {
    // Basic sanitization to prevent directory traversal
    $subPath = str_replace(['..', '\\'], ['', '/'], $subPath);
    $targetPath = rtrim($basePath, '/') . '/' . ltrim($subPath, '/');
}

if (!is_dir($targetPath)) {
    echo json_encode([]);
    exit;
}

$folders = array_filter(glob($targetPath . '/*'), 'is_dir');
$folderNames = array_map('basename', $folders);

// Sort alphabetically
sort($folderNames);

echo json_encode($folderNames);
