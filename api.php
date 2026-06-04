<?php
require_once 'auth.php';
restrictApiAccess();
header('Content-Type: application/json');
header('Cache-Control: no-cache');

$config = include(__DIR__ . '/config.php');

// ==================== SECURITY HELPERS ====================
function safe_join($base, $sub) {
    $base = rtrim(str_replace('\\', '/', realpath($base) ?: $base), '/');
    $sub  = ltrim(str_replace('\\', '/', $sub), '/');
    $full = realpath($base . '/' . $sub);
    if ($full === false) {
        // Path may not exist yet, build it manually
        $full = $base . '/' . $sub;
    }
    $full = str_replace('\\', '/', $full);
    if (strpos($full . '/', $base . '/') !== 0) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Security violation: path traversal detected']));
    }
    return $full;
}

function validate_id($id) {
    return preg_match('/^[a-zA-Z0-9_\-\.\/]+$/', $id);
}

function natural_sort_cmp($a, $b) {
    return strnatcasecmp($a, $b);
}

// ==================== INPUT PARSING ====================
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$savePath = isset($_GET['savePath']) ? $_GET['savePath'] : ($_POST['savePath'] ?? '');
$tableName = isset($_GET['tableName']) ? $_GET['tableName'] : ($_POST['tableName'] ?? '');
$position  = isset($_GET['position']) ? $_GET['position'] : ($_POST['position'] ?? '');

// Parse JSON body if content-type is application/json
$jsonBody = [];
$rawInput = file_get_contents('php://input');
if ($rawInput && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $jsonBody = json_decode($rawInput, true) ?? [];
}

function jp($key, $default = null) {
    global $jsonBody, $_GET, $_POST;
    return $jsonBody[$key] ?? $_POST[$key] ?? $_GET[$key] ?? $default;
}

// ==================== DB CONNECTION ====================
function getDB() {
    global $config;
    return new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}

// ==================== STATIC FILE PROXY ====================
// Serve files from savePath via /api.php?action=file&savePath=...&kit=...&path=...
if ($action === 'file') {
    $kit     = jp('kit');
    $kit     = explode('?', $kit)[0]; // Strip version query nếu có
    $relPath = jp('path', '');
    // Strip version query string nếu có (VD: "5-4/thumb_10.webp?v=123" → "5-4/thumb_10.webp")
    $relPath = explode('?', $relPath)[0];
    $relPath = ltrim(str_replace(['..', '\\'], ['', '/'], $relPath), '/');
    if (!$savePath || !$kit) { http_response_code(400); exit; }
    $baseDir  = rtrim(str_replace('\\', '/', $savePath), '/') . '/' . $kit;
    $fullPath = $baseDir . '/' . $relPath;

    // Nếu không tìm thấy, thử thêm /data vào savePath
    if (!file_exists($fullPath)) {
        $baseDirData = rtrim(str_replace('\\', '/', $savePath), '/') . '/data/' . $kit;
        $fullPathData = $baseDirData . '/' . $relPath;
        if (file_exists($fullPathData)) {
            $baseDir  = $baseDirData;
            $fullPath = $fullPathData;
        }
    }
    // Security: must stay within baseDir
    $realBase = str_replace('\\', '/', realpath($baseDir) ?: $baseDir);
    $realFull = str_replace('\\', '/', realpath($fullPath) ?: $fullPath);
    if (strpos($realFull . '/', $realBase . '/') !== 0) { http_response_code(403); exit; }
    if (!file_exists($fullPath) || !is_file($fullPath)) { http_response_code(404); exit; }
    $mime = mime_content_type($fullPath) ?: 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($fullPath));
    header('Cache-Control: public, max-age=3600');
    readfile($fullPath);
    exit;
}

// ==================== ROUTE DISPATCHER ====================
// Các action chỉ dành cho admin
$adminOnlyActions = [
    'rename_folder', 'delete_part', 'merge_layers', 'create_thumb',
    'auto_create_thumbs', 'delete_all_thumbs', 'delete_file', 'rename_file',
    'flatten_colors', 'batch_delete_reorder', 'reorder_images', 'rename_color_folder',
    'delete_color_folders', 'upload_file', 'crop_batch_thumbs', 'create_nav',
    'reorder_parts', 'batch_merge_layers', 'fix_color_code', 'fix_all_part_colors',
    'fix_colors_by_point', 'generate_bg_json',
];
if (in_array($action, $adminOnlyActions) && !isAdmin()) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Forbidden: chỉ admin mới có quyền thực hiện thao tác này']));
}

