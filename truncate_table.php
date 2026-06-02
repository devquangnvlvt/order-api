<?php
require_once 'auth.php';
restrictApiAccess();
if (!isAdmin()) {
    http_response_code(403);
    die(json_encode(['error' => 'Forbidden: admin only']));
}
header('Content-Type: application/json');

$config = include(__DIR__ . '/config.php');
$tableName = $_POST['tableName'] ?? '';

if (!$tableName) {
    die(json_encode(['error' => 'Table name is required']));
}

try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Only allow alphanumeric and underscore for table name security
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
        throw new Exception('Invalid table name format');
    }

    // Verify table exists
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$tableName]);
    if ($stmt->fetch()) {
        $pdo->exec("TRUNCATE TABLE `{$tableName}`");
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['error' => 'Table not found']);
    }

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
