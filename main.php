<?php

require_once 'auth.php';

restrictAccess();

$config = include(__DIR__ . '/config.php');
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Api</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/resumable.js/1.1.0/resumable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #3b82f6;
            --bg: #0f172a;
            --card: #1e293b;
            --text: #f8fafc;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 1rem;
            min-height: 100vh;
            box-sizing: border-box;
        }

        .main-layout {
            display: flex;
            gap: 1.5rem;

            margin: 0 auto;
            height: calc(100vh - 2rem);
        }

        .column {
            background-color: var(--card);
            border-radius: 1rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .col-form {
            flex: 1.5;
            min-width: 400px;
        }

        .col-sql {
            flex: 2;
            min-width: 500px;
        }

        .col-queue {
            flex: 1.2;
            min-width: 350px;
        }

        .col-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #334155;
            background: rgba(255, 255, 255, 0.02);
        }

        .col-header h2 {
            font-size: 1.1rem;
            margin: 0;
            color: var(--primary);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .col-body {
            padding: 1.5rem;
            overflow-y: auto;
            flex: 1;
        }

        .logout-link {
            color: #94a3b8;
            font-size: 0.75rem;
            text-decoration: none;
        }

        .logout-link:hover {
            color: var(--primary);
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            color: #94a3b8;
        }

        input[type="text"] {
            width: 100%;
            padding: 0.75rem;
            border-radius: 0.5rem;
            border: 1px solid #334155;
            background: #0f172a;
            color: white;
            box-sizing: border-box;
        }

        .upload-area {
            border: 2px dashed #334155;
            padding: 2rem;
            text-align: center;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: border-color 0.3s;
        }

        .upload-area:hover {
            border-color: var(--primary);
        }

        .progress-container {
            margin-top: 1rem;
            display: none;
        }

        .progress-bar {
            height: 0.5rem;
            background: #334155;
            border-radius: 1rem;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }

        .progress-fill {
            height: 100%;
            background: var(--primary);
            width: 0%;
            transition: width 0.3s;
        }

        #status {
            font-size: 0.875rem;
            color: #94a3b8;
            text-align: center;
        }

        .btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            cursor: pointer;
            width: 100%;
            font-weight: 600;
            margin-top: 1rem;
        }

        .pos-tag {
            display: inline-flex;
            align-items: center;
            background: #334155;
            color: #f8fafc;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            margin: 0.25rem;
            border: 1px solid #475569;
        }

        .pos-tag .delete-pos {
            margin-left: 0.5rem;
            color: #f87171;
            cursor: pointer;
            font-weight: bold;
            padding: 0 2px;
        }

        .pos-tag .delete-pos:hover {
            color: #ef4444;
        }

        #sqlOutput {
            width: 100%;
            height: 100%;
            background: #0f172a;
            color: #10b981;
            border: none;
            padding: 1rem;
            font-family: 'Courier New', Courier, monospace;
            font-size: 0.85rem;
            resize: none;
            box-sizing: border-box;
            display: block;
        }

        /* Tabs */
        .tabs {
            display: flex;
            gap: 0.5rem;
            padding: 0 1rem;
            background: rgba(255, 255, 255, 0.05);
            border-bottom: 1px solid #334155;
        }

        .tab-btn {
            padding: 0.75rem 1rem;
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 600;
            border-bottom: 2px solid transparent;
            transition: all 0.3s;
        }

        .tab-btn.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        .tab-content {
            display: none;
            height: 100%;
            flex: 1;
            overflow-y: auto;
        }

        .tab-content.active {
            display: block;
        }

        .sort-list {
            list-style: none;
            padding: 1rem;
            margin: 0;
        }

        .sort-item {
            background: #1e293b;
            border: 1px solid #334155;
            padding: 0.75rem 1rem;
            margin-bottom: 0.5rem;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            cursor: grab;
            transition: transform 0.2s, background 0.2s;
        }

        .sort-item:hover {
            background: #334155;
        }

        .sort-item .lvl-badge {
            background: var(--primary);
            color: white;
            padding: 0.1rem 0.4rem;
            border-radius: 0.25rem;
            font-size: 0.7rem;
            margin-right: 1rem;
            min-width: 40px;
            text-align: center;
        }

        .sort-item .drag-handle {
            margin-left: auto;
            color: #475569;
        }

        .ghost {
            opacity: 0.5;
            background: #3b82f6 !important;
        }

        .copy-btn {
            background: #334155;
            color: white;
            border: none;
            padding: 0.3rem 0.6rem;
            border-radius: 0.3rem;
            font-size: 0.7rem;
            cursor: pointer;
        }

        .copy-btn:hover {
            background: #475569;
        }

        #fileList {
            background: #0f172a;
            border-radius: 0.5rem;
            padding: 0.5rem;
            font-size: 0.75rem;
            color: #94a3b8;
            height: 100%;
            overflow-y: auto;
        }

        .avatar-card {
            text-align: center;
            background: #1e293b;
            padding: 0.5rem;
            border-radius: 0.5rem;
            border: 1px solid #334155;
            position: relative;
            transition: border-color 0.3s;
        }

        .avatar-card:hover {
            border-color: var(--primary);
        }

        .avatar-card img {
            width: 100%;
            height: auto;
            aspect-ratio: 1/1;
            object-fit: cover;
            border-radius: 0.25rem;
            display: block;
            margin-bottom: 0.5rem;
            background: #0f172a;
        }

        .avatar-upload-btn {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: rgba(15, 23, 42, 0.8);
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.8rem;
            border: 1px solid #334155;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .avatar-card:hover .avatar-upload-btn {
            opacity: 1;
        }

        .avatar-upload-btn:hover {
            background: var(--primary);
        }

        .avatar-download-btn {
            position: absolute;
            top: 0.5rem;
            right: 2.2rem;
            background: rgba(15, 23, 42, 0.8);
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.8rem;
            border: 1px solid #334155;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .avatar-card:hover .avatar-download-btn {
            opacity: 1;
        }

        .avatar-download-btn:hover {
            background: #10b981;
        }
    </style>