try {
    switch ($action) {
        case 'get_kits_list':   handleGetKitsList();   break;
        case 'get_kit_structure': handleGetKitStructure(); break;
        case 'rename_folder':   handleRenameFolder();  break;
        case 'delete_part':     handleDeletePart();    break;
        case 'list_part_images': handleListPartImages(); break;
        case 'merge_layers':    handleMergeLayers();   break;
        case 'create_thumb':    handleCreateThumb();   break;
        case 'auto_create_thumbs': handleAutoCreateThumbs(); break;
        case 'delete_all_thumbs': handleDeleteAllThumbs(); break;
        case 'delete_file':     handleDeleteFile();    break;
        case 'rename_file':     handleRenameFile();    break;
        case 'flatten_colors':  handleFlattenColors(); break;
        case 'batch_delete_reorder': handleBatchDeleteReorder(); break;
        case 'reorder_images':  handleReorderImages(); break;
        case 'rename_color_folder': handleRenameColorFolder(); break;
        case 'delete_color_folders': handleDeleteColorFolders(); break;
        case 'upload_file':     handleUploadFile();    break;
        case 'crop_batch_thumbs': handleCropBatchThumbs(); break;
        case 'create_nav':      handleCreateNav();     break;
        case 'reorder_parts':   handleReorderParts();  break;
        case 'debug_folder_files': handleDebugFolderFiles(); break;
        case 'batch_merge_layers': handleBatchMergeLayers(); break;
        case 'get_item_layers': handleGetItemLayers(); break;
        case 'fix_color_code':  handleFixColorCode();  break;
        case 'fix_all_part_colors': handleFixAllPartColors(); break;
        case 'fix_colors_by_point': handleFixColorsByPoint(); break;
        case 'generate_bg_json': handleGenerateBgJson(); break;
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

// ==================== HANDLER FUNCTIONS ====================

function getKitBasePath() {
    global $savePath, $position;
    if (!$savePath) throw new Exception('Missing savePath');
    return rtrim(str_replace('\\', '/', $savePath), '/');
}

function handleGetKitsList() {
    $base = getKitBasePath();
    global $position;
    $kits = [];

    // Single position mode: list parts inside that position folder
    if ($position) {
        $posPath = $base . '/' . $position;
        if (is_dir($posPath)) {
            $entries = array_diff(scandir($posPath), ['.', '..']);
            usort($entries, 'natural_sort_cmp');
            foreach ($entries as $entry) {
                if (is_dir($posPath . '/' . $entry)) {
                    $kits[] = ['id' => $entry, 'name' => $entry, 'folder' => $position . '/' . $entry, 'parent' => $position];
                }
            }
        }
        echo json_encode(['success' => true, 'kits' => $kits, 'parents' => [$position]]);
        return;
    }

    // List all positions as kits (top-level dirs)
    if (!is_dir($base)) {
        echo json_encode(['success' => true, 'kits' => [], 'parents' => []]);
        return;
    }
    $entries = array_diff(scandir($base), ['.', '..']);
    usort($entries, 'natural_sort_cmp');
    foreach ($entries as $entry) {
        $fullPath = $base . '/' . $entry;
        if (!is_dir($fullPath)) continue;
        $baseName = strtolower($entry);
        if (in_array($baseName, ['thumbs', 'bg', 'cache_blobs'])) continue;
        $kits[] = ['id' => $entry, 'name' => $entry, 'folder' => $entry, 'parent' => 'Mặc định'];
    }
    echo json_encode(['success' => true, 'kits' => $kits, 'parents' => []]);
}

function handleGetKitStructure() {
    $kitFolder = jp('kit');
    if (!$kitFolder) { echo json_encode(['success' => false, 'message' => 'Missing kit']); return; }

    $base    = getKitBasePath();
    $kitPath = safe_join($base, $kitFolder);

    // Nếu không tìm thấy, thử thêm /data vào base (asset/data/3)
    if (!is_dir($kitPath)) {
        $baseData = rtrim($base, '/') . '/data';
        $kitPathData = safe_join($baseData, $kitFolder);
        if (is_dir($kitPathData)) {
            $base    = $baseData;
            $kitPath = $kitPathData;
        }
    }

    if (!is_dir($kitPath)) {
        echo json_encode(['success' => false, 'message' => 'Kit not found']);
        return;
    }

    $parts = [];
    $entries = array_diff(scandir($kitPath), ['.', '..']);
    foreach ($entries as $entry) {
        $entryPath = $kitPath . '/' . $entry;
        if (!is_dir($entryPath)) continue;

        preg_match('/^(\d+)-(\d+)(?:-(.*))?$/', $entry, $match);
        $x = $match ? (int)$match[1] : 9999;
        $y = $match ? (int)$match[2] : (count($parts) + 1);

        // Scan colors & images
        $colors = [];
        $imageIndices = [];
        $itemIndices  = [];
        $thumbPattern = '/^thumb_(\d+)\.(png|webp)$/';
        $imgPattern   = '/^(\d+)\.(png|webp)$/';
        $colorGaps    = [];
        $colorImgCounts = [];

        $subItems = array_diff(scandir($entryPath), ['.', '..']);
        foreach ($subItems as $sf) {
            $sfPath = $entryPath . '/' . $sf;
            if (is_dir($sfPath)) {
                $colors[] = $sf;
            } elseif (is_file($sfPath)) {
                if (preg_match($thumbPattern, $sf, $m)) {
                    $itemIndices[] = (int)$m[1];
                }
                if (preg_match($imgPattern, $sf, $m)) {
                    $imageIndices[] = (int)$m[1];
                }
            }
        }

        if ($colors) {
            foreach ($colors as $sub) {
                $subPath = $entryPath . '/' . $sub;
                $subIdxs = [];
                foreach (array_diff(scandir($subPath), ['.', '..']) as $sf) {
                    if (preg_match($imgPattern, $sf, $m)) $subIdxs[] = (int)$m[1];
                }
                $colorImgCounts[$sub] = count($subIdxs);
                if ($subIdxs) {
                    $gaps = array_diff(range(1, max($subIdxs)), $subIdxs);
                    if ($gaps) $colorGaps[$sub] = array_values($gaps);
                }
            }
        }

        $numItems = max(
            !empty($imageIndices) ? max($imageIndices) : 0,
            !empty($itemIndices)  ? max($itemIndices)  : 0
        );

        $missingImages = [];
        if (!$colors && $imageIndices) {
            $maxImg = max($imageIndices);
            for ($i = 1; $i <= $maxImg; $i++) {
                if (!in_array($i, $imageIndices)) $missingImages[] = $i;
            }
        }

        $parts[] = [
            'x' => $x, 'y' => $y,
            'folder' => $entry,
            'display_name' => $entry,
            'items_count' => $numItems,
            'colors' => $colors,
            'is_separated' => false,
            'has_colors' => !empty($colors),
            'missing_images' => $missingImages,
            'color_gaps' => $colorGaps,
            'color_image_counts' => $colorImgCounts,
            'item_layer_counts' => [],
        ];
    }

    usort($parts, fn($a, $b) => $a['y'] - $b['y']);

    // Check continuity
    $foundX = array_filter(array_column($parts, 'x'), fn($v) => $v !== 9999);
    $foundY = array_filter(array_column($parts, 'y'), fn($v) => $v !== 9999);
    $missingX = $foundX ? array_values(array_diff(range(1, max($foundX)), $foundX)) : [];
    $missingY = $foundY ? array_values(array_diff(range(1, max($foundY)), $foundY)) : [];

    echo json_encode([
        'success' => true,
        'parts' => $parts,
        'api_version' => 'cf-v1',
        'has_separated_layers' => false,
        'separated_folders' => [],
        'duplicates' => [],
        'missing_x' => $missingX,
        'missing_y' => $missingY,
        'canvas_width' => 1436,
        'canvas_height' => 1902,
    ]);
}

function handleRenameFolder() {
    $kitFolder = jp('kit');
    $oldName   = jp('old_name');
    $newName   = jp('new_name');
    if (!$kitFolder || !$oldName || !$newName) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']); return;
    }
    if (!preg_match('/^\d+-\d+(?:-.*)?$/', $newName)) {
        echo json_encode(['success' => false, 'message' => 'Tên mới phải đúng định dạng X-Y']); return;
    }
    $base    = getKitBasePath();
    $kitPath = safe_join($base, $kitFolder);
    $oldPath = safe_join($kitPath, $oldName);
    $newPath = safe_join($kitPath, $newName);
    if (!is_dir($oldPath)) { echo json_encode(['success' => false, 'message' => 'Folder not found']); return; }
    if (is_dir($newPath))  { echo json_encode(['success' => false, 'message' => 'New folder name already exists']); return; }
    rename($oldPath, $newPath);
    // DB: cập nhật tên cột parts khi đổi tên folder bộ phận
    global $tableName;
    if ($tableName && preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
        try {
            $pdo = getDB();
            $chk = $pdo->prepare("SHOW TABLES LIKE ?"); $chk->execute([$tableName]);
            if ($chk->fetch()) {
                $upd = $pdo->prepare("UPDATE `{$tableName}` SET `parts` = ? WHERE `position` = ? AND `parts` = ?");
                $upd->execute([$newName, $kitFolder, $oldName]);
            }
        } catch (Exception $e) { error_log('[CF_API] renameFolder DB: ' . $e->getMessage()); }
    }
    echo json_encode(['success' => true, 'message' => 'Renamed successfully']);
}

function handleDeletePart() {
    $kitFolder = jp('kit');
    $yIndex    = (int)jp('y');
    if (!$kitFolder || !$yIndex) { echo json_encode(['success' => false, 'message' => 'Missing parameters']); return; }

    $base    = getKitBasePath();
    $kitPath = safe_join($base, $kitFolder);
    if (!is_dir($kitPath)) { echo json_encode(['success' => false, 'message' => 'Kit not found']); return; }

    $entries = array_diff(scandir($kitPath), ['.', '..']);
    $targetX = null;
    $deletedPartName = null;
    foreach ($entries as $entry) {
        if (!is_dir($kitPath . '/' . $entry)) continue;
        if (preg_match('/^(\d+)-(\d+)(?:-.*)?$/', $entry, $m)) {
            if ((int)$m[2] === $yIndex) {
                $targetX = (int)$m[1];
                $deletedPartName = $entry;
                rrmdir($kitPath . '/' . $entry);
            }
        }
    }
    if ($targetX === null) {
        echo json_encode(['success' => false, 'message' => "Không tìm thấy folder Y={$yIndex}"]); return;
    }

    // DB: xóa bản ghi bộ phận đã bị xóa
    global $tableName;
    if ($deletedPartName && $tableName && preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
        try {
            $pdo = getDB();
            $chk = $pdo->prepare("SHOW TABLES LIKE ?"); $chk->execute([$tableName]);
            if ($chk->fetch()) {
                $del = $pdo->prepare("DELETE FROM `{$tableName}` WHERE `position` = ? AND `parts` = ?");
                $del->execute([$kitFolder, $deletedPartName]);
            }
        } catch (Exception $e) { error_log('[CF_API] deletePart DB: ' . $e->getMessage()); }
    }

    // Re-index
    $entries = array_diff(scandir($kitPath), ['.', '..']);
    $toRename = [];
    foreach ($entries as $entry) {
        if (!is_dir($kitPath . '/' . $entry)) continue;
        if (preg_match('/^(\d+)-(\d+)(?:-(.*))?$/', $entry, $m)) {
            $ex = (int)$m[1]; $ey = (int)$m[2]; $suf = $m[3] ?? '';
            if ($ex > $targetX || $ey > $yIndex) {
                $toRename[] = ['old' => $entry, 'nx' => $ex > $targetX ? $ex - 1 : $ex, 'ny' => $ey > $yIndex ? $ey - 1 : $ey, 'suf' => $suf];
            }
        }
    }
    usort($toRename, fn($a, $b) => $a['ny'] - $b['ny']);
    $pdoReindex = null;
    if ($tableName && preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
        try { $pdoReindex = getDB(); } catch (Exception $e) {}
    }
    foreach ($toRename as $r) {
        $nn = $r['nx'] . '-' . $r['ny'] . ($r['suf'] ? '-' . $r['suf'] : '');
        @rename($kitPath . '/' . $r['old'], $kitPath . '/' . $nn);
        // DB: cập nhật tên bộ phận sau re-index
        if ($pdoReindex) {
            try {
                $upd = $pdoReindex->prepare("UPDATE `{$tableName}` SET `parts` = ? WHERE `position` = ? AND `parts` = ?");
                $upd->execute([$nn, $kitFolder, $r['old']]);
            } catch (Exception $e) { error_log('[CF_API] reindex DB: ' . $e->getMessage()); }
        }
    }
    echo json_encode(['success' => true, 'message' => 'Deleted and re-indexed']);
}

function handleListPartImages() {
    $kitFolder   = jp('kit');
    $folderName  = jp('folder');
    $color       = jp('color');
    if (!$kitFolder || !$folderName) { echo json_encode(['success' => false, 'message' => 'Missing parameters']); return; }

    $base    = getKitBasePath();
    $kitPath = safe_join($base, $kitFolder);
    $partPath = safe_join($kitPath, $folderName);
    $targetDir = ($color && $color !== 'default') ? safe_join($partPath, $color) : $partPath;
    if (!is_dir($targetDir)) { echo json_encode(['success' => false, 'message' => 'Directory not found']); return; }

    global $savePath;
    $images = [];
    $files = array_diff(scandir($targetDir), ['.', '..']);
    foreach ($files as $f) {
        if (!is_file($targetDir . '/' . $f)) continue;
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (!in_array($ext, ['png', 'webp', 'jpg', 'jpeg'])) continue;
        $bl = strtolower($f);
        if ($bl === 'nav.png' || $bl === 'nav.webp' || str_starts_with($bl, 'thumb_')) continue;
        $relPath = ltrim(str_replace(rtrim(str_replace('\\','/',$savePath),'/'), '', str_replace('\\','/',$targetDir . '/' . $f)), '/');
        $images[] = [
            'filename' => $f,
            'url' => 'api.php?action=file&savePath=' . urlencode($savePath) . '&kit=' . urlencode($kitFolder) . '&path=' . urlencode($folderName . ($color && $color !== 'default' ? '/' . $color : '') . '/' . $f),
        ];
    }
    usort($images, fn($a,$b) => strnatcasecmp($a['filename'], $b['filename']));
    echo json_encode(['success' => true, 'images' => $images]);
}

function handleDebugFolderFiles() {
    $kitFolder  = jp('kit');
    $folderName = jp('folder');
    $color      = jp('color');
    if (!$kitFolder || !$folderName) { echo json_encode(['success' => false, 'message' => 'Missing parameters']); return; }

    $base      = getKitBasePath();
    $kitPath   = safe_join($base, $kitFolder);
    $partPath  = safe_join($kitPath, $folderName);
    $targetDir = ($color && $color !== 'default') ? safe_join($partPath, $color) : $partPath;

    if (!is_dir($targetDir)) { echo json_encode(['success' => false, 'message' => 'Directory not found']); return; }

    global $savePath;
    $files = [];
    $entries = array_diff(scandir($targetDir), ['.', '..']);
    foreach ($entries as $f) {
        if (!is_file($targetDir . '/' . $f)) continue;
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        $isImg = in_array($ext, ['png', 'webp', 'jpg', 'jpeg', 'gif']);
        $relPart = $folderName . ($color && $color !== 'default' ? '/' . $color : '') . '/' . $f;
        $files[] = [
            'name'   => $f,
            'url'    => 'api.php?action=file&savePath=' . urlencode($savePath) . '&kit=' . urlencode($kitFolder) . '&path=' . urlencode($relPart),
            'is_image' => $isImg,
            'location' => $color ? 'Color/Sub' : 'Main',
        ];
    }
    echo json_encode(['success' => true, 'files' => $files]);
}

function handleDeleteFile() {
    $kitFolder  = jp('kit');
    $folderName = jp('folder');
    $filename   = jp('filename');
    $color      = jp('color');
    if (!$kitFolder || !$folderName || !$filename) { echo json_encode(['success' => false, 'message' => 'Missing parameters']); return; }
    if (strpbrk($filename, '/\\') !== false) { echo json_encode(['success' => false, 'message' => 'Invalid filename']); return; }

    $base      = getKitBasePath();
    $kitPath   = safe_join($base, $kitFolder);
    $partPath  = safe_join($kitPath, $folderName);
    $targetDir = ($color && $color !== 'default') ? safe_join($partPath, $color) : $partPath;
    $filePath  = safe_join($targetDir, $filename);

    if (!file_exists($filePath)) { echo json_encode(['success' => false, 'message' => 'File not found']); return; }
    unlink($filePath);
    // DB sync nếu là ảnh thực (không phải nav/thumb)
    $bl = strtolower($filename);
    if (preg_match('/\.(png|webp|jpg|jpeg)$/i', $filename) && !str_starts_with($bl, 'thumb_') && !in_array($bl, ['nav.png','nav.webp'])) {
        syncPartMetadataToDB($kitFolder, $folderName);
    }
    echo json_encode(['success' => true, 'message' => 'Deleted']);
}

function handleRenameFile() {
    $kitFolder  = jp('kit');
    $folderName = jp('folder');
    $oldName    = jp('old_name');
    $newName    = jp('new_name');
    $color      = jp('color');
    if (!$kitFolder || !$folderName || !$oldName || !$newName) { echo json_encode(['success' => false, 'message' => 'Missing parameters']); return; }
    if (strpbrk($oldName, '/\\') !== false || strpbrk($newName, '/\\') !== false) { echo json_encode(['success' => false, 'message' => 'Invalid filename']); return; }

    $base      = getKitBasePath();
    $kitPath   = safe_join($base, $kitFolder);
    $partPath  = safe_join($kitPath, $folderName);
    $targetDir = ($color && $color !== 'default') ? safe_join($partPath, $color) : $partPath;
    $oldPath   = safe_join($targetDir, $oldName);
    $newPath   = safe_join($targetDir, $newName);
    if (!file_exists($oldPath)) { echo json_encode(['success' => false, 'message' => 'File not found']); return; }
    rename($oldPath, $newPath);
    echo json_encode(['success' => true, 'message' => 'Renamed']);
}

function handleUploadFile() {
    $kitFolder  = jp('kit');
    $folderName = jp('folder');
    $filename   = jp('filename', 'nav.png');
    $fileContent = jp('file_content');
    $color      = jp('color');
    if (!$kitFolder || !$folderName || !$fileContent) { echo json_encode(['success' => false, 'message' => 'Missing parameters']); return; }
    if (strpbrk($filename, '/\\') !== false) { echo json_encode(['success' => false, 'message' => 'Invalid filename']); return; }

    $base      = getKitBasePath();
    $kitPath   = safe_join($base, $kitFolder);
    $partPath  = safe_join($kitPath, $folderName);
    $isNav     = str_starts_with(strtolower($filename), 'nav.');
    $targetDir = (!$isNav && $color && $color !== 'default') ? safe_join($partPath, $color) : $partPath;
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

    $filePath = safe_join($targetDir, $filename);
    if (str_contains($fileContent, ',')) $fileContent = explode(',', $fileContent)[1];
    file_put_contents($filePath, base64_decode($fileContent));
    // DB sync nếu là ảnh (không phải nav)
    if (!$isNav && preg_match('/\.(png|webp|jpg|jpeg)$/i', $filename)) {
        syncPartMetadataToDB($kitFolder, $folderName);
    }
    echo json_encode(['success' => true, 'message' => "Uploaded {$filename}"]);
}

function handleFlattenColors() {
    $kitFolder  = jp('kit');
    $folderName = jp('folder');
    if (!$kitFolder || !$folderName) { echo json_encode(['success' => false, 'message' => 'Missing parameters']); return; }

    $base      = getKitBasePath();
    $kitPath   = safe_join($base, $kitFolder);
    $partPath  = safe_join($kitPath, $folderName);
    if (!is_dir($partPath)) { echo json_encode(['success' => false, 'message' => 'Folder not found']); return; }

    $subDirs = [];
    foreach (array_diff(scandir($partPath), ['.','..']) as $e) {
        if (is_dir($partPath . '/' . $e)) $subDirs[] = $e;
    }
    if (!$subDirs) { echo json_encode(['success' => false, 'message' => 'No color folders found']); return; }

    $imgPattern = '/\.(png|webp|jpg|jpeg)$/i';
    foreach ($subDirs as $sub) {
        $subPath = $partPath . '/' . $sub;
        foreach (array_diff(scandir($subPath), ['.','..']) as $f) {
            $src = $subPath . '/' . $f;
            if (!is_file($src) || !preg_match($imgPattern, $f)) continue;
            $dst = $partPath . '/' . $f;
            if (!file_exists($dst)) rename($src, $dst);
        }
        rrmdir($subPath);
    }
    syncPartMetadataToDB($kitFolder, $folderName);
    echo json_encode(['success' => true, 'message' => 'Flattened successfully']);
}

function handleBatchDeleteReorder() {
    $kitFolder  = jp('kit');
    $folderName = jp('folder');
    $indices    = jp('indices', []);
    $applyAll   = jp('apply_all', true);
    $color      = jp('color');
    if (!$kitFolder || !$folderName || !$indices) { echo json_encode(['success' => false, 'message' => 'Missing parameters']); return; }

    $base      = getKitBasePath();
    $kitPath   = safe_join($base, $kitFolder);
    $partPath  = safe_join($kitPath, $folderName);

    $imgPattern = '/^(\d+)\.(png|webp)$/';
    $thumbPat   = '/^thumb_(\d+)\.(png|webp)$/';
    $idxSet     = array_flip($indices);

    // Determine target dirs
    $targetDirs = [];
    if ($applyAll) {
        foreach (array_diff(scandir($partPath), ['.','..']) as $e) {
            if (is_dir($partPath . '/' . $e)) $targetDirs[] = $partPath . '/' . $e;
        }
        if (empty($targetDirs)) $targetDirs = [$partPath];
    } else {
        $targetDirs = [$color && $color !== 'default' ? safe_join($partPath, $color) : $partPath];
    }

    foreach ($targetDirs as $dir) {
        $files = [];
        foreach (array_diff(scandir($dir), ['.','..']) as $f) {
            if (!is_file($dir . '/' . $f)) continue;
            if (preg_match($imgPattern, $f, $m)) $files[(int)$m[1]] = $f;
        }
        ksort($files);
        // Delete selected
        foreach ($idxSet as $idx => $_) {
            if (isset($files[$idx])) {
                unlink($dir . '/' . $files[$idx]);
                // Also delete thumb
                foreach (['png','webp'] as $ext) {
                    $tp = $dir . '/thumb_' . $idx . '.' . $ext; // from parent
                    if (file_exists($tp)) unlink($tp);
                }
                unset($files[$idx]);
            }
        }
        // Also delete thumbs from parent dir for selected indices
        foreach ($idxSet as $idx => $_) {
            foreach (['png','webp'] as $ext) {
                $tp = $partPath . '/thumb_' . $idx . '.' . $ext;
                if (file_exists($tp)) unlink($tp);
            }
        }
        // Reorder
        $remaining = array_values($files);
        $tmpPrefix = 'reorder_tmp_' . time() . '_';
        $tmps = [];
        foreach ($remaining as $i => $fn) {
            $tmp = $tmpPrefix . $i . '.' . pathinfo($fn, PATHINFO_EXTENSION);
            rename($dir . '/' . $fn, $dir . '/' . $tmp);
            $tmps[] = [$tmp, pathinfo($fn, PATHINFO_EXTENSION)];
        }
        foreach ($tmps as $i => [$tmp, $ext]) {
            rename($dir . '/' . $tmp, $dir . '/' . ($i + 1) . '.' . $ext);
        }
    }
    syncPartMetadataToDB($kitFolder, $folderName);
    echo json_encode(['success' => true, 'message' => 'Deleted and reordered']);
}

function handleReorderImages() {
    $kitFolder  = jp('kit');
    $partFolder = jp('part_folder');
    if (!$kitFolder || !$partFolder) { echo json_encode(['success' => false, 'message' => 'Missing parameters']); return; }

    $base      = getKitBasePath();
    $kitPath   = safe_join($base, $kitFolder);
    $partPath  = safe_join($kitPath, $partFolder);

    $subDirs = [];
    foreach (array_diff(scandir($partPath), ['.','..']) as $e) {
        if (is_dir($partPath . '/' . $e)) $subDirs[] = $e;
    }
    $targetDirs = $subDirs ? array_map(fn($s) => $partPath . '/' . $s, $subDirs) : [$partPath];

    $imgExts = ['png', 'webp', 'jpg', 'jpeg'];
    $imgPat  = '/^(\d+)\.(png|webp|jpg|jpeg)$/i';

    foreach ($targetDirs as $dir) {
        $files = [];
        foreach (array_diff(scandir($dir), ['.','..']) as $f) {
            if (!is_file($dir . '/' . $f)) continue;
            $bl = strtolower($f);
            if (str_starts_with($bl, 'thumb_') || in_array($bl, ['nav.png','nav.webp'])) continue;
            if (preg_match($imgPat, $f)) $files[] = $f;
        }
        usort($files, 'strnatcasecmp');
        $tmpPrefix = 'reorder_tmp_' . time() . '_';
        $tmps = [];
        foreach ($files as $i => $fn) {
            $tmp = $tmpPrefix . $i . '.' . pathinfo($fn, PATHINFO_EXTENSION);
            rename($dir . '/' . $fn, $dir . '/' . $tmp);
            $tmps[] = [$tmp, pathinfo($fn, PATHINFO_EXTENSION)];
        }
        foreach ($tmps as $i => [$tmp, $ext]) {
            rename($dir . '/' . $tmp, $dir . '/' . ($i + 1) . '.' . $ext);
        }
    }
    syncPartMetadataToDB($kitFolder, $partFolder);
    echo json_encode(['success' => true, 'message' => 'Reordered']);
}

function handleRenameColorFolder() {
    $kitFolder  = jp('kit');
    $folderName = jp('folder');
    $oldColor   = jp('old_color');
    $newColor   = jp('new_color');
    if (!$kitFolder || !$folderName || !$oldColor || !$newColor) { echo json_encode(['success' => false, 'message' => 'Missing parameters']); return; }

    $base      = getKitBasePath();
    $kitPath   = safe_join($base, $kitFolder);
    $partPath  = safe_join($kitPath, $folderName);
    $oldPath   = safe_join($partPath, $oldColor);
    $newPath   = safe_join($partPath, $newColor);
    if (!is_dir($oldPath)) { echo json_encode(['success' => false, 'message' => 'Color folder not found']); return; }
    if (is_dir($newPath))  { echo json_encode(['success' => false, 'message' => 'New color name already exists']); return; }
    rename($oldPath, $newPath);
    syncPartMetadataToDB($kitFolder, $folderName);
    echo json_encode(['success' => true, 'message' => 'Color folder renamed']);
}

function handleDeleteColorFolders() {
    $kitFolder  = jp('kit');
    $folderName = jp('folder');
    $colors     = jp('colors', []);
    if (!$kitFolder || !$folderName) { echo json_encode(['success' => false, 'message' => 'Missing parameters']); return; }

    $base      = getKitBasePath();
    $kitPath   = safe_join($base, $kitFolder);
    $partPath  = safe_join($kitPath, $folderName);

    foreach ($colors as $c) {
        $cp = safe_join($partPath, $c);
        if (is_dir($cp)) rrmdir($cp);
    }
    syncPartMetadataToDB($kitFolder, $folderName);
    echo json_encode(['success' => true, 'message' => 'Color folders deleted']);
}

function handleReorderParts() {
    $kitFolder = jp('kit');
    $order     = jp('order', []);
    if (!$kitFolder || !$order) { echo json_encode(['success' => false, 'message' => 'Missing parameters']); return; }

    $base    = getKitBasePath();
    $kitPath = safe_join($base, $kitFolder);

    // order = array of folder names in new Y order
    $tmpBase = 'rp_tmp_' . time() . '_';
    foreach ($order as $i => $folder) {
        if (preg_match('/^(\d+)-(\d+)((?:-.*)?)$/', $folder, $m)) {
            $tmp = $tmpBase . $i . $m[3];
            @rename($kitPath . '/' . $folder, $kitPath . '/' . $tmp);
        }
    }
    // Now rename from tmp to new names
    foreach ($order as $i => $folder) {
        if (preg_match('/^(\d+)-(\d+)((?:-.*)?)$/', $folder, $m)) {
            $tmp = $tmpBase . $i . $m[3];
            $newName = $m[1] . '-' . ($i + 1) . $m[3];
            if (is_dir($kitPath . '/' . $tmp)) {
                rename($kitPath . '/' . $tmp, $kitPath . '/' . $newName);
            }
        }
    }
    // DB: cập nhật tên bộ phận sau khi sắp xếp lại thứ tự
    global $tableName;
    if ($tableName && preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
        try {
            $pdo = getDB();
            $chk = $pdo->prepare("SHOW TABLES LIKE ?"); $chk->execute([$tableName]);
            if ($chk->fetch()) {
                foreach ($order as $i => $folder) {
                    if (preg_match('/^(\d+)-(\d+)((?:-.*)?)$/', $folder, $m)) {
                        $newName = $m[1] . '-' . ($i + 1) . $m[3];
                        if ($newName !== $folder) {
                            $upd = $pdo->prepare("UPDATE `{$tableName}` SET `parts` = ? WHERE `position` = ? AND `parts` = ?");
                            $upd->execute([$newName, $kitFolder, $folder]);
                        }
                    }
                }
            }
        } catch (Exception $e) { error_log('[CF_API] reorderParts DB: ' . $e->getMessage()); }
    }
    echo json_encode(['success' => true, 'message' => 'Reordered parts']);
}

function handleCreateThumb() {
    $kitFolder  = jp('kit');
    $folderName = jp('folder');
    $sourceFile = jp('source_file');
    $targetFile = jp('target_file');
    $color      = jp('color');
    if (!$kitFolder || !$folderName || !$sourceFile || !$targetFile) { echo json_encode(['success' => false, 'message' => 'Missing parameters']); return; }

    $base      = getKitBasePath();
    $kitPath   = safe_join($base, $kitFolder);
    $partPath  = safe_join($kitPath, $folderName);
    $srcDir    = ($color && $color !== 'default') ? safe_join($partPath, $color) : $partPath;
    $srcPath   = safe_join($srcDir, $sourceFile);
    $dstPath   = safe_join($partPath, $targetFile); // thumbs always in parent

    if (!file_exists($srcPath)) { echo json_encode(['success' => false, 'message' => 'Source file not found']); return; }

    if (!createThumbFrom($srcPath, $dstPath, 44, 44)) {
        echo json_encode(['success' => false, 'message' => 'Failed to create thumbnail']); return;
    }
    echo json_encode(['success' => true, 'message' => 'Thumbnail created']);
}

function handleAutoCreateThumbs() {
    $kitFolder  = jp('kit');
    $folderName = jp('folder');
    $color      = jp('color');
    if (!$kitFolder) { echo json_encode(['success' => false, 'message' => 'Missing kit']); return; }

    $base    = getKitBasePath();
    $kitPath = safe_join($base, $kitFolder);

    $imgPat = '/^(\d+)\.(png|webp|jpg|jpeg)$/i';
    $totalCreated = 0;
    $totalSkipped = 0;
    $details = [];

    // Nếu có folder cụ thể → chỉ tạo cho folder đó
    // Nếu không → scan toàn bộ sub-folder trong kit
    $foldersToProcess = [];
    if ($folderName) {
        $foldersToProcess[] = $folderName;
    } else {
        foreach (array_diff(scandir($kitPath), ['.','..']) as $entry) {
            if (is_dir($kitPath . '/' . $entry)) $foldersToProcess[] = $entry;
        }
    }

    foreach ($foldersToProcess as $fn) {
        $partPath = safe_join($kitPath, $fn);
        if (!is_dir($partPath)) continue;

        // Xác định thư mục nguồn ảnh
        $srcDir = ($color && $color !== 'default') ? safe_join($partPath, $color) : $partPath;
        if (!is_dir($srcDir)) continue;

        $created = 0; $skipped = 0;
        foreach (array_diff(scandir($srcDir), ['.','..']) as $f) {
            if (!is_file($srcDir . '/' . $f) || !preg_match($imgPat, $f, $m)) continue;
            $idx = $m[1];
            $dst = safe_join($partPath, "thumb_{$idx}.png");
            if (file_exists($dst)) { $skipped++; continue; }
            if (createThumbFrom($srcDir . '/' . $f, $dst, 44, 44)) $created++;
        }
        $totalCreated += $created;
        $totalSkipped += $skipped;
        if ($created > 0) $details[] = ['folder' => $fn, 'created' => $created];
    }

    echo json_encode([
        'success' => true,
        'message' => "Created {$totalCreated} thumbnails, skipped {$totalSkipped}",
        'stats'   => [
            'total_folders' => count($foldersToProcess),
            'total_images'  => $totalCreated + $totalSkipped,
            'created_thumbs'=> $totalCreated,
            'skipped_thumbs'=> $totalSkipped,
            'details'       => $details,
        ],
    ]);
}

function handleDeleteAllThumbs() {
    $kitFolder  = jp('kit');
    $folderName = jp('folder');
    if (!$kitFolder) { echo json_encode(['success' => false, 'message' => 'Missing kit']); return; }

    $base    = getKitBasePath();
    $kitPath = safe_join($base, $kitFolder);

    $totalDeleted = 0;

    // Nếu có folder cụ thể → xóa thumb trong folder đó
    // Nếu không → xóa thumb trong toàn bộ sub-folder
    $foldersToProcess = [];
    if ($folderName) {
        $foldersToProcess[] = $folderName;
    } else {
        foreach (array_diff(scandir($kitPath), ['.','..']) as $entry) {
            if (is_dir($kitPath . '/' . $entry)) $foldersToProcess[] = $entry;
        }
    }

    foreach ($foldersToProcess as $fn) {
        $partPath = safe_join($kitPath, $fn);
        if (!is_dir($partPath)) continue;
        foreach (array_diff(scandir($partPath), ['.','..']) as $f) {
            if (preg_match('/^thumb_\d+\.(png|webp)$/i', $f)) {
                unlink($partPath . '/' . $f);
                $totalDeleted++;
            }
        }
    }

    echo json_encode(['success' => true, 'message' => "Deleted {$totalDeleted} thumbnails"]);
}

function handleCropBatchThumbs() {
    $kitFolder  = jp('kit');
    $folderName = jp('folder');
    $color      = jp('color');
    $itemNo     = jp('item_no');
    // JS gửi: x, y, width, height
    $cropX      = (int)(jp('x') ?? jp('crop_x', 0));
    $cropY      = (int)(jp('y') ?? jp('crop_y', 0));
    $cropW      = (int)(jp('width') ?? jp('crop_w', 44));
    $cropH      = (int)(jp('height') ?? jp('crop_h', 44));
    $thumbW     = (int)jp('thumb_w', $cropW);
    $thumbH     = (int)jp('thumb_h', $cropH);
    if (!$kitFolder || !$folderName) { echo json_encode(['success' => false, 'message' => 'Missing parameters']); return; }
    if ($cropW <= 0 || $cropH <= 0) { echo json_encode(['success' => false, 'message' => 'Invalid crop dimensions']); return; }

    $base      = getKitBasePath();
    $kitPath   = safe_join($base, $kitFolder);
    $partPath  = safe_join($kitPath, $folderName);
    $srcDir    = ($color && $color !== 'default') ? safe_join($partPath, $color) : $partPath;

    $imgPat = '/^(\d+)\.(png|webp|jpg|jpeg)$/i';
    $count  = 0;

    // Nếu có item_no cụ thể → chỉ tạo cho ảnh đó
    if ($itemNo) {
        foreach (['png','webp','jpg','jpeg'] as $ext) {
            $src = $srcDir . '/' . $itemNo . '.' . $ext;
            if (file_exists($src)) {
                $dst = safe_join($partPath, "thumb_{$itemNo}.png");
                if (createCroppedThumb($src, $dst, $cropX, $cropY, $cropW, $cropH, $thumbW, $thumbH)) $count++;
                break;
            }
        }
    } else {
        // Tạo cho tất cả ảnh trong folder
        foreach (array_diff(scandir($srcDir), ['.','..']) as $f) {
            if (!is_file($srcDir . '/' . $f) || !preg_match($imgPat, $f, $m)) continue;
            $idx  = $m[1];
            $src  = $srcDir . '/' . $f;
            $dst  = safe_join($partPath, "thumb_{$idx}.png");
            if (createCroppedThumb($src, $dst, $cropX, $cropY, $cropW, $cropH, $thumbW, $thumbH)) $count++;
        }
    }
    echo json_encode(['success' => true, 'message' => "Created {$count} cropped thumbnails"]);
}

function handleCreateNav() {
    $kitFolder  = jp('kit');
    $folderName = jp('folder');
    $color      = jp('color');
    if (!$kitFolder || !$folderName) { echo json_encode(['success' => false, 'message' => 'Missing parameters']); return; }

    $base      = getKitBasePath();
    $kitPath   = safe_join($base, $kitFolder);
    $partPath  = safe_join($kitPath, $folderName);
    $srcDir    = ($color && $color !== 'default') ? safe_join($partPath, $color) : $partPath;

    // Find first image
    $imgPat = '/^\d+\.(png|webp|jpg|jpeg)$/i';
    $firstFile = null;
    $files = array_diff(scandir($srcDir), ['.','..']);
    usort($files, 'strnatcasecmp');
    foreach ($files as $f) {
        if (is_file($srcDir . '/' . $f) && preg_match($imgPat, $f)) { $firstFile = $f; break; }
    }
    if (!$firstFile) { echo json_encode(['success' => false, 'message' => 'No source image found']); return; }

    $src = $srcDir . '/' . $firstFile;
    $dst = safe_join($partPath, 'nav.png');
    if (!createThumbFrom($src, $dst, 44, 44)) {
        echo json_encode(['success' => false, 'message' => 'Failed to create nav image']); return;
    }
    echo json_encode(['success' => true, 'message' => 'Nav created']);
}

function handleMergeLayers() {
    $kitFolder  = jp('kit');
    $folderName = jp('folder');
    $layers     = jp('layers', []);    // [{url, color_tint}]
    $outputFile = jp('output_file', '1');
    $color      = jp('color');
    if (!$kitFolder || !$folderName || empty($layers)) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']); return;
    }

    $base      = getKitBasePath();
    $kitPath   = safe_join($base, $kitFolder);
    $partPath  = safe_join($kitPath, $folderName);
    $dstDir    = ($color && $color !== 'default') ? safe_join($partPath, $color) : $partPath;
    if (!is_dir($dstDir)) mkdir($dstDir, 0777, true);

    // Load & merge images using GD
    $canvas = null;
    $canvasW = 1436; $canvasH = 1902;

    foreach ($layers as $layer) {
        $layerPath = resolveLayerPath($layer['url'] ?? '', $kitFolder, $kitPath);
        if (!$layerPath || !file_exists($layerPath)) continue;

        $ext = strtolower(pathinfo($layerPath, PATHINFO_EXTENSION));
        $img = null;
        if ($ext === 'png') $img = @imagecreatefrompng($layerPath);
        elseif ($ext === 'webp') $img = @imagecreatefromwebp($layerPath);
        elseif ($ext === 'jpg' || $ext === 'jpeg') $img = @imagecreatefromjpeg($layerPath);
        if (!$img) continue;

        $iw = imagesx($img); $ih = imagesy($img);
        if (!$canvas) {
            $canvasW = $iw; $canvasH = $ih;
            $canvas = imagecreatetruecolor($canvasW, $canvasH);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefill($canvas, 0, 0, $transparent);
            imagealphablending($canvas, true);
        }

        // Apply color tint if requested
        if (!empty($layer['color_tint'])) {
            $hex = ltrim($layer['color_tint'], '#');
            if (strlen($hex) === 6) {
                $tr = hexdec(substr($hex, 0, 2));
                $tg = hexdec(substr($hex, 2, 2));
                $tb = hexdec(substr($hex, 4, 2));
                applyColorTint($img, $tr, $tg, $tb);
            }
        }

        imagecopy($canvas, $img, 0, 0, 0, 0, $iw, $ih);
        imagedestroy($img);
    }

    if (!$canvas) { echo json_encode(['success' => false, 'message' => 'No valid layers']); return; }

    $ext = 'png';
    $outName = pathinfo($outputFile, PATHINFO_EXTENSION) ? $outputFile : $outputFile . '.png';
    $dstPath = safe_join($dstDir, $outName);
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    imagepng($canvas, $dstPath);
    imagedestroy($canvas);

    echo json_encode(['success' => true, 'message' => "Merged to {$outName}"]);
}

