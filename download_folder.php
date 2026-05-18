<?php
require_once 'auth.php';
restrictApiAccess();

$folderPath = $_POST['folderPath'] ?? '';
if (!$folderPath) {
    die("Folder path is required.");
}

$folderPath = str_replace(['..', '\\'], ['', '/'], $folderPath);
if (!is_dir($folderPath)) {
    die("Thư mục không tồn tại.");
}

$folderName = basename($folderPath);
$zipFileName = $folderName . '.zip';
$zipFilePath = sys_get_temp_dir() . '/' . uniqid() . '_' . $zipFileName;

$zip = new ZipArchive();
if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    die("Không thể tạo file zip.");
}

function addFolderToZip($dir, $zipArchive, $zipDir = '') {
    if (is_dir($dir)) {
        if ($dh = opendir($dir)) {
            while (($file = readdir($dh)) !== false) {
                if ($file == '.' || $file == '..') continue;
                $filePath = $dir . '/' . $file;
                $localPath = $zipDir ? $zipDir . '/' . $file : $file;
                
                if (is_file($filePath)) {
                    $zipArchive->addFile($filePath, $localPath);
                } elseif (is_dir($filePath)) {
                    $zipArchive->addEmptyDir($localPath);
                    addFolderToZip($filePath, $zipArchive, $localPath);
                }
            }
            closedir($dh);
        }
    }
}

addFolderToZip($folderPath, $zip);
$zip->close();

if (file_exists($zipFilePath)) {
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipFileName . '"');
    header('Content-Length: ' . filesize($zipFilePath));
    readfile($zipFilePath);
    @unlink($zipFilePath);
    exit;
} else {
    die("Lỗi trong quá trình tạo file zip.");
}
