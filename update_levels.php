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
$positions = $_POST['positions'] ?? []; // Array of position names in new order

if (!$tableName) {
    die(json_encode(['error' => 'Table name is required']));
}

if (empty($positions)) {
    die(json_encode(['error' => 'No positions provided']));
}

try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
        throw new Exception('Invalid table name');
    }

    $pdo->beginTransaction();

    $level = 1;
    $stmt = $pdo->prepare("UPDATE `{$tableName}` SET `level` = ? WHERE `position` = ?");

    foreach ($positions as $posName) {
        $stmt->execute([$level, $posName]);
        $level++;
    }

    $pdo->commit();

    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    if (isset($pdo)) $pdo->rollBack();
    echo json_encode(['error' => $e->getMessage()]);
}