function handleBatchMergeLayers() {
    $tasks = jp('tasks', []);
    if (empty($tasks)) { echo json_encode(['success' => false, 'message' => 'No tasks']); return; }
    $successCount = 0;
    foreach ($tasks as $task) {
        global $jsonBody;
        $savedBody = $jsonBody;
        $jsonBody = $task;
        ob_start();
        handleMergeLayers();
        $out = ob_get_clean();
        $res = json_decode($out, true);
        if ($res && $res['success']) $successCount++;
        $jsonBody = $savedBody;
    }
    echo json_encode(['success' => true, 'message' => "Completed {$successCount}/" . count($tasks) . " tasks"]);
}

// ==================== IMAGE UTILITIES ====================
function createThumbFrom($src, $dst, $w, $h) {
    $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
    $img = null;
    if ($ext === 'png') $img = @imagecreatefrompng($src);
    elseif ($ext === 'webp') $img = @imagecreatefromwebp($src);
    elseif (in_array($ext, ['jpg','jpeg'])) $img = @imagecreatefromjpeg($src);
    if (!$img) return false;

    $iw = imagesx($img); $ih = imagesy($img);
    $thumb = imagecreatetruecolor($w, $h);
    imagealphablending($thumb, false);
    imagesavealpha($thumb, true);
    $transparent = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
    imagefill($thumb, 0, 0, $transparent);
    imagecopyresampled($thumb, $img, 0, 0, 0, 0, $w, $h, $iw, $ih);
    imagedestroy($img);
    $ok = imagepng($thumb, $dst);
    imagedestroy($thumb);
    return $ok;
}

