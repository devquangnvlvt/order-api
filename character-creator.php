<?php
require_once 'auth.php';
restrictAccess();

$config = include(__DIR__ . '/config.php');

$tableName = $_GET['table']    ?? '';
$savePath  = $_GET['savePath'] ?? $config['upload_path'];
$position  = $_GET['position'] ?? '';
$avatarBase = rtrim($config['avatar_base_url'], '/');
// Sanitize
$tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
$savePath  = rtrim(str_replace(['..', '\\'], ['', '/'], $savePath), '/');
$position  = preg_replace('/[^a-zA-Z0-9_\-\.\/]/', '', $position);

$pageTitle = $position ? "Xem: {$position}" : "Character Creator";
?>
<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($pageTitle) ?> - Check data</title>
  <link rel="stylesheet" href="css/character-creator.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Inter', 'Segoe UI', sans-serif;
      background: #f5f5f5;
    }

    .cf-topbar {
      background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
      color: #f8fafc;
      padding: 10px 20px;
      display: none;
      align-items: center;
      gap: 15px;
      font-size: 14px;
      border-bottom: 2px solid #3b82f6;
    }

    .cf-topbar a {
      color: #60a5fa;
      text-decoration: none;
      font-size: 13px;
    }

    .cf-topbar a:hover {
      color: #93c5fd;
    }

    .cf-topbar .cf-title {
      font-weight: 600;
      font-size: 15px;
      color: #fff;
    }

    .cf-topbar .cf-badge {
      background: #3b82f6;
      color: white;
      padding: 2px 8px;
      border-radius: 4px;
      font-size: 11px;
    }

    .cf-topbar .cf-path {
      color: #94a3b8;
      font-size: 11px;
      margin-left: auto;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 400px;
    }
  </style>
</head>

