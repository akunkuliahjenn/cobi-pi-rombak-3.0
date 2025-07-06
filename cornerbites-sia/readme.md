# Sistem Informasi Akuntansi (SIA) untuk UMKM - Corner Bites

Aplikasi web sederhana ini dibangun menggunakan PHP dan MySQL dengan antarmuka yang didesain menggunakan Tailwind CSS, bertujuan untuk membantu pelaku UMKM seperti Toko Corner Bites dalam mencatat transaksi keuangan dan menyusun laporan dasar. Kini dilengkapi dengan **Panel Admin** untuk pengelolaan pengguna dan sistem.

## Fitur Utama

* **Sistem Autentikasi Berbasis Peran:**
    * Login dan Logout.
    * Pengguna dengan peran `user` untuk UMKM.
    * Pengguna dengan peran `admin` untuk pengelolaan sistem.
* **Pencatatan Transaksi:**
    * Pemasukan (Penjualan)
    * Pengeluaran (Pembelian, Biaya Operasional)
* **Manajemen Produk:**
    * Pencatatan data produk (nama, satuan, harga beli, harga jual).
    * Pemantauan stok (stok awal, stok masuk, stok keluar).
    * Perhitungan Harga Pokok Penjualan (HPP) sederhana.
* **Laporan Keuangan Dasar:**
    * Laporan Laba Rugi
    * Laporan Neraca
* **Dashboard Pengguna:** Menampilkan ringkasan informasi keuangan penting UMKM.
* **Panel Admin:**
    * Dashboard admin dengan statistik sistem global.
    * Manajemen pengguna (tambah, edit peran, hapus pengguna).
    * Lihat semua transaksi sistem.
    * Statistik global sistem.

## Teknologi

* **Backend:** PHP
* **Database:** MySQL
* **Frontend:** HTML, Tailwind CSS, JavaScript (minimal)

## Struktur Proyek

```
cornerbites-sia/
├── config/                # Konfigurasi aplikasi (koneksi database)
│   └── db.php
│
├── includes/              # Komponen layout reusable (header, footer, sidebar, auth_check)
│   ├── header.php
│   ├── footer.php
│   ├── sidebar.php
│   └── auth_check.php
│
├── auth/                  # Halaman autentikasi (login, register, logout, proses login)
│   ├── login.php
│   ├── register.php
│   ├── logout.php
│   └── login_process.php
│
├── pages/                 # Halaman khusus untuk user (bukan admin)
│   ├── dashboard.php
│   ├── transaksi.php
│   ├── produk.php
│   └── laporan.php
│
├── admin/                 # Panel admin (akses terbatas untuk role admin)
│   ├── dashboard.php
│   ├── users.php
│   ├── semua_transaksi.php
│   └── statistik.php
│
├── process/               # File pemrosesan data (simpan/edit/hapus)
│   ├── simpan_transaksi.php
│   ├── simpan_produk.php
│   ├── register_process.php
│   ├── hapus_user.php
│   └── kelola_user.php
│
├── assets/                # Frontend: CSS, JS, gambar
│   ├── css/
│   │   └── style.css
│   ├── js/
│   └── img/
│
├── index.php              # File entry point utama aplikasi
└── .htaccess              # (Opsional) URL rewrite & proteksi file
```

## Persyaratan Sistem

* Web Server (Apache/Nginx)
* PHP (versi 7.4 atau lebih baru direkomendasikan)
* MySQL Database
* Composer (opsional, jika ingin menambahkan dependensi PHP)
* Node.js dan NPM/Yarn (untuk menginstal dan mengkompilasi Tailwind CSS jika tidak menggunakan CDN)

## Instalasi dan Setup

1.  **Kloning Repositori / Buat Struktur Folder:**
    Tempatkan semua file dan folder ini ke dalam direktori root server web lokal Anda (misalnya `htdocs` untuk XAMPP, `www` untuk WAMP). Pastikan nama folder root adalah `cornerbites-sia`.

