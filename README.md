# SiDesa — Sistem Informasi Warga Desa

```
 ____  _ ____
/ ___|(_)  _ \  ___  ___  __ _
\___ \| | | | |/ _ \/ __|/ _` |
 ___) | | |_| |  __/\__ \ (_| |
|____/|_|____/ \___||___/\__,_|

FNA & Kawan-kawan
(Fauzan Nur Ahmadi & Kawan-kawan)
Universitas Sumatera Utara — Medan
```

> **Dibuat oleh FNA & Kawan-kawan** · Gratis · Open Source · Bebas digunakan oleh siapa saja

---

## Tentang Aplikasi

**SiDesa** adalah sistem informasi manajemen data warga desa berbasis web yang berjalan secara lokal menggunakan XAMPP (PHP + MySQL). Aplikasi ini dirancang untuk membantu petugas desa dalam mengelola data kependudukan secara efisien, akurat, dan terorganisir — tanpa biaya, tanpa koneksi internet, dan tanpa ketergantungan pada server pihak ketiga.

Aplikasi ini mendukung **banyak desa sekaligus** dalam satu instalasi, lengkap dengan sistem peran pengguna yang membatasi akses sesuai kebutuhan organisasi.

---

## Fitur Utama

- **Multi-Desa** — Satu instalasi dapat mengelola banyak desa sekaligus
- **Manajemen Warga** — Tambah, edit, hapus, dan cari data warga dengan filter lengkap
- **Statistik Demografis** — Distribusi usia, agama, pendidikan, pekerjaan, dan jenis kelamin secara visual
- **Ekspor Excel** — Unduh data warga dalam format CSV yang kompatibel dengan Microsoft Excel
- **Cetak PDF** — Laporan demografis dan daftar warga siap cetak / simpan PDF langsung dari browser
- **Sistem Peran** — Superadmin (akses semua desa), Admin Desa, dan Operator
- **Log Aktivitas** — Setiap perubahan data tercatat secara otomatis
- **Antarmuka Modern** — Desain responsif, nyaman digunakan di desktop maupun tablet

---

## Persyaratan Sistem

| Komponen | Versi Minimum |
|---|---|
| XAMPP | 7.4+ |
| PHP | 7.4+ |
| MySQL / MariaDB | 5.7+ / 10.3+ |
| Browser | Chrome 80+, Firefox 75+, Edge 80+ |
| OS | Windows 7/10/11, Linux, macOS |

---

## Cara Instalasi

### Langkah 1 — Siapkan XAMPP
Pastikan XAMPP sudah terinstal dan layanan **Apache** serta **MySQL** sudah berjalan.

### Langkah 2 — Salin Folder Aplikasi
Ekstrak atau salin folder `sistem_desa` ke dalam direktori:
```
C:\xampp\htdocs\sistem_desa\
```

### Langkah 3 — Import Database
1. Buka browser, akses **http://localhost/phpmyadmin**
2. Klik tab **Import**
3. Pilih file `sistem_desa/database.sql`
4. Klik **Go** / **Impor**

Database `sistem_desa` akan dibuat otomatis lengkap dengan 3 desa contoh dan akun pengguna demo.

### Langkah 4 — Jalankan Aplikasi
Buka browser dan akses:
```
http://localhost/sistem_desa/
```

### Langkah 5 — Login

| Username | Password | Peran |
|---|---|---|
| `superadmin` | `password` | Super Administrator (akses semua desa) |
| `admin_tanjung` | `password` | Admin Desa Tanjung Mulia |
| `admin_mencirim` | `password` | Admin Desa Sei Mencirim |

> ⚠️ **Penting:** Segera ganti password setelah login pertama kali melalui pengaturan profil.

---

## Struktur Folder

```
sistem_desa/
├── assets/
│   ├── css/
│   │   └── style.css          ← Stylesheet utama
│   └── js/
│       └── app.js             ← JavaScript utama
├── includes/
│   ├── config.php             ← Konfigurasi database
│   ├── auth.php               ← Sistem autentikasi & sesi
│   ├── header.php             ← Template header + sidebar
│   └── footer.php             ← Template footer
├── modules/
│   ├── warga/
│   │   ├── index.php          ← Daftar warga
│   │   ├── form.php           ← Tambah / edit warga
│   │   ├── detail.php         ← Detail warga
│   │   └── hapus.php          ← Hapus warga
│   ├── desa/
│   │   └── index.php          ← Manajemen desa
│   └── laporan/
│       ├── index.php          ← Statistik & laporan
│       ├── cetak_laporan.php  ← Cetak laporan demografis (PDF)
│       ├── cetak_warga.php    ← Cetak daftar warga (PDF)
│       └── export_excel.php   ← Ekspor data ke Excel/CSV
├── uploads/                   ← Folder upload foto (buat manual jika perlu)
├── index.php                  ← Dashboard utama
├── login.php                  ← Halaman login
├── logout.php                 ← Proses logout
└── database.sql               ← Skema & data awal database
```

---

## Konfigurasi Database

Jika konfigurasi MySQL XAMPP Anda berbeda dari default, edit file `includes/config.php`:

```php
define('DB_HOST', 'localhost');  // Host database
define('DB_USER', 'root');       // Username MySQL
define('DB_PASS', '');           // Password MySQL (default XAMPP kosong)
define('DB_NAME', 'sistem_desa'); // Nama database
```

---

## Panduan Penggunaan Cepat

### Menambah Desa Baru
Login sebagai `superadmin` → menu **Manajemen Desa** → isi form di sisi kiri → klik **Tambah Desa**.

### Menambah Warga
Menu **Data Warga** → klik tombol **Tambah Warga** → isi form lengkap → klik **Simpan Warga**.

### Ekspor Data ke Excel
Menu **Data Warga** → terapkan filter jika diperlukan → klik tombol **Export Excel**. File CSV akan diunduh dan dapat dibuka langsung di Microsoft Excel.

### Mencetak Laporan PDF
Menu **Laporan & Statistik** → pilih desa (opsional) → klik **Cetak Laporan** → di jendela baru, pilih **Cetak / Simpan PDF** → di dialog cetak browser, pilih printer **"Microsoft Print to PDF"** atau **"Save as PDF"**.

---

## Hak Cipta & Lisensi

```
SiDesa — Sistem Informasi Warga Desa
Copyright (c) 2025 FNA & Kawan-kawan
(Fauzan Nur Ahmadi & Kawan-kawan)
Universitas Sumatera Utara, Medan, Indonesia

Aplikasi ini didistribusikan secara GRATIS dan OPEN SOURCE.
Siapa saja diperbolehkan menggunakan, memodifikasi, dan mendistribusikan
ulang aplikasi ini dengan syarat mencantumkan atribusi kepada pembuat asli:

    "Dikembangkan berdasarkan SiDesa oleh FNA & Kawan-kawan
     (Fauzan Nur Ahmadi & Kawan-kawan), Universitas Sumatera Utara"
```

Lisensi: **MIT License** — bebas digunakan untuk keperluan apapun, termasuk komersial, selama mencantumkan atribusi.

---

## Kontribusi

Kontribusi sangat disambut. Silakan fork repositori ini, buat branch fitur baru, dan ajukan pull request. Untuk melaporkan bug atau mengusulkan fitur, gunakan fitur **Issues** di GitHub.

---

## Kontak

**Fauzan Nur Ahmadi**
Universitas Sumatera Utara — Medan
- GitHub: github.com/fauzan-usu
- Email: fauzan.nurahmadi@usu.ac.id

---

*Dibuat dengan ❤️ untuk kemajuan tata kelola desa di Indonesia.*
*FNA & Kawan-kawan · <?= date('Y') ?>*