<body>

  <!-- Top Navigation Bar -->
  <div class="cf-topbar">
    <a href="main.php">← Quay lại upload data</a>
    <span class="cf-title">Character Creator</span>
    <?php if ($position): ?>
      <span class="cf-badge"><?= htmlspecialchars($position) ?></span>
    <?php endif; ?>
    <?php if ($tableName): ?>
      <span style="color:#94a3b8; font-size:12px;">📊 <?= htmlspecialchars($tableName) ?></span>
    <?php endif; ?>
    <span class="cf-path" title="<?= htmlspecialchars($savePath) ?>"><?= htmlspecialchars($savePath) ?></span>
  </div>

  <div class="container-full">
    <div class="header">
      <div style="display: flex; gap: 50px; align-items: center;">
        <h6 style="margin:0; font-size:13px;"><a href="main.php" style="color:#333;text-decoration:none;">← Quản lý</a></h6>
        <p style="font-size:13px;"><a href="javascript:void(0)" onclick="showInstructions()">Hướng dẫn</a></p>
      </div>
      <div class="kit-selector-area">
        <div class="flex-between m-b-5">
          <div class="flex-gap-8 align-center">
            <div class="tooltip-container">
              <div class="tooltip-text">
                Đặt tên folder dùng tiếng việt không dấu, chữ thường, thay khoảng trắng bằng gạch nối (-) hoặc gạch dưới (_).
              </div>
            </div>
          </div>
        </div>
        <div class="kit-search-container m-b-5">
          <input type="text" id="kit-search" placeholder="Tìm bộ sưu tập..." class="kit-search-input" oninput="filterKits(this.value)">
        </div>
        <div class="flex-gap-5">
          <select id="parent-selector" onchange="filterByCategory(this.value)" style="flex:1; border:0.5px solid #e7e4e4; border-radius:5px;">
            <option value="all">-- Tất cả mục --</option>
          </select>
          <select id="kit-selector" onchange="switchKit(this.value)" style="flex:2;">
            <option value="">Đang tải...</option>
          </select>
        </div>
      </div>
    </div>

    <div class="main-content">
      <div class="canvas-area">
        <canvas id="character-canvas" width="400" height="400"></canvas>
        <div class="controls">
          <button class="btn btn-secondary" onclick="randomizeCharacter()">Random (z)</button>
          <button class="btn btn-secondary btn-red" onclick="resetAllLayers()">Reset (x)</button>
        </div>
        <div id="structure-warnings" class="warning-box p-10 m-b-10 font-13"></div>
        <div id="separated-layers-info" class="info-box p-15 m-t-10 w-100 text-left">
          <h4 class="m-b-8 font-14">🌟 Các bộ phận có layer tách:</h4>
          <div id="separated-folders-list" class="sep-folders-list font-12"></div>
        </div>
      </div>

      <div class="sidebar">
        <div id="part-navigation-area" class="part-navigation focus-region">
          <div class="flex-between m-b-15">
            <div class="flex-gap-10 align-center justify-center">
              <div>
                <h3>Chọn bộ phận <span id="count-layer"></span></h3>
              </div>
              <div class="sort-controls flex-gap-5">
                <button id="sort-x-btn" class="btn btn-secondary btn-tiny" onclick="changePartSort('x')" title="Sắp xếp theo thứ tự hiển thị (X)">X</button>
                <button id="sort-y-btn" class="btn btn-secondary btn-tiny active" onclick="changePartSort('y')" title="Sắp xếp theo ID bộ phận (Y)">Y</button>
              </div>
              <div class="shift-controls flex-gap-5" style="margin-left:10px;">
                <button class="btn btn-secondary btn-tiny" onclick="shiftCoordinates(1)" title="Tăng chỉ số (+1)">+1</button>
                <button class="btn btn-secondary btn-tiny" onclick="shiftCoordinates(-1)" title="Giảm chỉ số (-1)">-1</button>
              </div>
              <div class="z-filter flex-gap-5" style="margin-left:8px; display:inline-flex; background:rgba(0,0,0,0.05); padding:2px; border-radius:6px;">
                <button id="z-all-btn" class="btn btn-secondary btn-tiny active" onclick="changeZFilter('all')" title="Hiển thị tất cả">All</button>
                <button id="z-1-btn" class="btn btn-secondary btn-tiny" onclick="changeZFilter('1')" title="Lọc Z=1">1</button>
                <button id="z-2-btn" class="btn btn-secondary btn-tiny" onclick="changeZFilter('2')" title="Lọc Z=2">2</button>
              </div>
            </div>
            <div>
              <button class="btn btn-blue btn-small" onclick="autoCreateThumbs()">Tạo Thumb toàn bộ (c)</button>
              <button class="btn btn-red btn-small m-l-5" onclick="deleteAllThumbs()">Xóa Thumb toàn bộ (v)</button>
            </div>
          </div>
          <div class="nav-icons" id="nav-icons"></div>
        </div>

        <div id="item-selector-area" class="item-selector focus-region">
          <div class="flex-between m-b-15">
            <h3 id="current-part-name">Chọn một bộ phận</h3>
            <div class="flex-gap-5">
              <button id="layer-details-btn" class="btn btn-purple btn-small" onclick="showCurrentItemLayers()">Chi tiết layer</button>
              <button id="merge-part-btn" class="btn btn-green btn-small" onclick="mergeLayers()">Ghép layer</button>
              <label id="color-thumb-label" class="color-thumb-toggle" title="Hiển thị thumbnail theo màu đang chọn">
                <input type="checkbox" id="color-thumb-check" onchange="toggleColorThumb(this.checked)">
                <span>Item màu</span>
              </label>
              <button id="flatten-colors-btn" class="btn btn-orange btn-small" onclick="flattenColors()">Gộp folder</button>
              <button id="create-part-thumb-btn" class="btn btn-blue btn-small" onclick="createPartThumbs()">Tạo Thumb</button>
              <button id="delete-part-thumb-btn" class="btn btn-red btn-small" onclick="deletePartThumbs()">Xóa Thumb</button>
              <button id="create-part-nav-btn" class="btn btn-indigo btn-small" onclick="createPartNav()">Tạo Nav (T)</button>
              <button id="delete-part-btn" class="btn btn-coral btn-small" onclick="promptDeletePart()">Xóa layer (A)</button>
            </div>
          </div>
          <div class="item-grid" id="item-grid"></div>
        </div>

        <div id="color-selector-area" class="color-selector focus-region">
          <div>
            <div id="edit-folder-color"></div>
          </div>
          <div class="color-grid" id="color-grid"></div>
        </div>
      </div>
    </div>

    <div style="text-align:center; padding:10px; font-size:12px; color:#666;">
      Check data &mdash; Character Creator &copy; 2026
    </div>
  </div>

  <!-- Merge Modal -->
  <div id="merge-modal-overlay" class="modal-overlay">
    <div class="merge-modal">
      <div class="merge-header">
        <h2>Ghép layer: <span id="merge-folder-name"></span></h2>
        <button class="btn btn-secondary" onclick="closeMergeModal()">✖</button>
      </div>
      <div class="merge-content">
        <div class="layer-library">
          <h3>Danh sách ảnh</h3>
          <p style="font-size:11px;color:#666;margin-bottom:10px;">Bấm vào ảnh để thêm vào danh sách ghép.</p>
          <div id="merge-library-grid" class="item-grid" style="grid-template-columns:repeat(3,1fr)"></div>
        </div>
        <div class="preview-pane">
          <div style="position:absolute;top:10px;right:10px;z-index:10">
            <button class="btn btn-secondary" onclick="toggleMergeBackground()" title="Đổi màu nền">🌗</button>
          </div>
          <div class="preview-canvas-container">
            <canvas id="merge-preview-canvas" width="1436" height="1902"></canvas>
          </div>
        </div>
        <div class="stack-manager d-flex flex-column">
          <h3>Thứ tự ghép</h3>
          <p style="font-size:11px;color:#666;margin-bottom:10px;">Ảnh bên dưới sẽ nằm đè lên ảnh bên trên.</p>
          <div id="merge-stack-list" class="flex-1 overflow-y-auto"></div>
          <div id="color-adjust-panel" class="color-adjust-box d-none m-t-15 p-15">
            <h4 class="m-0 m-b-10 font-13">Điều chỉnh màu: <span id="selected-layer-name"></span></h4>
            <div class="m-b-10">
              <label class="font-11 text-muted">Màu sắc: <span id="color-value">#FFFFFF</span></label>
              <div class="flex-gap-10">
                <input type="color" id="color-picker" value="#FFFFFF" class="color-picker-input" oninput="updateColorAdjustment()" />
                <button class="btn btn-secondary btn-small" onclick="clearColorTint()">✖ Xóa màu</button>
              </div>
            </div>
            <button class="btn btn-secondary w-100 font-11" onclick="resetColorAdjustment()">↺ Reset</button>
          </div>
          <div class="m-t-15">
            <button class="btn btn-secondary w-100 m-b-5" onclick="shuffleStack()">Random</button>
            <button class="btn btn-secondary w-100 btn-coral" onclick="clearStack()">Xóa hết</button>
          </div>
        </div>
        <div class="queue-manager d-flex flex-column">
          <h3>Hàng chờ ghép</h3>
          <p style="font-size:11px;color:#666;margin-bottom:10px;">Danh sách các task sẽ xử lý cùng lúc.</p>
          <div id="merge-queue-list" class="flex-1 overflow-y-auto"></div>
          <button id="process-queue-btn" class="btn btn-green w-100 m-t-10" onclick="confirmBatchMerge()" disabled>Ghép tất cả</button>
        </div>
      </div>
      <div class="merge-footer">
        <div class="flex-1 flex-gap-15 align-center">
          <div>
            <input type="text" id="merge-dest-name" value="1" class="input-merge-id" /> .png
          </div>
          <label class="flex-gap-5 align-center pointer font-13">
            <input type="checkbox" id="bulk-apply-check" /> Áp dụng tất cả màu
          </label>
        </div>
        <div class="flex-gap-10">
          <button id="add-to-queue-btn" class="btn btn-dark" onclick="addToMergeQueue()">Thêm vào hàng chờ</button>
          <button id="confirm-merge-btn" class="btn btn-green" onclick="confirmMerge()">Ghép nhanh</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Layer Details Modal -->
  <div id="layer-details-modal" class="modal-overlay">
    <div class="merge-modal modal-custom-medium">
      <div class="merge-header">
        <h2>📋 Chi tiết các layer</h2>
        <button class="btn btn-secondary" onclick="closeLayerDetailsModal()">✖</button>
      </div>
      <div class="p-20 overflow-y-auto flex-1">
        <div id="layer-details-content">
          <div class="loading">
            <div class="spinner"></div>Đang tải...
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Thumbnail Crop Modal -->
  <div id="crop-modal-overlay" class="modal-overlay">
    <div class="merge-modal modal-custom-large">
      <div class="merge-header">
        <h2>Tạo thumbnail &mdash; <span id="crop-part-name"></span></h2>
        <button class="btn btn-secondary" onclick="closeCropModal()">✖</button>
      </div>
      <div class="crop-modal-body">
        <div class="crop-modal-hint">
          🖱️ Nhấn &amp; kéo trên ảnh để di chuyển khung cắt. Thumbnail sẽ được tạo cho tất cả ảnh trong folder màu <b><span id="crop-color-name"></span></b>.
        </div>
        <div class="crop-modal-layout">
          <div class="crop-canvas-wrapper" id="crop-canvas-wrapper">
            <div class="pos-absolute t-10 r-10 z-10">
              <button class="btn btn-secondary" onclick="toggleCropBackground()" title="Đổi màu nền">🌗</button>
            </div>
            <canvas id="crop-canvas"></canvas>
            <div id="crop-frame">
              <div id="crop-resizer"></div>
            </div>
          </div>
          <div class="crop-controls-panel">
            <div class="crop-control-group">
              <label>Rộng (px)</label>
              <input type="number" id="crop-w" value="44" min="10" max="2000" oninput="updateCropFrameSize()">
            </div>
            <div class="crop-control-group">
              <label>Cao (px)</label>
              <input type="number" id="crop-h" value="44" min="10" max="2000" oninput="updateCropFrameSize()">
            </div>
            <div class="crop-control-group">
              <label class="flex-gap-8 pointer align-center" style="color:#ecf0f1;font-weight:normal;text-transform:none;font-size:13px;">
                <input type="checkbox" id="crop-lock-ratio" checked onchange="updateCropFrameSize()"> Khóa tỉ lệ 1:1
              </label>
            </div>
            <div class="crop-size-preview">
              <span id="crop-w-disp">44</span> × <span id="crop-h-disp">44</span> <span>px</span>
            </div>
            <div class="crop-coords-box">
              <div class="coords-title">📍 Tọa độ thực</div>
              <div class="crop-coord-row"><span>Trái (X):</span><span class="coord-val" id="crop-x-val">0</span></div>
              <div class="crop-coord-row"><span>Trên (Y):</span><span class="coord-val" id="crop-y-val">0</span></div>
            </div>
          </div>
        </div>
      </div>
      <div class="merge-footer justify-end">
        <button class="btn btn-secondary" onclick="closeCropModal()">Hủy</button>
        <button id="start-crop-btn" class="btn btn-green" onclick="confirmBatchCrop()">🚀 Bắt đầu tạo Thumbnail</button>
      </div>
    </div>
  </div>

  <!-- Color Picker Point Modal -->
  <div id="color-picker-modal" class="modal-overlay">
    <div class="merge-modal modal-custom-large">
      <div class="merge-header">
        <h2>🎯 Chọn điểm để lấy mã màu đồng loạt</h2>
        <button class="btn btn-secondary" onclick="closeColorPickerModal()">✖</button>
      </div>
      <div class="crop-modal-body">
        <div class="crop-modal-hint">🖱️ Click chuột vào một điểm trên ảnh để lấy màu.</div>
        <div class="crop-modal-layout" style="justify-content:center;">
          <div id="color-picker-wrapper" class="crop-canvas-wrapper"
            style="cursor:crosshair;position:relative;max-width:800px;max-height:600px;overflow:auto;background:repeating-conic-gradient(#f0f0f0 0% 25%, white 0% 50%) 50% / 20px 20px;">
            <div class="pos-absolute t-10 r-10 z-10">
              <button class="btn btn-secondary" onclick="toggleColorPickerBackground()" title="Đổi màu nền">🌗</button>
            </div>
            <img id="color-picker-img" style="display:block;max-width:100%;height:auto;">
            <div id="color-picker-marker" style="display:none;position:absolute;width:10px;height:10px;border:2px solid white;border-radius:50%;background:red;box-shadow:0 0 5px black;pointer-events:none;transform:translate(-50%,-50%);z-index:100;"></div>
          </div>
        </div>
      </div>
      <div class="merge-footer justify-end flex-gap-10">
        <div style="flex:1;font-size:13px;color:#666;" id="color-picker-info">Tọa độ: - , - | Màu: -</div>
        <button class="btn btn-secondary" onclick="closeColorPickerModal()">Hủy</button>
        <button id="confirm-fix-by-point-btn" class="btn btn-green" onclick="confirmFixColorsByPoint()" disabled>🚀 Bắt đầu Fix tất cả màu</button>
      </div>
    </div>
  </div>

  <!-- File Debug Modal -->
  <div id="file-debug-modal" class="modal-overlay debug-modal-position">
    <div class="modal-content modal-custom-large">
      <div class="flex-justify-end m-b-20 flex-gap-10">
        <button class="btn btn-secondary" onclick="toggleDebugGridTheme()" title="Đổi màu nền">🌗</button>
        <button class="btn btn-dark" onclick="document.getElementById('file-debug-modal').style.display='none'">Đóng</button>
      </div>
      <div class="flex-between m-b-5" style="display:flex;position:fixed;right:63px;top:77px;">
        <div class="delete-item">
          <div class="flex-wrap m-t-25">
            <input type="text" id="batch-delete-input" placeholder="Nhập số cần xóa, VD: 1, 3, 4" class="debug-input p-8 br-4 w-250px">
            <label class="flex-gap-5 text-muted pointer align-center">
              <input type="checkbox" id="batch-delete-all-check" checked class="checkbox-large"> Áp dụng tất cả folder màu
            </label>
          </div>
          <button class="btn btn-red" onclick="batchDeleteAndReorder()">Xóa &amp; Sắp xếp lại</button>
          <button id="delete-selected-btn" class="btn btn-red" onclick="deleteSelectedImages()" style="display:none;background:#c0392b;">
            Xóa các ảnh đã chọn (<span id="selected-count">0</span>)
          </button>
        </div>
      </div>
      <p id="file-debug-subtitle" class="text-muted m-b-20">Đang tải...</p>
      <div id="file-debug-grid" class="debug-file-grid"></div>
    </div>
  </div>

  <!-- Rename Modal -->
  <div id="rename-modal-overlay" class="modal-overlay">
    <div class="modal-content modal-custom-small">
      <div class="merge-header">
        <h2 id="rename-modal-title">Đổi tên thư mục</h2>
        <button class="btn btn-secondary" onclick="closeRenameModal()">✖</button>
      </div>
      <div class="p-20">
        <div class="input-wrapper m-b-20">
          <label class="font-12 text-muted m-b-5 d-block">Tên hiện tại: <span id="rename-old-name" class="font-bold"></span></label>
          <input type="text" id="rename-new-input" class="debug-input w-100 p-10 br-8" placeholder="Nhập tên mới..."
            onkeydown="if(event.key==='Enter') confirmRenameModal(); if(event.key==='Escape') closeRenameModal();">
        </div>
        <div class="flex-justify-end flex-gap-10">
          <button class="btn btn-secondary" onclick="closeRenameModal()">Hủy</button>
          <button class="btn btn-green" onclick="confirmRenameModal()">Xác nhận</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Instruction Modal -->
  <div id="instruction-modal-overlay" class="modal-overlay">
    <div class="modal-content modal-custom-medium">
      <div class="merge-header">
        <h2>Hướng dẫn</h2>
        <button class="btn btn-secondary" onclick="closeInstructionModal()">✖</button>
      </div>
      <div class="p-20 overflow-y-auto" style="max-height:70vh;background:white;">
        <div class="instruction-content">
          <section class="m-b-20">
            <h4 class="m-b-10" style="color:#667eea;">Phím tắt</h4>
            <p><b>Z</b>: Random &nbsp; <b>X</b>: Reset &nbsp; <b>C</b>: Tạo thumb toàn bộ &nbsp; <b>V</b>: Xóa thumb</p>
            <p><b>T</b>: Tạo Nav &nbsp; <b>A</b>: Xóa layer &nbsp; <b>W</b>: Sắp xếp ảnh &nbsp; <b>E</b>: Fix theo điểm</p>
            <p><b>1</b>: Vùng bộ phận &nbsp; <b>2</b>: Vùng item &nbsp; <b>3</b>: Vùng màu</p>
          </section>
          <div class="info-box p-15 m-t-20">
            <p class="font-13" style="color:#1976d2;">Check data &mdash; Character Creator</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Global Loading Overlay -->
  <div id="global-loading-overlay">
    <div class="spinner"></div>
    <p id="global-loading-message">Đang xử lý...</p>
  </div>

  <!-- Inject CF config trước khi load JS -->
  <script>
    // === CLOUDFLARE CONTEXT ===
    const CF_SAVE_PATH = <?= json_encode($savePath) ?>;
    const CF_TABLE_NAME = <?= json_encode($tableName) ?>;
    const CF_POSITION = <?= json_encode($position) ?>;
    const CF_AVATAR_BASE = <?= json_encode($avatarBase) ?>;
  </script>

  <!-- 1. Load adapter trước để patch fetch() -->
  <script src="js/cf-api-adapter.js"></script>
  <!-- 2. Load character-creator.js gốc -->
  <script src="js/character-creator.js?v=2"></script>

</body>

</html>