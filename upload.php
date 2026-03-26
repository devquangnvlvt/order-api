<?php
require_once 'auth.php';
restrictApiAccess();
header('Content-Type: application/json');

$config = include(__DIR__ . '/config.php');
$tempBaseDir = __DIR__ . '/uploads/temp';
$defaultFinalBaseDir = $config['upload_path'] ?? (__DIR__ . '/uploads/final');

// Get parameters
$resumableIdentifier = $_POST['resumableIdentifier'] ?? $_GET['resumableIdentifier'] ?? '';
$resumableFilename = $_POST['resumableFilename'] ?? $_GET['resumableFilename'] ?? '';
$resumableChunkNumber = $_POST['resumableChunkNumber'] ?? $_GET['resumableChunkNumber'] ?? '';
$resumableTotalChunks = $_POST['resumableTotalChunks'] ?? $_GET['resumableTotalChunks'] ?? '';
$resumableRelativePath = $_POST['resumableRelativePath'] ?? $_GET['resumableRelativePath'] ?? '';
$savePath = $_POST['savePath'] ?? $_GET['savePath'] ?? $defaultFinalBaseDir;

// Basic sanitization
$savePath = str_replace(['..', '\\'], ['', '/'], $savePath);
$finalBaseDir = rtrim($savePath, '/');

if (!is_dir($tempBaseDir)) mkdir($tempBaseDir, 0777, true);
if (!is_dir($finalBaseDir)) mkdir($finalBaseDir, 0777, true);

if (!$resumableIdentifier || !$resumableFilename || !$resumableChunkNumber) {
    header("HTTP/1.0 400 Bad Request");
    die(json_encode(['error' => 'Missing parameters']));
}

// Security: Filter out system/thumb files
$lowerName = strtolower($resumableFilename);
if (strpos($lowerName, 'thumbs.db') !== false || strpos($lowerName, 'desktop.ini') !== false) {
    header("HTTP/1.0 400 Bad Request");
    die(json_encode(['error' => 'Skipping system files']));
}

$safeIdentifier = preg_replace('/[^a-zA-Z0-9_-]/', '', $resumableIdentifier);
$chunkDir = $tempBaseDir . '/' . $safeIdentifier;

if (!is_dir($chunkDir)) {
    mkdir($chunkDir, 0777, true);
}

$chunkFile = $chunkDir . '/' . basename($resumableFilename) . '.part' . $resumableChunkNumber;

// Handle GET (Check if chunk exists)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (file_exists($chunkFile)) {
        header("HTTP/1.0 200 Ok");
    } else {
        header("HTTP/1.0 404 Not Found");
    }
    exit;
}

// Handle POST (Save chunk)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_FILES)) {
        foreach ($_FILES as $file) {
            if ($file['error'] === 0) {
                if (!move_uploaded_file($file['tmp_name'], $chunkFile)) {
                    die(json_encode(['error' => 'Failed to save chunk']));
                }
            }
        }
    }

    // Check if all chunks are uploaded
    $chunksFound = 0;
    for ($i = 1; $i <= $resumableTotalChunks; $i++) {
        if (file_exists($chunkDir . '/' . basename($resumableFilename) . '.part' . $i)) {
            $chunksFound++;
        }
    }

    if ($chunksFound == $resumableTotalChunks) {
        // All chunks are here, assemble them
        $finalPath = $finalBaseDir . '/' . $resumableRelativePath;
        // Normalize slashes for Windows/Linux compatibility
        $finalPath = str_replace('\\', '/', $finalPath);
        $finalDir = dirname($finalPath);
        
        if (!is_dir($finalDir)) {
            mkdir($finalDir, 0777, true);
        }

        $fp = fopen($finalPath, 'w');
        if (flock($fp, LOCK_EX)) {
            for ($i = 1; $i <= $resumableTotalChunks; $i++) {
                $chunkPath = $chunkDir . '/' . basename($resumableFilename) . '.part' . $i;
                $chunkFileIdx = fopen($chunkPath, 'rb');
                stream_copy_to_stream($chunkFileIdx, $fp);
                fclose($chunkFileIdx);
                @unlink($chunkPath); // Delete chunk
            }
            flock($fp, LOCK_UN);
        }
        fclose($fp);
        @rmdir($chunkDir); // Delete temp dir

        echo json_encode(['status' => 'complete', 'path' => $resumableRelativePath]);
    } else {
        echo json_encode(['status' => 'uploading', 'chunk' => $resumableChunkNumber]);
    }
}
