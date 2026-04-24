<?php
require_once 'auth.php';
restrictApiAccess();
header('Content-Type: application/json');

$config = include(__DIR__ . '/config.php');

// Get the target table name and save path from POST
$tableName = $_POST['tableName'] ?? '';
$savePath = $_POST['savePath'] ?? '';

if (!$tableName) {
    die(json_encode(['error' => 'Table name is required']));
}
if (!$savePath) {
    die(json_encode(['error' => 'Save path is required']));
}

$finalBaseDir = rtrim(str_replace(['..', '\\'], ['', '/'], $savePath), '/');

set_time_limit(0); 
ini_set('memory_limit', '512M');

try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
        throw new Exception('Tên bảng không hợp lệ.');
    }

    // 1. Create table if not exists
    $sqlCreate = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `position` VARCHAR(255),
        `parts` VARCHAR(255),
        `colorArray` TEXT,
        `quantity` INT,
        `level` INT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sqlCreate);

    // 2. Scan folders
    $results = [];
    $level = 0;

    // Get the first level of directories (Positions)
    $positions = array_filter(glob($finalBaseDir . '/*'), function($dir) {
        return is_dir($dir) && strtolower(basename($dir)) !== 'thumbs';
    });
    sort($positions);

    foreach ($positions as $posPath) {
        $level++;
        $posName = basename($posPath);

        // Get the second level of directories (Parts)
        $parts = array_filter(glob($posPath . '/*'), function($dir) {
            return is_dir($dir) && strtolower(basename($dir)) !== 'thumbs';
        });
        sort($parts);

        foreach ($parts as $partPath) {
            $partName = basename($partPath);
            $colorArray = [];
            $quantity = 0;

            // Check for subdirectories (Color folders)
            $colors = array_filter(glob($partPath . '/*'), function($dir) {
                return is_dir($dir) && strtolower(basename($dir)) !== 'thumbs';
            });
            sort($colors);

            if (!empty($colors)) {
                // Case: Has subfolders
                foreach ($colors as $colorPath) {
                    $colorArray[] = basename($colorPath);
                }

                // Count images in the FIRST color subfolder (as per Batch logic)
                $firstColorPath = $colors[0];
                $images = glob($firstColorPath . '/*.{png,svg,webp,jpg,jpeg,PNG,SVG,WEBP,JPG,JPEG}', GLOB_BRACE);
                foreach ($images as $image) {
                    $imgName = strtolower(basename($image));
                    if (!in_array($imgName, ['nav.png', 'nav.svg', 'nav.webp']) && !str_starts_with($imgName, 'thumb_')) {
                        $quantity++;
                    }
                }
            } else {
                // Case: No subfolders, count images in the part folder
                $images = glob($partPath . '/*.{png,svg,webp,jpg,jpeg,PNG,SVG,WEBP,JPG,JPEG}', GLOB_BRACE);
                foreach ($images as $image) {
                    $imgName = strtolower(basename($image));
                    if (!in_array($imgName, ['nav.png', 'nav.svg', 'nav.webp']) && !str_starts_with($imgName, 'thumb_')) {
                        $quantity++;
                    }
                }
            }

            $results[] = [
                'position' => $posName,
                'parts' => $partName,
                'colorArray' => implode(',', $colorArray),
                'quantity' => $quantity,
                'level' => $level
            ];
        }
    }

    // 3. Clean existing records for these positions (Overwrite logic)
    if (!empty($results)) {
        $positionsToClean = array_unique(array_column($results, 'position'));
        $placeholdersClean = implode(',', array_fill(0, count($positionsToClean), '?'));
        $sqlDelete = "DELETE FROM `{$tableName}` WHERE `position` IN ($placeholdersClean)";
        $stmtDelete = $pdo->prepare($sqlDelete);
        $stmtDelete->execute(array_values($positionsToClean));
    }

    // 4. Insert into database
    if (!empty($results)) {
        $sqlInsert = "INSERT INTO `{$tableName}` (`position`, `parts`, `colorArray`, `quantity`, `level`) VALUES ";
        $placeholders = [];
        $values = [];

        foreach ($results as $row) {
            $placeholders[] = "(?, ?, ?, ?, ?)";
            $values[] = $row['position'];
            $values[] = $row['parts'];
            $values[] = $row['colorArray'];
            $values[] = $row['quantity'];
            $values[] = $row['level'];
        }

        $sqlInsert .= implode(', ', $placeholders);
        $stmt = $pdo->prepare($sqlInsert);
        $stmt->execute($values);
    }

    echo json_encode([
        'status' => 'success',
        'inserted_count' => count($results),
        'table' => $tableName
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