function createCroppedThumb($src, $dst, $cropX, $cropY, $cropW, $cropH, $thumbW, $thumbH) {
    $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
    $img = null;
    if ($ext === 'png') $img = @imagecreatefrompng($src);
    elseif ($ext === 'webp') $img = @imagecreatefromwebp($src);
    elseif (in_array($ext, ['jpg','jpeg'])) $img = @imagecreatefromjpeg($src);
    if (!$img) return false;

    $thumb = imagecreatetruecolor($thumbW, $thumbH);
    imagealphablending($thumb, false);
    imagesavealpha($thumb, true);
    $transparent = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
    imagefill($thumb, 0, 0, $transparent);
    imagecopyresampled($thumb, $img, 0, 0, $cropX, $cropY, $thumbW, $thumbH, $cropW, $cropH);
    imagedestroy($img);
    $ok = imagepng($thumb, $dst);
    imagedestroy($thumb);
    return $ok;
}

function applyColorTint($img, $tr, $tg, $tb) {
    $w = imagesx($img); $h = imagesy($img);
    for ($x = 0; $x < $w; $x++) {
        for ($y = 0; $y < $h; $y++) {
            $rgba = imagecolorat($img, $x, $y);
            $a = ($rgba >> 24) & 0x7F;
            $r = ($rgba >> 16) & 0xFF;
            $g = ($rgba >> 8) & 0xFF;
            $b = $rgba & 0xFF;
            if ($a < 127) {
                $nr = (int)(($r * $tr) / 255);
                $ng = (int)(($g * $tg) / 255);
                $nb = (int)(($b * $tb) / 255);
                $nc = imagecolorallocatealpha($img, $nr, $ng, $nb, $a);
                imagesetpixel($img, $x, $y, $nc);
            }
        }
    }
}

