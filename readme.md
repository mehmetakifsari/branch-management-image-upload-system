# 🧭 Branch Management & Image Upload System  
*(Şube Yönetim ve Görsel Yükleme Sistemi)*

A PHP + PDO based multi-branch dashboard designed for uploading and managing images across different service branches.  
Developed under **AMR Danışmanlık**, this system provides an easy-to-use interface for branch staff and admins to manage uploads, monitor system activity, and communicate effectively between branches.

---

## 🚀 Features

### 🔹 Admin Panel
- Manage all branches and user accounts  
- Monitor total uploads, users, and last upload time  
- View real-time system metrics (CPU, memory, and disk usage)

### 🔹 Branch Panel
- Upload job-related images (e.g. warranty, service, or operation photos)  
- Each branch can view and manage its own uploads  
- WhatsApp quick contact link for branch warranty representative  
- Simple and responsive grid-based layout (5-column adaptive design)

### 🔹 System
- Built using **PDO (PHP Data Objects)** for secure database operations  
- Supports **multi-branch user authentication**  
- Uniform image display with lazy loading and pagination  
- Structured file storage:  
  - `/uploads/` → image files  
  - `/panel/` → dashboard files  
  - `/assets/css/` → main stylesheet  
  - `/inc/` → authentication & helper functions  
  - `/database.php` → database connection  

---

## 🛠️ Technologies Used

| Layer | Technology |
|-------|-------------|
| Backend | PHP 8.x + PDO |
| Frontend | HTML5, CSS3, Vanilla JS |
| Database | MySQL / MariaDB |
| UI Components | Responsive Grid Layout, System Monitor |
| Deployment | Compatible with Linux / Ubuntu / cPanel servers |

---

## ⚙️ Installation Guide

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/branch-management-image-upload.git
   cd branch-management-image-upload
   ```

2. **Set up database**
   - Create a MySQL database (e.g., `amr_panel`)
   - Import the provided SQL schema files (`user.sql`, `uploads.sql`, `settings.sql`)
   - Update `/database.php` with your DB credentials:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_NAME', 'amr_panel');
     define('DB_USER', 'root');
     define('DB_PASS', 'password');
     ```

3. **Upload the project**
   - Place all files under `/public_html/pnl2/` or your desired subdomain directory
   - Ensure `uploads/` folder has **write permissions (chmod 755 or 775)**

4. **Access**
   - Admin Panel → `https://yourdomain.com/pnl2/panel/`
   - Front Upload Page → `https://yourdomain.com/pnl2/index.php`

---

## 🖼️ Screenshot

*(You can add screenshots here)*  
Example structure:
```
📁 public_html/
 ├── pnl2/
 │   ├── panel/
 │   ├── uploads/
 │   ├── assets/
 │   ├── inc/
 │   └── database.php
```

---

## 🔒 Security Notes
- All database queries use **prepared statements (PDO)**  
- Admin-only features are protected with `is_admin()` checks  
- Each branch account is restricted to its own data view  
- Optional two-level login system (Admin & Branch user roles)

---

## 🧩 To-Do / Future Improvements
- Role-based permissions system (`role` column in `user` table`)  
- Image thumbnail generator for faster loading  
- Multi-language support (TR / EN)  
- Cron job for auto-cleaning old uploads  

---

## 🤝 Contributing
Pull requests are welcome!  
For major changes, please open an issue first to discuss what you would like to change.

1. Fork the repository  
2. Create your feature branch (`git checkout -b feature/amr-panel`)  
3. Commit your changes (`git commit -m 'Add new feature'`)  
4. Push to your branch (`git push origin feature/amr-panel`)  
5. Open a Pull Request 🎉

---

## 🧑‍💻 Author
**Developed by:** [AMR Danışmanlık](https://amrdanismanlik.com)  
**Developer:** Mehmet Akif Sarı  
📧 Contact: info@amrdanismanlik.com

---

## 🪪 License
This project is licensed under the **MIT License** — you’re free to use, modify, and distribute it.

---

### 🇹🇷 Özet (Türkçe)
Bu sistem, farklı şubelere ait iş emri fotoğraflarını düzenli bir şekilde toplayıp yönetmek için geliştirilmiştir.  
Yetkilendirme sistemi sayesinde her şube sadece kendi kayıtlarını görebilir. Admin paneli ise tüm şubelerin toplam durumunu izler.  
Panel, hem masaüstü hem mobil cihazlarda sorunsuz çalışır ve sistem kaynak kullanımını canlı olarak gösterir.