2.  **Konfigurasi Database:**
    * Buat database baru di MySQL (misalnya melalui phpMyAdmin) dengan nama `corner_bites_sia`.
    * Impor skema database berikut. **PENTING:** Untuk user admin awal, Anda HARUS menghasilkan hash password menggunakan `password_hash('password_anda', PASSWORD_DEFAULT);` di PHP dan mengganti placeholder di bawah.

        ```sql
        CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin', 'user') DEFAULT 'user' NOT NULL
        );

        -- Tambahkan user admin awal (password: admin123)
        -- GANTI '$2y$10$XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX' dengan hash password Anda yang sebenarnya
        INSERT INTO users (username, password, role) VALUES ('admin', '$2y$10$wTf2zD5fL.0123456789abcdefghijklmnopqrstuvw', 'admin');
        -- Anda bisa menghasilkan hash dengan script PHP sementara:
        -- echo password_hash('admin123', PASSWORD_DEFAULT);

        CREATE TABLE products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            unit VARCHAR(50),
            initial_stock INT DEFAULT 0,
            current_stock INT DEFAULT 0,
            purchase_price DECIMAL(15, 2) DEFAULT 0.00,
            sale_price DECIMAL(15, 2) DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            date DATE NOT NULL,
            type ENUM('pemasukan', 'pengeluaran') NOT NULL,
            description TEXT,
            amount DECIMAL(15, 2) NOT NULL,
            product_id INT NULL,
            quantity INT NULL,
            user_id INT NULL, -- Tambahan untuk melacak user yang melakukan transaksi
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL -- Relasi ke tabel users
        );
        ```
    * Edit file `config/db.php` dengan kredensial database Anda.

3.  **Setup Tailwind CSS:**
    * **Opsi 1 (Cepat - Rekomendasi untuk Demo/Pengembangan Awal):** Gunakan CDN Tailwind CSS yang sudah disertakan di `includes/header.php`. Anda tidak perlu langkah ini.
    * **Opsi 2 (Produksi - Rekomendasi untuk Project Lebih Lanjut):**
        * Pastikan Node.js dan NPM sudah terinstal.
        * Buka terminal di folder `cornerbites-sia/`.
        * Jalankan `npm install` untuk menginstal Tailwind CSS dan dependensi lainnya.
        * Buat file `src/input.css` (jika belum ada) di root proyek dengan isi:
            ```css
            @tailwind base;
            @tailwind components;
            @tailwind utilities;
            ```
        * Jalankan `npm run tailwind:build` (untuk kompilasi sekali) atau `npm run tailwind:watch` (untuk kompilasi otomatis saat ada perubahan). File `assets/css/style.css` akan dihasilkan.

4.  **Akses Aplikasi:**
    Buka browser Anda dan akses `http://localhost/cornerbites-sia/` (sesuai dengan konfigurasi server web Anda). Anda akan diarahkan ke halaman login.

## Catatan Penting

* **Keamanan:** Implementasi autentikasi di sini adalah dasar. Untuk aplikasi produksi, pertimbangkan untuk menambahkan fitur keamanan yang lebih robust (misalnya, CSRF tokens, rate limiting, dll.).
* **Pengelolaan Stok:** Logika stok saat ini sederhana (stok keluar saat penjualan). Untuk skenario yang lebih kompleks (misalnya, retur, penyesuaian stok), Anda perlu memperluas logika ini.
* **HPP & Laporan:** Perhitungan HPP dan laporan akuntansi di sini disederhanakan. Akuntansi yang lebih mendalam memerlukan metode seperti FIFO/LIFO/Average Costing dan penyesuaian laporan.
* **Error Handling:** Pesan error di beberapa tempat hanya ditampilkan ke pengguna. Di lingkungan produksi, error harus dicatat (logged) dan pesan yang ditampilkan ke pengguna harus lebih generik untuk mencegah kebocoran informasi.
* **URL Rewrite:** Jika Anda menggunakan `.htaccess`, pastikan `mod_rewrite` di Apache Anda aktif. Jika tidak, URL mungkin terlihat seperti `http://localhost/cornerbites-sia/pages/dashboard.php` daripada `http://localhost/cornerbites-sia/dashboard`.

---

Ini adalah struktur dan kode yang diperbarui dengan mempertimbangkan peran admin. Pastikan untuk membuat database dan mengisinya dengan user `admin` awal menggunakan password yang sudah di-hash. Selamat melanjutkan proyek An