function resolveLayerPath($url, $kitFolder, $kitPath) {
    // url could be api.php?action=file&savePath=...&kit=...&path=...
    // or /downloads/kit/folder/file.png (tool-neka style)
    if (str_contains($url, 'action=file')) {
        parse_str(parse_url($url, PHP_URL_QUERY), $params);
        $relPath = $params['path'] ?? '';
        $relPath = ltrim(str_replace(['..','\\'], ['','/'], $relPath), '/');
        return $kitPath . '/' . $relPath;
    }
    // Fallback: treat as relative to kitPath
    $rel = ltrim(str_replace(['..','\\'], ['','/'], basename($url)), '/');
    return $kitPath . '/' . $rel;
}

function rrmdir($dir) {
    if (!is_dir($dir)) return;
    foreach (array_diff(scandir($dir), ['.','..']) as $f) {
        $p = $dir . '/' . $f;
        is_dir($p) ? rrmdir($p) : unlink($p);
    }
    rmdir($dir);
}

// ==================== DATABASE SYNC HELPER ====================
/**
 * Sau khi thay đổi cấu trúc thư mục, hàm này quét lại và cập nhật
 * cột colorArray + quantity trong bảng MySQL tương ứng.
 */
function syncPartMetadataToDB($kitFolder, $partFolder) {
    global $tableName, $savePath, $position;
    if (!$tableName || !preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) return;

    $base     = rtrim(str_replace('\\', '/', $savePath), '/');
    $kitPath  = safe_join($base, $kitFolder);
    $partPath = safe_join($kitPath, $partFolder);
    if (!is_dir($partPath)) return;

    // Quét folder màu
    $colors = [];
    $qty    = 0;
    $imgPat = '/^\d+\.(png|webp|jpg|jpeg)$/i';

    foreach (array_diff(scandir($partPath), ['.','..']) as $e) {
        $ep = $partPath . '/' . $e;
        if (is_dir($ep)) {
            $ln = strtolower($e);
            if (!in_array($ln, ['thumbs','cache_blobs'])) $colors[] = $e;
        }
    }

    if ($colors) {
        // Đếm ảnh trong folder màu đầu tiên
        $firstColorPath = $partPath . '/' . $colors[0];
        foreach (array_diff(scandir($firstColorPath), ['.','..']) as $f) {
            if (is_file($firstColorPath . '/' . $f) && preg_match($imgPat, $f)) {
                $bl = strtolower($f);
                if (!str_starts_with($bl, 'thumb_') && !in_array($bl, ['nav.png','nav.webp'])) $qty++;
            }
        }
    } else {
        // Đếm ảnh trực tiếp trong partPath
        foreach (array_diff(scandir($partPath), ['.','..']) as $f) {
            if (is_file($partPath . '/' . $f) && preg_match($imgPat, $f)) {
                $bl = strtolower($f);
                if (!str_starts_with($bl, 'thumb_') && !in_array($bl, ['nav.png','nav.webp'])) $qty++;
            }
        }
    }

    $colorArrayStr = implode(',', $colors);

    // Cập nhật DB: tìm hàng theo tableName + position (kit) + parts (partFolder)
    // position trong DB = $kitFolder, parts trong DB = $partFolder
    try {
        $pdo = getDB();
        // Kiểm tra bảng tồn tại
        $check = $pdo->prepare("SHOW TABLES LIKE ?");
        $check->execute([$tableName]);
        if (!$check->fetch()) return;

        $stmt = $pdo->prepare(
            "UPDATE `{$tableName}` SET `colorArray` = ?, `quantity` = ? WHERE `position` = ? AND `parts` = ?"
        );
        $stmt->execute([$colorArrayStr, $qty, $kitFolder, $partFolder]);
    } catch (Exception $e) {
        // Không crash khi DB lỗi - chỉ log
        error_log('[CF_API] syncPartMetadataToDB error: ' . $e->getMessage());
    }
}