</head>

<body>
    <div class="main-layout">
        <!-- Cột 1: Form Upload -->
        <div class="column col-form">
            <div class="col-header">
                <h2>
                    <span>Cấu hình & Upload</span>
                    <a href="logout.php" class="logout-link">Đăng xuất</a>
                </h2>
            </div>
            <div class="col-body">
                <div class="form-group">
                    <label for="tableName">Tên bảng SQL của bạn: <span id="tableStatus"
                            style="font-size: 0.75rem; font-weight: normal; margin-left: 0.5rem;"></span></label>
                    <input type="text" id="tableName" placeholder="VD: ST193_PixcelMaker" value="" list="tableNameList">
                    <datalist id="tableNameList"></datalist>
                </div>

                <div class="form-group">
                    <label>Định dạng cấu trúc bảng:</label>
                    <div style="display: flex; gap: 1rem; margin-top: 0.5rem;">
                        <label style="font-weight: normal; font-size: 0.85rem; cursor: pointer;">
                            <input type="radio" name="tableFormat" value="1" checked> Chuẩn (6 cột)
                        </label>
                        <label style="font-weight: normal; font-size: 0.85rem; cursor: pointer;">
                            <input type="radio" name="tableFormat" value="2"> Mở rộng (thêm cột data)
                        </label>
                    </div>
                </div>

                <div id="tableOptions"
                    style="display: none; margin-bottom: 1.5rem; padding: 1rem; background: #1e293b; border-radius: 0.5rem; border: 1px solid #334155;">
                    <div id="existingPositions"
                        style="font-size: 0.75rem; color: #60a5fa; margin-bottom: 1rem; line-height: 1.4;"></div>
                    <button id="truncateBtn" class="btn"
                        style="background: #ef4444; font-size: 0.75rem; padding: 0.5rem 1rem; margin-top: 0; width: auto;">Xóa
                        toàn bộ bảng</button>
                </div>

                <?php $basePath = $config['upload_path'] ?? 'D:/web/laragon/www/upload'; ?>
                <div class="form-group">
                    <label for="subFolder">Chọn hoặc nhập tên Folder con tại: <span
                            style="color: #60a5fa"><?php echo $basePath; ?></span></label>
                    <input type="text" id="subFolder" list="folderList"
                        placeholder="Trống = Upload trực tiếp vào thư mục gốc">
                    <datalist id="folderList"></datalist>
                    <input type="hidden" id="savePath" value="<?php echo htmlspecialchars($basePath); ?>">
                </div>

                <div class="upload-area" id="dropTarget">
                    <p>Kéo thả hoặc nhấn để chọn <b>Thư mục (Folder)</b> bộ ảnh</p>
                    <input type="file" id="folderInput" webkitdirectory directory multiple style="display:none">
                    <button class="btn" style="background: #1e293b; border: 1px solid var(--primary);"
                        onclick="document.getElementById('folderInput').click()">+ Thêm Folder</button>
                </div>

                <div id="alertArea"
                    style="margin-top: 1rem; padding: 0.75rem; border-radius: 0.5rem; background: #1e293b; border: 1px solid #334155; font-size: 0.875rem; display: none; line-height: 1.5;">
                </div>

                <div class="progress-container" id="progressContainer">
                    <div class="progress-bar">
                        <div class="progress-fill" id="progressFill"></div>
                    </div>
                    <div id="status">Đang tải lên... <span id="percent">0</span>%</div>
                </div>

                <div style="border-top: 1px solid #334155; margin-top: 1.5rem; padding-top: 1.5rem;">
                    <div id="fileListContainer" style="display: none; height: auto; flex-direction: column;">
                        <label style="margin-bottom: 0.5rem; display: block;">Hàng đợi Upload:</label>
                        <div id="fileList"
                            style="max-height: 200px; margin-bottom: 1rem; background: #0f172a; border-radius: 0.5rem; padding: 0.5rem; font-size: 0.75rem; color: #94a3b8; overflow-y: auto;">
                        </div>
                        <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                            <button class="btn" id="startBtn" style="margin-top: 0; background: #3b82f6;">Thêm vào
                                MySQL</button>
                            <button class="btn" id="startNoSqlBtn" style="margin-top: 0; background: #64748b;">Không
                                thêm vào MySQL</button>
                        </div>
                        <div id="fileErrors"
                            style="margin-top: 1rem; font-size: 0.75rem; color: #f87171; max-height: 150px; overflow-y: auto;">
                        </div>
                    </div>
                    <div id="emptyQueue" style="color: #94a3b8; font-size: 0.875rem; text-align: center;">
                        Chưa có folder nào được chọn.
                    </div>
                </div>
            </div>
        </div>

        <!-- Cột 2: SQL Insert & Sắp xếp -->
        <div class="column col-sql">
            <div class="col-header" style="border-bottom: none; padding-bottom: 0;">
                <div
                    style="display: flex; justify-content: space-between; align-items: center; width: 100%; margin-bottom: 0.5rem;">
                    <h2 style="margin: 0;">Quản lý Dữ liệu</h2>
                    <div id="sqlControls">
                        <button class="copy-btn" onclick="copySQL()">Copy SQL</button>
                    </div>
                </div>
            </div>
            <div class="tabs">
                <button class="tab-btn active" onclick="switchTab('tabSQL', this)">Mã INSERT SQL</button>
                <button class="tab-btn" onclick="switchTab('tabLevels', this)">Sắp xếp Level</button>
            </div>

            <div id="tabSQL" class="tab-content active" style="padding: 0; background: #0f172a;">
                <textarea id="sqlOutput" readonly
                    placeholder="Câu lệnh SQL INSERT sẽ hiện tại đây sau khi bạn chọn bảng..."></textarea>
            </div>

            <div id="tabLevels" class="tab-content">
                <div style="padding: 1rem; font-size: 0.75rem; color: #94a3b8; border-bottom: 1px solid #334155;">
                    💡 Kéo thả để thay đổi thứ tự. Level sẽ tự động cập nhật từ trên xuống dưới (1 -> n).
                </div>
                <ul id="sortableList" class="sort-list">
                    <li style="text-align: center; color: #475569; margin-top: 2rem;">Vui lòng chọn bảng có dữ liệu...
                    </li>
                </ul>
            </div>
        </div>

        <!-- Cột 3: Avatar Preview -->
        <div class="column col-queue">
            <div class="col-header d-flex">
                <h2>Avatar data</h2>
                <input type="text" class="form-group" value="https://lvtglobal.tech/" readonly>
            </div>
            <div class="col-body" style="padding: 1rem;">
                <div id="avatarList"
                    style="display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 0.75rem; margin-bottom: 1.5rem;">
                    <div style="grid-column: 1/-1; text-align: center; color: #475569; padding: 2rem 0;">Chọn bảng để
                        xem avatar...</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const avatarBaseUrl = '<?php echo rtrim($config['avatar_base_url'], '/'); ?>';
        const baseUploadPath = '<?php echo rtrim(str_replace('\\', '/', $config['upload_path']), '/'); ?>';

        const tableNameInput = document.getElementById('tableName');
        const tableStatus = document.getElementById('tableStatus');
        const tableOptions = document.getElementById('tableOptions');
        const existingPositions = document.getElementById('existingPositions');
        const truncateBtn = document.getElementById('truncateBtn');
        const subFolderInput = document.getElementById('subFolder');
        const folderList = document.getElementById('folderList');
        const savePathInput = document.getElementById('savePath');
        const fileList = document.getElementById('fileList');
        const fileListContainer = document.getElementById('fileListContainer');
        const startBtn = document.getElementById('startBtn');
        const tableNameList = document.getElementById('tableNameList');

        function getFullSavePath() {
            const base = savePathInput.value.trim().replace(/\\/g, '/');
            const sub = subFolderInput.value.trim().replace(/\\/g, '/');
            if (!sub) return base;
            return base.replace(/\/$/, '') + '/' + sub.replace(/^\/|\/$/g, '');
        }

        // Fetch folders
        function fetchFolders(path = '') {
            fetch(`list_folders.php?path=${encodeURIComponent(path)}`)
                .then(res => res.json())
                .then(data => {
                    folderList.innerHTML = '';
                    data.forEach(folder => {
                        const option = document.createElement('option');
                        const fullVal = path ? (path.replace(/\/$/, '') + '/' + folder) : folder;
                        option.value = fullVal;
                        folderList.appendChild(option);
                    });
                });
        }

        // Fetch tables
        function fetchTables() {
            fetch('get_tables.php')
                .then(res => res.json())
                .then(data => {
                    tableNameList.innerHTML = '';
                    data.forEach(table => {
                        const option = document.createElement('option');
                        option.value = table;
                        tableNameList.appendChild(option);
                    });
                });
        }

        fetchFolders();
        fetchTables();

        function checkTable() {
            const table = tableNameInput.value.trim();
            if (!table) {
                tableStatus.innerText = '';
                tableOptions.style.display = 'none';
                document.getElementById('sqlOutput').value = '';
                document.getElementById('avatarList').innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: #475569; padding: 2rem 0;">Chọn bảng để xem avatar...</div>';
                return;
            }
            const currentSavePath = getFullSavePath();
            fetch(`check.php?tableName=${encodeURIComponent(table)}&savePath=${encodeURIComponent(currentSavePath)}`)
                .then(res => res.json())
                .then(data => {
                    if (data.tableExists) {
                        tableStatus.innerHTML = '<span style="color: #4ade80">✅ Đã tồn tại</span>';
                        tableOptions.style.display = 'block';

                        const avatarList = document.getElementById('avatarList');
                        avatarList.innerHTML = '';

                        if (data.positions && data.positions.length > 0) {
                            existingPositions.innerHTML = '<b>Các Position đã có (Nhấn dấu x để xóa):</b><br>';
                            const sortList = document.getElementById('sortableList');
                            sortList.innerHTML = '';

                            // Calculate relative URL path
                            let relativePath = currentSavePath.replace(/\\/g, '/').replace(baseUploadPath, '').replace(/^\//, '');
                            if (relativePath && !relativePath.endsWith('/')) relativePath += '/';

                            data.positions.forEach(item => {
                                // Column 1 Tags
                                const tag = document.createElement('span');
                                tag.className = 'pos-tag';
                                tag.innerHTML = `<span style="color: #94a3b8; margin-right: 0.4rem;">Level.${item.level}</span> <b>${item.position}</b> <span class="delete-pos" onclick="deletePosition('${item.position}')" title="Xóa position này">×</span>`;
                                existingPositions.appendChild(tag);

                                // Column 2 Draggable List
                                const li = document.createElement('li');
                                li.className = 'sort-item';
                                li.dataset.name = item.position;
                                li.innerHTML = `
                                    <span class="lvl-badge">Lvl.${item.level}</span>
                                    <span>${item.position}</span>
                                    <span class="drag-handle">☰</span>
                                `;
                                sortList.appendChild(li);

                                // Column 3 Avatars
                                const av = document.createElement('div');
                                av.className = 'avatar-card';
                                av.id = `card-${item.position}`;

                                let relativePath = currentSavePath.replace(/\\/g, '/').replace(baseUploadPath, '').replace(/^\//, '');
                                if (relativePath && !relativePath.endsWith('/')) relativePath += '/';

                                const imgUrl = item.hasAvatar
                                    ? `${avatarBaseUrl}/${relativePath}${item.position}/avatar.png?v=${Date.now()}`
                                    : 'https://via.placeholder.com/150?text=No+Avatar';

                                av.innerHTML = `
                                    <label class="avatar-upload-btn" title="Thay thế avatar">
                                        <input type="file" style="display:none" onchange="uploadAvatar('${item.position}', this)" accept="image/*">
                                        ↑
                                    </label>
                                    <div class="avatar-download-btn" title="Tải thư mục này về" onclick="downloadFolder('${item.position}')">
                                        ↓
                                    </div>
                                    <img src="${imgUrl}" id="img-${item.position}">
                                    <div style="font-size: 0.6rem; color: #94a3b8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${item.position}">${item.position}</div>
                                `;
                                avatarList.appendChild(av);
                            });
                        } else {
                            existingPositions.innerText = 'Bảng trống (chưa có dữ liệu).';
                            document.getElementById('sortableList').innerHTML = '<li style="text-align: center; color: #475569; margin-top: 2rem;">Bảng trống</li>';
                            avatarList.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: #475569; padding: 2rem 0;">Bảng trống</div>';
                        }
                        fetchSQL(); // Refresh SQL output
                    } else {
                        tableStatus.innerHTML = '<span style="color: #60a5fa">ℹ️ Chưa có (Sẽ tạo mới)</span>';
                        tableOptions.style.display = 'none';
                        document.getElementById('sqlOutput').value = '-- Bảng chưa tồn tại or sai cấu trúc bảng --';
                        document.getElementById('avatarList').innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: #475569; padding: 2rem 0;">Bảng chưa tồn tại</div>';
                    }
                })
                .catch(() => {
                    tableStatus.innerText = '';
                    tableOptions.style.display = 'none';
                });
        }

        function fetchSQL() {
            const table = tableNameInput.value.trim();
            if (!table) return;

            fetch(`get_sql.php?tableName=${encodeURIComponent(table)}`)
                .then(res => res.json())
                .then(data => {
                    if (data.sql) {
                        document.getElementById('sqlOutput').value = data.sql;
                    } else if (data.error) {
                        document.getElementById('sqlOutput').value = '-- Lỗi: ' + data.error;
                    }
                });
        }

        function copySQL() {
            const sql = document.getElementById('sqlOutput');
            sql.select();
            document.execCommand('copy');
            const btn = document.querySelector('.copy-btn');
            const oldText = btn.innerText;
            btn.innerText = 'Đã Copy!';
            setTimeout(() => btn.innerText = oldText, 2000);
        }

        function uploadAvatar(pos, input) {
            if (!input.files || !input.files[0]) return;

            const file = input.files[0];
            const currentSavePath = getFullSavePath();
            const posPath = currentSavePath.replace(/\/$/, '') + '/' + pos;

            const formData = new FormData();
            formData.append('avatar', file);
            formData.append('position', pos);
            formData.append('savePath', posPath);

            const card = document.getElementById(`card-${pos}`);
            const img = document.getElementById(`img-${pos}`);

            card.style.opacity = '0.5';

            fetch('upload_avatar.php', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Update image with cache buster
                        const oldSrc = img.src.split('?')[0];
                        img.src = oldSrc + '?v=' + Date.now();
                    } else {
                        alert('Lỗi upload avatar: ' + data.error);
                    }
                })
                .catch(err => alert('Lỗi kết nối: ' + err.message))
                .finally(() => {
                    card.style.opacity = '1';
                    input.value = ''; // Reset input
                });
        }

        function downloadFolder(pos) {
            const currentSavePath = getFullSavePath();
            const posPath = currentSavePath.replace(/\/$/, '') + '/' + pos;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'download_folder.php';
            form.target = '_blank';

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'folderPath';
            input.value = posPath;

            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }

        function switchTab(tabId, btn) {
            // Update buttons
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            // Update content
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');

            // Show/Hide SQL controls
            document.getElementById('sqlControls').style.visibility = (tabId === 'tabSQL') ? 'visible' : 'hidden';
        }

        // Initialize Sortable
        const sortableList = document.getElementById('sortableList');
        Sortable.create(sortableList, {
            animation: 150,
            ghostClass: 'ghost',
            onEnd: function () {
                updateLevelsInDB();
            }
        });

        function updateLevelsInDB() {
            const table = tableNameInput.value.trim();
            if (!table) return;

            const positions = [];
            document.querySelectorAll('#sortableList .sort-item').forEach(item => {
                positions.push(item.dataset.name);
            });

            const formData = new FormData();
            formData.append('tableName', table);
            positions.forEach(p => formData.append('positions[]', p));

            fetch('update_levels.php', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Update badges in UI without full refresh for smoothness
                        document.querySelectorAll('#sortableList .sort-item').forEach((item, index) => {
                            item.querySelector('.lvl-badge').innerText = `Lvl.${index + 1}`;
                        });
                        // Still need to refresh SQL and Col 1 tags
                        checkTable();
                    } else {
                        alert('Lỗi cập nhật level: ' + data.error);
                    }
                })
                .catch(err => console.error('Lỗi kết nối:', err));
        }

        function deletePosition(pos) {
            const table = tableNameInput.value.trim();
            if (!table || !confirm(`Xác nhận xóa position "${pos}" và toàn bộ dữ liệu liên quan trong bảng "${table}"?`)) return;

            const formData = new FormData();
            formData.append('tableName', table);
            formData.append('positions[]', pos);

            fetch('delete_positions.php', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        checkTable(); // Refresh
                    } else {
                        alert('Lỗi: ' + data.error);
                    }
                })
                .catch(err => alert('Lỗi kết nối: ' + err.message));
        }

        truncateBtn.addEventListener('click', () => {
            const table = tableNameInput.value.trim();
            if (!table) return;
            if (!confirm(`Bạn có chắc muốn XÓA TOÀN BỘ dữ liệu trong bảng "${table}"?`)) return;

            truncateBtn.disabled = true;
            truncateBtn.innerText = 'Đang xử lý...';

            const formData = new FormData();
            formData.append('tableName', table);

            fetch('truncate_table.php', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert('Đã xóa trắng bảng thành công.');
                        checkTable(); // Refresh status
                    } else {
                        alert('Lỗi: ' + data.error);
                    }
                })
                .catch(err => {
                    alert('Lỗi kết nối: ' + err.message);
                })
                .finally(() => {
                    truncateBtn.disabled = false;
                    truncateBtn.innerText = 'Xóa trắng bảng (TRUNCATE)';
                });
        });

        tableNameInput.addEventListener('input', checkTable);
        subFolderInput.addEventListener('input', checkTable);
        subFolderInput.addEventListener('change', () => {
            const val = subFolderInput.value.trim();
            if (val) {
                fetchFolders(val);
            } else {
                fetchFolders();
            }
        });

        const r = new Resumable({
            target: 'upload.php',
            chunkSize: 8 * 1024 * 1024, // 8MB
            simultaneousUploads: 6,
            testChunks: false,
            throttleProgressCallbacks: 1,
            query: () => {
                return {
                    tableName: tableNameInput.value.trim(),
                    savePath: getFullSavePath()
                };
            }
        });

        const folderInput = document.getElementById('folderInput');
        const progressContainer = document.getElementById('progressContainer');
        const progressFill = document.getElementById('progressFill');
        const percentSpan = document.getElementById('percent');
        const statusDiv = document.getElementById('status');

        if (!r.support) {
            alert('Trình duyệt của bạn không hỗ trợ Resumable.js');
        }

        // Assign browse and drop
        r.assignBrowse(folderInput, true);
        r.assignDrop(document.getElementById('dropTarget'));

        // Listeners
        folderInput.addEventListener('change', (e) => {
            // No need to call r.addFiles manually if using r.assignBrowse
            // but we want to trigger checks. 
            // Actually, r.assignBrowse(folderInput, true) will handle the change event.
        });

        let checkTimeout = null;
        let pendingFiles = [];

        r.on('fileAdded', (file) => {
            pendingFiles.push(file.file);
            clearTimeout(checkTimeout);
            checkTimeout = setTimeout(() => {
                performChecks(pendingFiles);
                pendingFiles = [];
            }, 100);

            fileListContainer.style.display = 'flex';
            document.getElementById('emptyQueue').style.display = 'none';
            const item = document.createElement('div');
            item.innerText = `• ${file.relativePath}`;
            fileList.appendChild(item);
        });

        function performChecks(fileList) {
            if (!tableNameInput.value.trim()) return;
            const fullPath = getFullSavePath();

            const files = Array.from(fileList).filter(file => {
                const name = file.name.toLowerCase();
                return !name.includes('thumbs.db') && !name.includes('desktop.ini');
            });

            if (files.length === 0) return;

            const rootFolders = [...new Set(files.map(f => {
                const path = f.webkitRelativePath || f.relativePath || f.name;
                return path.split('/')[0];
            }))];

            const alertDiv = document.getElementById('alertArea');
            alertDiv.innerHTML = 'Đang kiểm tra...';
            alertDiv.style.display = 'block';

            let messages = [];
            let tableChecked = false;

            const checkPromises = rootFolders.map(folderName => {
                const checkUrl = `check.php?tableName=${encodeURIComponent(tableNameInput.value.trim())}&savePath=${encodeURIComponent(getFullSavePath())}&folderName=${encodeURIComponent(folderName)}`;
                return fetch(checkUrl).then(res => res.json());
            });

            Promise.all(checkPromises).then(results => {
                results.forEach((data, index) => {
                    if (data.error) return;
                    if (!tableChecked && data.tableExists === false) {
                        messages.push(`<span style="color: #60a5fa">Bảng "${tableNameInput.value.trim()}" chưa tồn tại, sẽ tạo mới.</span>`);
                        tableChecked = true;
                    }
                    if (data.folderExists === true) {
                        messages.push(`<span style="color: #fbbf24">Cảnh báo: Thư mục "${rootFolders[index]}" đã tồn tại!</span>`);
                    }
                });

                if (messages.length > 0) {
                    alertDiv.innerHTML = messages.join('<br>');
                    alertDiv.style.display = 'block';
                } else {
                    alertDiv.style.display = 'none';
                }
            });
        }

        let shouldInsertSql = true;
        const startNoSqlBtn = document.getElementById('startNoSqlBtn');

        startBtn.addEventListener('click', () => {
            if (r.files.length === 0) {
                alert('Hàng đợi trống!');
                return;
            }
            shouldInsertSql = true;
            r.upload();
            startBtn.disabled = true;
            startNoSqlBtn.disabled = true;
            startBtn.innerText = 'Đang upload...';
            progressContainer.style.display = 'block';
            document.getElementById('fileErrors').innerHTML = '';
        });

        startNoSqlBtn.addEventListener('click', () => {
            if (r.files.length === 0) {
                alert('Hàng đợi trống!');
                return;
            }
            shouldInsertSql = false;
            r.upload();
            startBtn.disabled = true;
            startNoSqlBtn.disabled = true;
            startNoSqlBtn.innerText = 'Đang upload...';
            progressContainer.style.display = 'block';
            document.getElementById('fileErrors').innerHTML = '';
        });

        r.on('progress', () => {
            const progress = r.progress() * 100;
            progressFill.style.width = progress + '%';
            percentSpan.innerText = Math.floor(progress);
        });

        r.on('fileError', (file, message) => {
            const errDiv = document.getElementById('fileErrors');
            const p = document.createElement('div');
            p.innerText = `Lỗi file ${file.fileName}: Bỏ qua file này.`;
            errDiv.appendChild(p);
        });

        r.on('complete', () => {
            if (!shouldInsertSql) {
                statusDiv.innerHTML = `<span style="color: #4ade80">Thành công! Đã upload folder (Không thêm vào MySQL).</span>`;
                const resetBtn = document.createElement('button');
                resetBtn.className = 'btn';
                resetBtn.innerText = 'Làm mới để Upload tiếp';
                resetBtn.style.marginTop = '1rem';
                resetBtn.style.background = '#10b981';
                resetBtn.onclick = () => window.location.reload();
                statusDiv.appendChild(document.createElement('br'));
                statusDiv.appendChild(resetBtn);
                return;
            }

            statusDiv.innerText = 'Đang xử lý SQL và tạo bảng...';

            // Call process.php to generate SQL
            const formData = new FormData();
            formData.append('tableName', tableNameInput.value.trim());
            formData.append('savePath', getFullSavePath());

            const formatRadio = document.querySelector('input[name="tableFormat"]:checked');
            if (formatRadio) {
                formData.append('tableFormat', formatRadio.value);
            }

            fetch('process.php', {
                method: 'POST',
                body: formData
            })
                .then(res => {
                    if (!res.ok) {
                        return res.text().then(text => { throw new Error(`Server ${res.status}: ${text}`) });
                    }
                    return res.json();
                })
                .then(data => {
                    if (data.status === 'success') {
                        statusDiv.innerHTML = `<span style="color: #4ade80">Thành công! Đã chèn ${data.inserted_count} dòng vào bảng ${data.table}.</span>`;
                        checkTable(); // Refresh positions and SQL
                        // Thêm nút để upload tiếp
                        const resetBtn = document.createElement('button');
                        resetBtn.className = 'btn';
                        resetBtn.innerText = 'Làm mới để Upload tiếp';
                        resetBtn.style.marginTop = '1rem';
                        resetBtn.style.background = '#10b981';
                        resetBtn.onclick = () => window.location.reload();
                        statusDiv.appendChild(document.createElement('br'));
                        statusDiv.appendChild(resetBtn);
                    } else {
                        statusDiv.innerHTML = `<span style="color: #f87171">Lỗi: ${data.error}</span>`;
                        startBtn.disabled = false;
                        startNoSqlBtn.disabled = false;
                        startBtn.innerText = 'Thêm vào MySQL';
                        startNoSqlBtn.innerText = 'Không thêm vào MySQL';
                    }
                })
                .catch(err => {
                    statusDiv.innerHTML = `<span style="color: #f87171">Lỗi kết nối hoặc xử lý: ${err.message}</span>`;
                    console.error(err);
                    startBtn.disabled = false;
                    startNoSqlBtn.disabled = false;
                    startBtn.innerText = 'Thêm vào MySQL';
                    startNoSqlBtn.innerText = 'Không thêm vào MySQL';
                });
        });

        r.on('error', (message, file) => {
            console.error('Lỗi tổng quát:', message);
        });
    </script>
</body>

</html>