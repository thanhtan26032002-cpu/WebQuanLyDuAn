<div align="center">
  <h1 align="center">RingNet Project Management (Hệ Thống Quản Lý Dự Án)</h1>
  <p align="center">
    <strong>Giải pháp quản trị công việc toàn diện, tối ưu hiệu suất làm việc nhóm và theo dõi tiến độ dự án theo chuẩn Agile/Kanban.</strong>
  </p>
  <br />
</div>

---

## 📖 Giới Thiệu Tổng Quan

**RingNet Project Management** là một hệ thống phần mềm quản lý dự án nội bộ được thiết kế nhằm giúp các doanh nghiệp và đội ngũ tổ chức công việc một cách thông minh, mạch lạc. Với giao diện người dùng trực quan, hiện đại cùng hiệu năng mạnh mẽ từ kiến trúc tách biệt Front-end (Vue 3) và Back-end (Laravel), hệ thống đảm bảo trải nghiệm làm việc mượt mà, đồng bộ dữ liệu theo thời gian thực và quản lý tài nguyên hiệu quả.

---

## 🚀 Các Tính Năng Cốt Lõi (Key Features)

### 1. Quản Lý Dự Án Toàn Diện
- **Khởi tạo và Theo dõi:** Cho phép tạo các dự án mới với đầy đủ thông tin (tên, mô tả, ngày bắt đầu, ngày kết thúc).
- **Phân quyền và Thành viên:** Chỉ định các thành viên tham gia vào từng dự án cụ thể, với các vai trò tương ứng.
- **Tiến độ trực quan:** Tự động tính toán tỷ lệ hoàn thành dự án dựa trên số lượng công việc (tasks) đã hoàn tất.

### 2. Quản Lý Công Việc (Kanban Board)
- **Kanban linh hoạt:** Tổ chức công việc theo các cột trạng thái (To Do, In Progress, In Review, Done). Thay đổi trạng thái dễ dàng thông qua thao tác **Kéo & Thả (Drag & Drop)**.
- **Chi tiết công việc:** Đính kèm mô tả chi tiết, độ ưu tiên (Low, Medium, High), ngày hết hạn và phân công trực tiếp cho từng cá nhân (Assignee).
- **Tương tác liên tục:** Hỗ trợ tính năng thảo luận/bình luận (comments) trực tiếp trên từng thẻ công việc.

### 3. Hệ Thống Thông Báo & Lưu Vết (Notification & Audit Trail)
- **Hoạt động (Activity Logs):** Mọi thao tác thay đổi dữ liệu quan trọng (tạo dự án, cập nhật công việc, thay đổi trạng thái) đều được hệ thống lưu vết tự động.
- **Thông báo (Push Notifications):** Gửi thông báo tức thời tới người dùng khi họ được phân công một công việc mới hoặc có các cập nhật quan trọng liên quan.

### 4. Quản Lý Nhân Sự & Phòng Ban
- Tổ chức các thành viên vào các nhóm (Groups) hoặc phòng ban cụ thể để dễ dàng quản lý tài nguyên nhân sự.

### 5. Vận hành chuyên nghiệp
- Đăng nhập bằng API token và phân quyền `admin`, `project_manager`, `member`, `viewer` trên toàn bộ API.
- Trang **Công việc của tôi**, báo cáo quá hạn/khối lượng/ước lượng so với thực tế và chế độ xem đã lưu.
- Sức khỏe dự án, cập nhật định kỳ, cột mốc, phụ thuộc/chặn nhiệm vụ, người theo dõi và thông báo lưu trong database.
- Nhiệm vụ lặp, mẫu dự án, nhật ký thời gian và các tự động hóa nhắc hạn/báo hoàn thành/bàn giao trạng thái.

---

## 🛠 Kiến Trúc Hệ Thống (Tech Stack)

Dự án được xây dựng dựa trên kiến trúc **Client - Server (SPA)**, đảm bảo khả năng mở rộng (Scalability) và bảo trì (Maintainability) dài hạn.

- **Front-end:** 
  - Framework: **Vue.js 3** (Composition API).
  - Build Tool: **Vite** (Tốc độ build siêu tốc).
  - Styling: **TailwindCSS** (Utility-first CSS framework giúp thiết kế UI nhất quán, linh hoạt).
  - Icons: **Lucide Vue** & **Phosphor Icons**.
- **Back-end:** 
  - Framework: **Laravel 11** (PHP Framework mạnh mẽ, bảo mật).
  - RESTful API: Thiết kế API chuẩn mực, hỗ trợ JSON format.
- **Database:** 
  - RDBMS: **MySQL** (hoặc MariaDB).

---

## ⚙️ Hướng Dẫn Cài Đặt (Installation Guide)

### Yêu Cầu Hệ Thống (Prerequisites)
- **PHP** >= 8.2 (Cài đặt cùng Composer).
- **Node.js** >= 18.x (Cài đặt cùng npm).
- **MySQL** >= 8.0 (hoặc MariaDB tương đương).

