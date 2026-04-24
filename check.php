<?php
require_once 'auth.php';
restrictApiAccess();
header('Content-Type: application/json');

$config = include(__DIR__ . '/config.php');

$tableName = $_GET['tableName'] ?? '';
$savePath = $_GET['savePath'] ?? '';
$folderName = $_GET['folderName'] ?? '';

$response = [
    'tableExists' => false,
    'folderExists' => false,
    'positions' => []
];

try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // 1. Check if table exists
    if ($tableName) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
            throw new Exception('Tên bảng không hợp lệ.');
        }
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$tableName]);
        if ($stmt->fetch()) {
            $response['tableExists'] = true;
            // Get unique positions and their levels from the table
            $stmtPos = $pdo->query("SELECT `position`, MIN(`level`) as level FROM `{$tableName}` GROUP BY `position` ORDER BY `level` ASC, `position` ASC");
            $positions = $stmtPos->fetchAll(PDO::FETCH_ASSOC);

            // Check for avatar.png for each position
            if ($savePath) {
                $cleanSavePath = rtrim(str_replace(['..', '\\'], ['', '/'], $savePath), '/');
                foreach ($positions as &$item) {
                    $avatarPath = $cleanSavePath . '/' . $item['position'] . '/avatar.png';
                    if (file_exists($avatarPath)) {
                        $item['hasAvatar'] = true;
                    } else {
                        $item['hasAvatar'] = false;
                    }
                }
            }
            $response['positions'] = $positions;
        }
    }

    // 2. Check if folder exists
    if ($savePath && $folderName) {
        $cleanSavePath = rtrim(str_replace(['..', '\\'], ['', '/'], $savePath), '/');
        $targetDir = $cleanSavePath . '/' . $folderName;
        if (is_dir($targetDir)) {
            $response['folderExists'] = true;
        }
    }

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