// Đồng bộ tất cả bộ phận của một kit vào DB
function syncKitToDB($kitFolder) {
    global $tableName, $savePath;
    if (!$tableName || !preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) return;

    $base    = rtrim(str_replace('\\', '/', $savePath), '/');
    $kitPath = safe_join($base, $kitFolder);
    if (!is_dir($kitPath)) return;

    foreach (array_diff(scandir($kitPath), ['.','..']) as $e) {
        if (is_dir($kitPath . '/' . $e)) {
            syncPartMetadataToDB($kitFolder, $e);
        }
    }
}

// ==================== NEW HANDLER: GET ITEM LAYERS ====================
/**
 * Quét các tệp ảnh cấu thành một item (ví dụ: 1.png, 1_1.png, 1_2.png)
 * và trả về danh sách layers kèm kích thước thực.
 */
function handleGetItemLayers() {
    $kitFolder  = jp('kit');
    $folderName = jp('folder');
    $itemNumber = (int)jp('item_number', 0);
    if (!$kitFolder || !$folderName || !$itemNumber) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']); return;
    }

    $base      = getKitBasePath();
    $kitPath   = safe_join($base, $kitFolder);
    $partPath  = safe_join($kitPath, $folderName);
    if (!is_dir($partPath)) {
        echo json_encode(['success' => false, 'message' => 'Part folder not found']); return;
    }

    global $savePath;
    $layers = [];

    // Tìm tệp ảnh có dạng {itemNumber}.png, {itemNumber}.webp
    $exts = ['png', 'webp', 'jpg', 'jpeg'];

    // Quét thư mục chính (không màu)
    $dirsToScan = [$partPath];
    // Thêm tất cả folder màu
    foreach (array_diff(scandir($partPath), ['.','..']) as $sub) {
        $subP = $partPath . '/' . $sub;
        if (is_dir($subP) && !in_array(strtolower($sub), ['thumbs'])) {
            $dirsToScan[] = $subP;
        }
    }

    // Ưu tiên folder không có màu (flat structure)
    $found = false;
    foreach ($dirsToScan as $scanDir) {
        foreach ($exts as $ext) {
            $fp = $scanDir . '/' . $itemNumber . '.' . $ext;
            if (file_exists($fp)) {
                $size = @getimagesize($fp);
                $relPart = str_replace(rtrim(str_replace('\\','/',$savePath),'/'), '', str_replace('\\','/',$scanDir . '/' . $itemNumber . '.' . $ext));
                $relPart = ltrim($relPart, '/');
                $url = 'api.php?action=file&savePath=' . urlencode($savePath) . '&kit=' . urlencode($kitFolder) . '&path=' . urlencode(ltrim(str_replace(str_replace('\\','/',$kitPath).'/', '', str_replace('\\','/',$fp)), '/'));
                $layers[] = [
                    'filename' => $itemNumber . '.' . $ext,
                    'url'      => $url,
                    'width'    => $size ? $size[0] : null,
                    'height'   => $size ? $size[1] : null,
                ];
                $found = true;
                break 2; // Lấy layer đầu tiên tìm được
            }
        }
    }

    echo json_encode(['success' => true, 'layers' => $layers, 'total_count' => count($layers)]);
}

