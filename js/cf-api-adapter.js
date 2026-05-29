/**
 * CF_API_ADAPTER - Cloudflare API Adapter
 */

const CF_API_BASE = 'api.php';

// Set KIT_BASE_PATH ngay lập tức
// buildKitPath() trong character-creator.js detect &kit= → dùng &path= format
// img.src = "api.php?...&kit=3&path=1-2/nav.png?v=123" → PHP nhận kit=3, path=1-2/nav.png ✅
window.KIT_BASE_PATH = `${CF_API_BASE}?action=file&savePath=${encodeURIComponent(CF_SAVE_PATH)}&kit=`;

// === MONKEY-PATCH fetch() ===
const _originalFetch = window.fetch.bind(window);

window.fetch = function(url, options) {
    if (typeof url !== 'string') return _originalFetch(url, options);

    if (url.startsWith('/api/')) {
        const urlObj = new URL(url, window.location.origin);
        const action = urlObj.pathname.replace('/api/', '');
        const newUrl = `${CF_API_BASE}?action=${encodeURIComponent(action)}&savePath=${encodeURIComponent(CF_SAVE_PATH)}&tableName=${encodeURIComponent(CF_TABLE_NAME)}&position=${encodeURIComponent(CF_POSITION)}&${urlObj.searchParams.toString()}`;
        return _originalFetch(newUrl, options);
    }

    return _originalFetch(url, options);
};

// === Monkey-patch showLayerDetails ===
window.addEventListener('load', () => {
    const _origShowLayerDetails = window.showLayerDetails;
    if (typeof _origShowLayerDetails === 'function') {
        window.showLayerDetails = async function(folderName, itemNumber, event) {
            event.stopPropagation();
            const modal = document.getElementById('layer-details-modal');
            const content = document.getElementById('layer-details-content');
            modal.style.display = 'flex';
            content.innerHTML = '<div class="loading"><div class="spinner"></div>Đang tải...</div>';
            try {
                const response = await fetch(`${CF_API_BASE}?action=get_item_layers&savePath=${encodeURIComponent(CF_SAVE_PATH)}&tableName=${encodeURIComponent(CF_TABLE_NAME)}&position=${encodeURIComponent(CF_POSITION)}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ kit: window.CURRENT_KIT_FOLDER, folder: folderName, item_number: itemNumber }),
                });
                const result = await response.json();
                if (result.success) {
                    let html = `<h3 style="margin-bottom:15px;">Item #${itemNumber} - Tổng ${result.total_count} layer(s)</h3><div style="display:grid;gap:15px;">`;
                    (result.layers || []).forEach((layer, idx) => {
                        const imgUrl = layer.url || '';
                        html += `<div style="border:1px solid #ddd;border-radius:8px;padding:15px;background:#f9f9f9;"><div style="display:flex;gap:15px;align-items:start;">${imgUrl ? `<img src="${imgUrl}" style="width:100px;height:100px;object-fit:contain;border:1px solid #ccc;background:white;border-radius:4px;">` : '<div style="width:100px;height:100px;background:#eee;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:11px;color:#999;">No img</div>'}<div style="flex:1;"><div style="font-weight:bold;margin-bottom:8px;">🎨 Layer #${idx + 1}</div><div style="font-size:12px;color:#666;line-height:1.6;"><div><strong>File:</strong> <code style="background:#e0e0e0;padding:2px 4px;border-radius:3px;">${layer.filename}</code></div><div><strong>Kích thước:</strong> ${layer.width || '?'}×${layer.height || '?'}px</div></div></div></div></div>`;
                    });
                    html += '</div>';
                    content.innerHTML = html;
                } else {
                    content.innerHTML = `<div style="color:red;">Lỗi: ${result.message}</div>`;
                }
            } catch (e) {
                content.innerHTML = `<div style="color:red;">Lỗi kết nối: ${e.message}</div>`;
            }
        };
    }
});
