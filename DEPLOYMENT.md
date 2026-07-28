# Triển khai bản hiện tại

## Yêu cầu máy chủ

- PHP 8.3 trở lên cùng các extension: PDO MySQL, Fileinfo, OpenSSL, Mbstring, Intl và Zip.
- MySQL 8 hoặc MariaDB tương thích.
- Node.js chỉ cần trên máy build frontend; không bắt buộc trên máy chạy production.
- Document root của backend phải trỏ vào thư mục `back-end/public`, không trỏ vào `back-end`.

## Backend Laravel

1. Sao chép `back-end/.env.production.example` thành `back-end/.env` và điền URL, database cùng mật khẩu thật.
2. Cài thư viện production:

   `composer install --no-dev --optimize-autoloader`

3. Tạo khóa ứng dụng:

   `php artisan key:generate`

4. Với database mới, chạy migration và seed tài khoản hệ thống:

   `php artisan migrate --seed --force`

   Không import `database.sql` rồi chạy migration trên cùng database. File SQL chỉ dành cho cách cài đặt thủ công cũ.

   Nếu hosting đang dùng database đã import từ `database.sql` của phiên bản cũ, không chạy toàn bộ migration. Hãy import riêng file `database-upgrade-2026-07-28.sql` để bổ sung cột màu dự án và cho phép nhiệm vụ không có người phụ trách.

5. Tạo liên kết file upload và cache cấu hình:

   `php artisan storage:link`

   `php artisan optimize`

6. Cấp quyền ghi cho `back-end/storage` và `back-end/bootstrap/cache`.

## Frontend Vue

1. Nếu frontend và API cùng tên miền, giữ `VITE_API_URL` trống. Nếu khác tên miền, đặt URL đầy đủ kết thúc bằng `/api`.
2. Chạy:

   `npm ci`

   `npm run build`

3. Đưa nội dung `front-end/dist` lên web root và cấu hình fallback mọi route không phải tệp thật về `index.html`.

## Kiểm tra sau triển khai

- Truy cập `/up` của Laravel và xác nhận HTTP 200.
- Tạo thử một dự án không có hạn chót và một dự án có hạn hôm nay.
- Tạo một nhiệm vụ có người phụ trách và một nhiệm vụ không có người phụ trách; kéo đổi trạng thái, tải lại trang rồi xóa nhiệm vụ thử.
- Thêm thành viên, chọn nhóm trong biểu mẫu chỉnh sửa, kéo thành viên sang nhóm khác và kéo về khu vực chưa phân nhóm; tải lại trang để xác nhận dữ liệu vẫn còn.
- Upload rồi xóa một tệp thử; xác nhận URL `/storage/...` truy cập được.

## Lưu ý bảo mật của bản hiện tại

Các API nghiệp vụ vẫn đang public theo thiết kế hiện tại và giao diện chưa có màn hình đăng nhập. Chỉ nên phát hành bản này trong mạng nội bộ hoặc đặt lớp xác thực tại hosting. Không nên mở trực tiếp ra Internet cho dữ liệu thật cho tới khi bổ sung phân quyền ứng dụng.