// ==================== COLOR ANALYSIS HELPERS ====================

/**
 * Phát hiện màu chủ đạo của một tệp ảnh bằng PHP GD.
 * Trả về chuỗi HEX 6 ký tự (uppercase).
 */
function detectDominantColor($filePath) {
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $img = null;
    if ($ext === 'png')  $img = @imagecreatefrompng($filePath);
    elseif ($ext === 'webp') $img = @imagecreatefromwebp($filePath);
    elseif (in_array($ext, ['jpg','jpeg'])) $img = @imagecreatefromjpeg($filePath);
    if (!$img) return null;

    // Thu nhỏ ảnh để tăng tốc
    $sample = imagecreatetruecolor(50, 50);
    imagealphablending($sample, false);
    imagesavealpha($sample, true);
    $trans = imagecolorallocatealpha($sample, 0, 0, 0, 127);
    imagefill($sample, 0, 0, $trans);
    imagecopyresampled($sample, $img, 0, 0, 0, 0, 50, 50, imagesx($img), imagesy($img));
    imagedestroy($img);

    $counts = [];
    for ($x = 0; $x < 50; $x++) {
        for ($y = 0; $y < 50; $y++) {
            $rgba = imagecolorat($sample, $x, $y);
            $a = ($rgba >> 24) & 0x7F;
            if ($a > 100) continue; // Bỏ qua pixel trong suốt
            $r = ($rgba >> 16) & 0xFF;
            $g = ($rgba >> 8)  & 0xFF;
            $b = $rgba & 0xFF;
            // Lượng hóa về 32 bước để nhóm màu tương tự
            $rq = (int)($r / 32) * 32;
            $gq = (int)($g / 32) * 32;
            $bq = (int)($b / 32) * 32;
            $key = sprintf('%02X%02X%02X', min($rq,255), min($gq,255), min($bq,255));
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }
    }
    imagedestroy($sample);

    if (empty($counts)) return null;
    arsort($counts);
    return array_key_first($counts);
}

/**
 * Lấy màu pixel tại tọa độ (x, y) của một tệp ảnh.
 * Trả về chuỗi HEX 6 ký tự (uppercase) hoặc null nếu pixel trong suốt.
 */
function getPixelColorAtPoint($filePath, $px, $py) {
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $img = null;
    if ($ext === 'png')  $img = @imagecreatefrompng($filePath);
    elseif ($ext === 'webp') $img = @imagecreatefromwebp($filePath);
    elseif (in_array($ext, ['jpg','jpeg'])) $img = @imagecreatefromjpeg($filePath);
    if (!$img) return null;

    $w = imagesx($img); $h = imagesy($img);
    if ($px < 0 || $px >= $w || $py < 0 || $py >= $h) { imagedestroy($img); return null; }

    $rgba = imagecolorat($img, $px, $py);
    $a = ($rgba >> 24) & 0x7F;
    $r = ($rgba >> 16) & 0xFF;
    $g = ($rgba >> 8)  & 0xFF;
    $b = $rgba & 0xFF;
    imagedestroy($img);

    if ($a > 100) return null; // Trong suốt
    return sprintf('%02X%02X%02X', $r, $g, $b);
}

// ==================== NEW HANDLER: FIX COLOR CODE ====================
/**
 * Tự động phân tích màu chủ đạo trong folder màu hiện tại
 * và đổi tên thư mục sang mã HEX chuẩn, sau đó đồng bộ DB.
 */
function handleFixColorCode() {
    $kitFolder  = jp('kit');
    $partFolder = jp('part_folder');
    $color      = jp('color');
    if (!$kitFolder || !$partFolder || !$color || $color === 'default') {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']); return;
    }

    $base      = getKitBasePath();
    $kitPath   = safe_join($base, $kitFolder);
    $partPath  = safe_join($kitPath, $partFolder);
    $colorPath = safe_join($partPath, $color);

    if (!is_dir($colorPath)) {
        echo json_encode(['success' => false, 'message' => 'Color folder not found']); return;
    }

    // Tìm ảnh mẫu 1.png trong folder màu
    $sampleFile = null;
    foreach (['1.png','1.webp','1.jpg','1.jpeg'] as $fn) {
        if (file_exists($colorPath . '/' . $fn)) { $sampleFile = $colorPath . '/' . $fn; break; }
    }
    if (!$sampleFile) {
        // Thử bất kỳ ảnh số nào
        foreach (array_diff(scandir($colorPath), ['.','..']) as $f) {
            if (preg_match('/^\d+\.(png|webp|jpg|jpeg)$/i', $f)) { $sampleFile = $colorPath . '/' . $f; break; }
        }
    }
    if (!$sampleFile) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy ảnh mẫu trong folder màu']); return;
    }

    $detectedHex = detectDominantColor($sampleFile);
    if (!$detectedHex) {
        echo json_encode(['success' => false, 'message' => 'Không phân tích được màu từ ảnh']); return;
    }

    // Nếu tên đã đúng thì không cần đổi
    if (strtoupper($color) === strtoupper($detectedHex)) {
        echo json_encode(['success' => true, 'message' => 'Tên folder đã chính xác', 'new_name' => null, 'detected' => $detectedHex]); return;
    }

    $newColorPath = safe_join($partPath, $detectedHex);
    if (is_dir($newColorPath)) {
        echo json_encode(['success' => false, 'message' => "Tên mới '{$detectedHex}' đã tồn tại"]); return;
    }

    rename($colorPath, $newColorPath);
    syncPartMetadataToDB($kitFolder, $partFolder);

    echo json_encode(['success' => true, 'new_name' => $detectedHex, 'detected' => $detectedHex, 'message' => "Đã đổi tên: {$color} -> {$detectedHex}"]);
}