### Bước 1: Khởi Tạo Cơ Sở Dữ Liệu
1. Mở hệ quản trị CSDL của bạn (MySQL Workbench, phpMyAdmin, DBeaver, v.v.).
2. Tạo một schema/database mới với định dạng UTF-8:
   ```sql
   CREATE DATABASE web_quan_ly_du_an CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
3. Cấu trúc dữ liệu được quản lý bằng Laravel migration ở bước tiếp theo; không cần import thủ công để cài mới.

### Bước 2: Thiết Lập Back-end (Laravel API)
1. Di chuyển vào thư mục back-end:
   ```bash
   cd back-end
   ```
2. Cài đặt các thư viện phụ thuộc (Dependencies):
   ```bash
   composer install
   ```
3. Cấu hình môi trường (Environment Variables):
   - Copy file `.env.example` thành `.env`.
   - Chỉnh sửa thông tin kết nối Database trong file `.env`:
     ```env
     DB_CONNECTION=mysql
     DB_HOST=127.0.0.1
     DB_PORT=3306
     DB_DATABASE=web_quan_ly_du_an
     DB_USERNAME=root
     DB_PASSWORD=your_password
     ```
4. Cấp phát khóa bảo mật (Application Key):
   ```bash
   php artisan key:generate
   ```
5. Tạo/cập nhật cấu trúc database:
   ```bash
   php artisan migrate
   ```
6. Khởi động API Server:
   ```bash
   php artisan serve
   ```
   *Mặc định, server sẽ lắng nghe tại `http://localhost:8000`.*

### Bước 3: Thiết Lập Front-end (Vue SPA)
1. Mở một terminal mới, di chuyển vào thư mục front-end:
   ```bash
   cd front-end
   ```
2. Cài đặt các gói NPM:
   ```bash
   npm install
   ```
3. Kiểm tra kết nối API trong `front-end/.env` (xem `front-end/.env.example`) và đặt `VITE_API_URL` nếu API không chạy cùng host.
4. Khởi động môi trường phát triển (Development Server):
   ```bash
   npm run dev
   ```
   *Truy cập vào ứng dụng tại `http://localhost:5173` (hoặc cổng hiển thị trên terminal).*

---

## 📚 Sổ Tay Hướng Dẫn Sử Dụng (User Manual)

Dưới đây là luồng thao tác cơ bản để khai thác tối đa sức mạnh của hệ thống.

### 1. Tổng Quan Bảng Điều Khiển (Dashboard)
- **Truy cập:** Ngay khi mở ứng dụng, trang Dashboard sẽ cung cấp cái nhìn toàn cảnh.
- **Tiện ích:** Theo dõi nhanh các chỉ số (Tổng dự án, Tổng Task, Task hoàn thành). Góc phải màn hình là dòng thời gian **Hoạt động gần đây (Activity Feed)**, giúp người quản trị nắm bắt mọi sự thay đổi trong hệ thống theo thời gian thực.

### 2. Chu trình Quản Lý Dự Án
1. **Tạo Dự Án Mới:** 
   - Vào menu **Dự án** ở Sidebar.
   - Nhấn nút **"+ Dự án mới"**, điền các thông số cơ bản (Tên, Mô tả, Deadline).
2. **Quản trị chi tiết:** Bấm vào thẻ dự án để đi vào không gian làm việc chi tiết của dự án đó.
3. **Thêm Thành Viên:** Trong chi tiết dự án (Tab Thành viên), bạn có thể lựa chọn các nhân sự từ danh sách hệ thống để mời vào dự án.

### 3. Vận Hành Công Việc với Bảng Kanban
1. Truy cập tab **Bảng (Kanban)** trong chi tiết Dự Án.
2. **Tạo Task:** Nhấn **"+ Thêm nhiệm vụ"** tại cột "To Do". Ghi rõ tiêu đề, độ ưu tiên, deadline, và **chọn người chịu trách nhiệm (Assignee)**.
3. **Phát Thông Báo:** Ngay khi bạn Assign một người, hệ thống sẽ gửi một thông báo đẩy đến biểu tượng **Chuông** của người đó.
4. **Cập Nhật Tiến Độ:** Nhân sự thực hiện công việc chỉ cần **Kéo (Drag)** và **Thả (Drop)** thẻ nhiệm vụ sang cột tiếp theo ("In Progress", "Done"). Mọi thao tác dịch chuyển này đều được hệ thống tự động ghi lại vào lịch sử hoạt động.

### 4. Quản Lý Thông Báo (Notification Center)
- Người dùng luôn thấy biểu tượng **Chuông thông báo** ở góc trên cùng bên phải. Dấu chấm đỏ cùng con số thể hiện số lượng thông báo chưa đọc.
- **Tương tác:** Bấm vào biểu tượng chuông để mở danh sách thông báo. Bấm trực tiếp vào thông báo để đánh dấu là "Đã đọc", hoặc dùng nút "Check All" (dấu tích đôi) để đánh dấu toàn bộ.

---

## 👨‍💻 Dành Cho Nhà Phát Triển (Developer Notes)

- Chạy kiểm thử backend bằng `php artisan test`; kiểm tra bản production frontend bằng `npm run build`.
- Scheduler phải chạy `php artisan schedule:work` (hoặc cron `schedule:run`) để tự động gửi nhắc hạn hằng ngày.

- **Quy tắc tạo mã tự động:** Hệ thống sử dụng một chuỗi mã định danh duy nhất (`code`) thay vì Auto-increment ID. Logic này được quản lý thông qua trait `App\Traits\GeneratesCode` trên Back-end. Các thực thể sẽ có tiền tố riêng (ví dụ: `US...` cho User, `PJ...` cho Project, `AC...` cho Activity).
- **Service Lõi:** Tham khảo `App\Services\ActivityService` để hiểu cơ chế trigger Hoạt động và Thông báo từ các Controller.
- **Quản lý State Front-end:** Ứng dụng Vue không dùng Vuex/Pinia phức tạp mà sử dụng mẫu thiết kế **Composables** (`useProjectWorkspace.js`) để duy trì tính đơn giản, gọn nhẹ và reactivity cho toàn bộ ứng dụng.

---
<div align="center">
  <p>Được phát triển với tâm huyết nhằm mang lại giải pháp quản trị dự án hiện đại và hiệu quả tối đa.</p>
</div>
