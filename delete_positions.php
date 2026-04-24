<?php
require_once 'auth.php';
restrictApiAccess();
header('Content-Type: application/json');

$config = include(__DIR__ . '/config.php');
$tableName = $_POST['tableName'] ?? '';
$positions = $_POST['positions'] ?? []; // Can be a single string or an array

if (!$tableName) {
    die(json_encode(['error' => 'Table name is required']));
}

if (empty($positions)) {
    die(json_encode(['error' => 'No positions specified for deletion']));
}

if (!is_array($positions)) {
    $positions = [$positions];
}

try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
        throw new Exception('Invalid table name format');
    }

    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$tableName]);
    if ($stmt->fetch()) {
        $placeholders = implode(',', array_fill(0, count($positions), '?'));
        $sql = "DELETE FROM `{$tableName}` WHERE `position` IN ($placeholders)";
        $stmtDelete = $pdo->prepare($sql);
        $stmtDelete->execute($positions);

        echo json_encode([
            'status' => 'success',
            'deleted_count' => $stmtDelete->rowCount()
        ]);
    } else {
        echo json_encode(['error' => 'Table not found']);
    }

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
