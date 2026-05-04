<?php
require_once 'auth.php';
restrictApiAccess();
header('Content-Type: application/json');

$config = include(__DIR__ . '/config.php');
$tableName = $_GET['tableName'] ?? '';

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

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
        throw new Exception('Invalid table name');
    }

    $stmt = $pdo->query("SELECT * FROM `{$tableName}` ORDER BY `level` ASC, `position` ASC, `id` ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        echo json_encode(['sql' => '-- Bảng trống hoặc không có dữ liệu --']);
        exit;
    }

    $hasDataColumn = array_key_exists('data', $rows[0]);
    if ($hasDataColumn) {
        $sql = "INSERT INTO `{$tableName}` (`id`, `position`, `parts`, `colorArray`, `quantity`, `level`, `data`) VALUES\n";
    } else {
        $sql = "INSERT INTO `{$tableName}` (`id`, `position`, `parts`, `colorArray`, `quantity`, `level`) VALUES\n";
    }
    
    $values = [];
    foreach ($rows as $row) {
        $id = (int)$row['id'];
        $pos = addslashes($row['position']);
        $parts = addslashes($row['parts']);
        $colors = addslashes($row['colorArray']);
        $qty = (int)$row['quantity'];
        $lvl = (int)$row['level'];
        
        if ($hasDataColumn) {
            $data = addslashes((string)$row['data']);
            $values[] = "({$id}, '{$pos}', '{$parts}', '{$colors}', {$qty}, {$lvl}, '{$data}')";
        } else {
            $values[] = "({$id}, '{$pos}', '{$parts}', '{$colors}', {$qty}, {$lvl})";
        }
    }
    $sql .= implode(",\n", $values) . ";";

    echo json_encode(['sql' => $sql]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