// ==================== NEW HANDLER: FIX ALL PART COLORS ====================
/**
 * Tự động chuẩn hóa toàn bộ folder màu trong một bộ phận.
 */
function handleFixAllPartColors() {
    $kitFolder  = jp('kit');
    $partFolder = jp('part_folder');
    if (!$kitFolder || !$partFolder) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']); return;
    }

    $base      = getKitBasePath();
    $kitPath   = safe_join($base, $kitFolder);
    $partPath  = safe_join($kitPath, $partFolder);
    if (!is_dir($partPath)) {
        echo json_encode(['success' => false, 'message' => 'Part folder not found']); return;
    }

    $colors = [];
    foreach (array_diff(scandir($partPath), ['.','..']) as $e) {
        if (is_dir($partPath . '/' . $e)) $colors[] = $e;
    }

    $processedCount = 0;
    $errors = [];

    foreach ($colors as $color) {
        $colorPath = $partPath . '/' . $color;
        $sampleFile = null;
        foreach (['1.png','1.webp'] as $fn) {
            if (file_exists($colorPath . '/' . $fn)) { $sampleFile = $colorPath . '/' . $fn; break; }
        }
        if (!$sampleFile) {
            foreach (array_diff(scandir($colorPath), ['.','..']) as $f) {
                if (preg_match('/^\d+\.(png|webp|jpg|jpeg)$/i', $f)) { $sampleFile = $colorPath . '/' . $f; break; }
            }
        }
        if (!$sampleFile) continue;

        $detectedHex = detectDominantColor($sampleFile);
        if (!$detectedHex) continue;

        if (strtoupper($color) === strtoupper($detectedHex)) continue;

        $newColorPath = $partPath . '/' . $detectedHex;
        if (is_dir($newColorPath)) {
            $errors[] = "{$color} -> {$detectedHex} (đã tồn tại)";
            continue;
        }

        if (rename($colorPath, $newColorPath)) {
            $processedCount++;
        } else {
            $errors[] = "Lỗi đổi tên: {$color} -> {$detectedHex}";
        }
    }

    if ($processedCount > 0) {
        syncPartMetadataToDB($kitFolder, $partFolder);
    }

    echo json_encode(['success' => true, 'processed_count' => $processedCount, 'errors' => $errors, 'message' => "Đã sửa {$processedCount} folder màu"]);
}

// ==================== NEW HANDLER: FIX COLORS BY POINT ====================
/**
 * Lấy màu pixel tại tọa độ (x, y) từ ảnh mẫu của TỪNG folder màu,
 * tự động đổi tên tất cả folder màu theo mã HEX lấy được, và đồng bộ DB.
 */
function handleFixColorsByPoint() {
    $kitFolder  = jp('kit');
    $partFolder = jp('part_folder');
    $px         = (int)jp('x', 0);
    $py         = (int)jp('y', 0);
    $filename   = jp('filename', '1.png');
    // Sanitize
    $filename = basename(preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $filename));
    if (!$kitFolder || !$partFolder) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']); return;
    }

    $base      = getKitBasePath();
    $kitPath   = safe_join($base, $kitFolder);
    $partPath  = safe_join($kitPath, $partFolder);
    if (!is_dir($partPath)) {
        echo json_encode(['success' => false, 'message' => 'Part folder not found']); return;
    }

    $colors = [];
    foreach (array_diff(scandir($partPath), ['.','..']) as $e) {
        if (is_dir($partPath . '/' . $e)) $colors[] = $e;
    }

    $processedCount = 0;
    $errors = [];

    foreach ($colors as $color) {
        $colorPath = $partPath . '/' . $color;

        // Tìm ảnh mẫu
        $sampleFile = null;
        if (file_exists($colorPath . '/' . $filename)) {
            $sampleFile = $colorPath . '/' . $filename;
        } else {
            // Thử 1.png fallback
            foreach (['1.png','1.webp'] as $fn) {
                if (file_exists($colorPath . '/' . $fn)) { $sampleFile = $colorPath . '/' . $fn; break; }
            }
            if (!$sampleFile) {
                foreach (array_diff(scandir($colorPath), ['.','..']) as $f) {
                    if (preg_match('/^\d+\.(png|webp|jpg|jpeg)$/i', $f)) { $sampleFile = $colorPath . '/' . $f; break; }
                }
            }
        }
        if (!$sampleFile) continue;

        $detectedHex = getPixelColorAtPoint($sampleFile, $px, $py);
        if (!$detectedHex) {
            // Fallback: dùng màu chủ đạo
            $detectedHex = detectDominantColor($sampleFile);
        }
        if (!$detectedHex) continue;

        if (strtoupper($color) === strtoupper($detectedHex)) continue;

        $newColorPath = $partPath . '/' . $detectedHex;
        if (is_dir($newColorPath)) {
            $errors[] = "{$color} -> {$detectedHex} (đã tồn tại)";
            continue;
        }

        if (rename($colorPath, $newColorPath)) {
            $processedCount++;
        } else {
            $errors[] = "Lỗi đổi tên: {$color} -> {$detectedHex}";
        }
    }

    if ($processedCount > 0) {
        syncPartMetadataToDB($kitFolder, $partFolder);
    }

    echo json_encode(['success' => true, 'processed_count' => $processedCount, 'errors' => $errors, 'message' => "Đã sửa {$processedCount} folder màu theo điểm ({$px},{$py})"]);
}

// ==================== BG JSON GENERATOR ====================
function handleGenerateBgJson() {
    $kitFolder = jp('kit'); // VD: "3" hoặc folder chứa bg/
    if (!$kitFolder) { echo json_encode(['success' => false, 'message' => 'Missing kit']); return; }

    $base    = getKitBasePath();
    $kitPath = safe_join($base, $kitFolder);

    // Fallback thêm /data nếu không tìm thấy
    if (!is_dir($kitPath)) {
        $kitPathData = safe_join(rtrim($base, '/') . '/data', $kitFolder);
        if (is_dir($kitPathData)) $kitPath = $kitPathData;
    }

    if (!is_dir($kitPath)) {
        echo json_encode(['success' => false, 'message' => 'Kit not found']); return;
    }

    $bgPath = $kitPath . '/bg';
    if (!is_dir($bgPath)) {
        // Nếu kit chính là folder bg rồi thì dùng luôn
        if (strtolower(basename($kitPath)) === 'bg') {
            $bgPath = $kitPath;
        } else {
            echo json_encode(['success' => false, 'message' => 'Folder bg không tồn tại trong kit này']); return;
        }
    }

    $imgExts = ['png', 'jpg', 'jpeg', 'webp', 'gif'];
    $result  = [];

    // Duyệt các folder con trong bg/ (VD: background, sticker)
    $topFolders = array_values(array_filter(
        array_diff(scandir($bgPath), ['.', '..']),
        fn($e) => is_dir($bgPath . '/' . $e)
    ));
    usort($topFolders, 'natural_sort_cmp');

    foreach ($topFolders as $topFolder) {
        $topPath    = $bgPath . '/' . $topFolder;
        $categories = [];

        // Tìm các folder con (category) trong topFolder
        $subEntries = array_diff(scandir($topPath), ['.', '..']);
        $subFolders = array_values(array_filter($subEntries, fn($e) => is_dir($topPath . '/' . $e)));
        usort($subFolders, 'natural_sort_cmp');

        if (empty($subFolders)) {
            // Không có folder con → category: "", đếm file ảnh trực tiếp trong topFolder
            $count = count(array_filter(
                array_diff(scandir($topPath), ['.', '..']),
                fn($f) => is_file($topPath . '/' . $f) && in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), $imgExts)
            ));
            $categories[] = ['category' => '', 'quantity' => $count];
        } else {
            // Có folder con → mỗi folder con là 1 category
            foreach ($subFolders as $sub) {
                $subPath = $topPath . '/' . $sub;
                $count   = count(array_filter(
                    array_diff(scandir($subPath), ['.', '..']),
                    fn($f) => is_file($subPath . '/' . $f) && in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), $imgExts)
                ));
                $categories[] = ['category' => $sub, 'quantity' => $count];
            }
        }

        $result[$topFolder] = $categories;
    }

    // Ghi bg.json bên trong folder bg/
    $jsonPath = $bgPath . '/bg.json';
    $jsonContent = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (file_put_contents($jsonPath, $jsonContent) === false) {
        echo json_encode(['success' => false, 'message' => 'Không thể ghi file bg.json']); return;
    }

    echo json_encode([
        'success'  => true,
        'message'  => 'Đã tạo bg.json thành công',
        'path'     => $jsonPath,
        'data'     => $result,
    ]);
}
