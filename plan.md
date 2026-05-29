# Kế hoạch Triển khai - Tích hợp bộ tùy biến dữ liệu nhân vật giống Tool Neka sang dự án Cloudflare

Yêu cầu của bạn là đồng bộ hóa toàn bộ giao diện và chức năng chỉnh sửa nhân vật (Character Creator) của dự án `tool-neka` sang dự án `cloudflare`. Cụ thể, mỗi ô dữ liệu Avatar ở Cột 3 của dự án `cloudflare` sẽ có thêm 1 nút Xem (View 👁️). Khi bấm vào nút này, một trang mới sẽ mở ra hiển thị toàn bộ giao diện chỉnh sửa cục bộ với các tính năng đầy đủ tương tự bên dự án `tool-neka`.

Dưới đây là kế hoạch chi tiết để tích hợp tính năng này bằng ngôn ngữ PHP tương thích hoàn toàn với dự án `cloudflare` của bạn.

## Nội dung cần người dùng duyệt

> [!IMPORTANT]
> - **Chuyển đổi Tech Stack**: Dự án `cloudflare` chạy bằng PHP, trong khi `tool-neka` chạy bằng Python. Chúng tôi sẽ viết một bộ định tuyến API mới bằng PHP (`api.php`) để thay thế toàn bộ các API Python của `app_server.py`.
> - **Đồng bộ hóa Database**: Khi bạn chỉnh sửa cấu trúc thư mục (đổi tên, xóa phần tử...), chúng tôi sẽ thiết kế để hệ thống tự động cập nhật bảng cơ sở dữ liệu MySQL tương ứng trong dự án `cloudflare`.
> - **Thao tác Ảnh (Cắt/Ghép) bằng PHP GD**: Chúng tôi sẽ sử dụng thư viện xử lý ảnh GD tích hợp sẵn của PHP để thực hiện các tính năng nâng cao như Ghép Layer (Merge Layers) và Tạo Thumbnail tự động (Crop Thumbs).

## Các thay đổi đề xuất

### 1. Cập nhật giao diện quản lý Cloudflare

#### [MODIFY] [main.php](file:///d:/web/laragon/www/cloudflare/main.php)
- Thêm mã CSS cho nút Xem `.avatar-view-btn` (icon 👁️) nằm cạnh nút Tải xuống ở Cột 3.
- Cập nhật hàm render HTML của thẻ `.avatar-card` để hiển thị nút Xem.
- Thêm hàm Javascript `viewCreator(pos)` để mở trang tùy biến nhân vật trong tab mới, truyền đầy đủ các tham số cấu hình: `tableName` (tên bảng), `savePath` (thư mục lưu), và `position` (tên kit).

### 2. Tích hợp Trang chỉnh sửa nhân vật mới (Character Creator)

#### [NEW] [character-creator.php](file:///d:/web/laragon/www/cloudflare/character-creator.php)
- Tạo trang giao diện PHP mới kế thừa toàn bộ cấu trúc giao diện HTML của `character-creator.html` từ dự án `tool-neka`.
- Nhúng các biến cấu hình toàn cục từ PHP sang JS bao gồm: `CLOUDFLARE_TABLE`, `CLOUDFLARE_SAVEPATH`, và `CLOUDFLARE_POSITION`.

#### [NEW] [js/character-creator.js](file:///d:/web/laragon/www/cloudflare/js/character-creator.js)
- Sao chép tệp JS từ dự án `tool-neka`.
- Thay đổi hàm gọi API: Định nghĩa tiền tố API trỏ về `api.php?action=ACTION&tableName=...&savePath=...` thay vì sử dụng `/api/ACTION` của server Python.

#### [NEW] [css/character-creator.css](file:///d:/web/laragon/www/cloudflare/css/character-creator.css)
- Sao chép toàn bộ tệp CSS giao diện từ dự án `tool-neka` sang dự án `cloudflare`.

### 3. Xây dựng lõi API Router bằng PHP

#### [NEW] [api.php](file:///d:/web/laragon/www/cloudflare/api.php)
- Xây dựng tệp định tuyến API bằng PHP để xử lý toàn bộ các yêu cầu chức năng từ giao diện của Character Creator bao gồm:
  - `get_kits_list`: Trả về danh sách các thư mục (kit) trong thư mục lưu.
  - `get_kit_structure`: Quét cấu trúc vật lý các thư mục con và tệp ảnh của kit được chọn để hiển thị lên thanh công cụ.
  - `download`: Proxy đọc và xuất luồng tệp ảnh (hỗ trợ cả thư mục cục bộ và đường dẫn mạng UNC).
  - `rename_folder` / `delete_part`: Cập nhật cấu trúc thư mục vật lý và đồng thời đồng bộ hóa cập nhật cơ sở dữ liệu MySQL (`tableName`).
  - `merge_layers`: Ghép các ảnh PNG đè lên nhau sử dụng thư viện GD của PHP, hỗ trợ áp dụng màu sắc và độ mờ.
  - `crop_batch_thumbs` / `auto_create_thumbs`: Cắt và tạo tệp ảnh thumbnail hàng loạt bằng PHP GD.
  - `flatten_colors` / `reorder_images` / `fix_colors_by_point`: Các tiện ích xử lý tệp tin và màu sắc ảnh.

## Kế hoạch Xác minh

### Kiểm thử tự động & Thủ công
1. Truy cập trang `main.php` của dự án `cloudflare`, chọn bảng dữ liệu.
2. Di chuột vào một Avatar ở Cột 3, kiểm tra xem nút 👁️ có hiển thị mượt mà bên cạnh nút Tải xuống hay không.
3. Click vào nút 👁️ để mở trang `character-creator.php` trong tab mới.
4. Kiểm tra xem giao diện có tải đầy đủ danh sách các bộ phận, màu sắc của kit đó hay không.
5. Thử nghiệm các tính năng cốt lõi: Ghép Layer, Tạo Thumbnail, Đổi tên thư mục và kiểm tra xem thư mục vật lý cũng như DB MySQL có thay đổi chính xác hay không.
