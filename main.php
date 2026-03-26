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
    <title>Folder Upload & SQL Generator</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/resumable.js/1.1.0/resumable.min.js"></script>
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
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        .container {
            background-color: var(--card);
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 500px;
            position: relative;
        }

        .logout-link {
            position: absolute;
            top: 1rem;
            right: 1rem;
            color: #94a3b8;
            font-size: 0.75rem;
            text-decoration: none;
        }
        .logout-link:hover { color: var(--primary); }

        h1 {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            text-align: center;
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
            margin-top: 2rem;
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
    </style>
</head>

<body>
    <div class="container">
        <a href="logout.php" class="logout-link">Đăng xuất</a>
        <h1>Folder Upload & SQL Generator</h1>

        <div class="form-group">
            <label for="tableName">Tên bảng SQL của bạn: <span id="tableStatus" style="font-size: 0.75rem; font-weight: normal; margin-left: 0.5rem;"></span></label>
            <input type="text" id="tableName" placeholder="VD: ST193_PixcelMaker" value="" list="tableNameList">
            <datalist id="tableNameList"></datalist>
        </div>

        <div id="tableOptions" style="display: none; margin-top: 1.5rem; padding: 1rem; background: #1e293b; border-radius: 0.5rem; border: 1px solid #334155;">
            <div id="existingPositions" style="font-size: 0.75rem; color: #60a5fa; margin-bottom: 1rem; line-height: 1.4;"></div>
            <button id="truncateBtn" class="btn" style="background: #ef4444; font-size: 0.75rem; padding: 0.5rem 1rem; margin-top: 0; width: auto;">Xóa trắng bảng (TRUNCATE)</button>
        </div>

        <?php $basePath = $config['upload_path'] ?? 'D:/web/laragon/www/upload'; ?>
        <div class="form-group">
            <label for="subFolder">Chọn hoặc nhập tên Folder con tại: <span style="color: #60a5fa"><?php echo $basePath; ?></span></label>
            <div style="display: flex; gap: 0.5rem;">
                <input type="text" id="subFolder" list="folderList" placeholder="Trống = Upload trực tiếp vào thư mục gốc" style="flex: 1; padding: 0.75rem; border-radius: 0.5rem; border: 1px solid #334155; background: #0f172a; color: white;">
                <datalist id="folderList"></datalist>
            </div>
            <input type="hidden" id="savePath" value="<?php echo htmlspecialchars($basePath); ?>">
        </div>

        <div class="upload-area" id="dropTarget">
            <p>Kéo thả hoặc nhấn để chọn <b>Thư mục (Folder)</b> bộ ảnh</p>
            <p style="font-size: 0.75rem; color: #94a3b8;">Có thể chọn nhiều folder lần lượt để thêm vào hàng đợi</p>
            <input type="file" id="folderInput" webkitdirectory directory multiple style="display:none">
            <button class="btn" style="background: #1e293b; border: 1px solid var(--primary);"
                onclick="document.getElementById('folderInput').click()">+ Thêm Folder</button>
        </div>

        <div id="fileListContainer" style="margin-top: 1rem; display: none;">
            <label>Hàng đợi upload:</label>
            <div id="fileList"
                style="max-height: 150px; overflow-y: auto; background: #0f172a; border-radius: 0.5rem; padding: 0.5rem; font-size: 0.75rem; color: #94a3b8;">
            </div>
            <button class="btn" id="startBtn" style="margin-top: 1rem;">Bắt đầu Upload</button>
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

        <div id="fileErrors" style="margin-top: 1rem; font-size: 0.75rem; color: #f87171; max-height: 100px; overflow-y: auto;"></div>
    </div>

    <script>
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
                return;
            }
            fetch(`check.php?tableName=${encodeURIComponent(table)}`)
                .then(res => res.json())
                .then(data => {
                    if (data.tableExists) {
                        tableStatus.innerHTML = '<span style="color: #4ade80">✅ Đã tồn tại</span>';
                        tableOptions.style.display = 'block';
                        if (data.positions && data.positions.length > 0) {
                            existingPositions.innerHTML = `<b>Các Position đã có:</b> ${data.positions.join(', ')}`;
                        } else {
                            existingPositions.innerText = 'Bảng trống (chưa có dữ liệu).';
                        }
                    } else {
                        tableStatus.innerHTML = '<span style="color: #60a5fa">ℹ️ Chưa có (Sẽ tạo mới)</span>';
                        tableOptions.style.display = 'none';
                    }
                })
                .catch(() => {
                    tableStatus.innerText = '';
                    tableOptions.style.display = 'none';
                });
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
        subFolderInput.addEventListener('change', () => {
            const val = subFolderInput.value.trim();
            if (val) {
                // If ending with / or we just want to see what's inside
                // We'll update the list for next selection
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

            fileListContainer.style.display = 'block';
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

        startBtn.addEventListener('click', () => {
            if (r.files.length === 0) {
                alert('Hàng đợi trống!');
                return;
            }
            r.upload();
            startBtn.disabled = true;
            startBtn.innerText = 'Đang upload...';
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
            statusDiv.innerText = 'Đang xử lý SQL và tạo bảng...';

            // Call process.php to generate SQL
            const formData = new FormData();
            formData.append('tableName', tableNameInput.value.trim());
            formData.append('savePath', getFullSavePath());

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
                        startBtn.innerText = 'Thử lại';
                    }
                })
                .catch(err => {
                    statusDiv.innerHTML = `<span style="color: #f87171">Lỗi kết nối hoặc xử lý: ${err.message}</span>`;
                    console.error(err);
                    startBtn.disabled = false;
                    startBtn.innerText = 'Bắt đầu Upload';
                });
        });

        r.on('error', (message, file) => {
            console.error('Lỗi tổng quát:', message);
        });
    </script>
</body>

</html>