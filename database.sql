-- ============================================================
--  SISTEM INFORMASI WARGA DESA
--  Dibuat untuk XAMPP (MySQL 5.7+ / MariaDB 10.3+)
-- ============================================================

CREATE DATABASE IF NOT EXISTS sistem_desa
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE sistem_desa;

-- ============================================================
--  TABEL DESA
-- ============================================================
CREATE TABLE IF NOT EXISTS desa (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode_desa       VARCHAR(20)  NOT NULL UNIQUE COMMENT 'Kode unik desa misal: DSA-001',
    nama_desa       VARCHAR(100) NOT NULL,
    kecamatan       VARCHAR(100) NOT NULL,
    kabupaten       VARCHAR(100) NOT NULL,
    provinsi        VARCHAR(100) NOT NULL DEFAULT 'Sumatera Utara',
    kode_pos        VARCHAR(10),
    kepala_desa     VARCHAR(100),
    luas_wilayah    DECIMAL(10,2) COMMENT 'Dalam hektar',
    latitude        DECIMAL(10,8),
    longitude       DECIMAL(11,8),
    deskripsi       TEXT,
    aktif           TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_kecamatan (kecamatan),
    INDEX idx_aktif (aktif)
) ENGINE=InnoDB COMMENT='Master data desa';

-- ============================================================
--  TABEL WARGA
-- ============================================================
CREATE TABLE IF NOT EXISTS warga (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_desa         INT UNSIGNED NOT NULL,
    nik             CHAR(16) NOT NULL UNIQUE COMMENT 'Nomor Induk Kependudukan 16 digit',
    no_kk           CHAR(16) NOT NULL COMMENT 'Nomor Kartu Keluarga',
    nama_lengkap    VARCHAR(150) NOT NULL,
    tempat_lahir    VARCHAR(100) NOT NULL,
    tanggal_lahir   DATE NOT NULL,
    jenis_kelamin   ENUM('L','P') NOT NULL COMMENT 'L=Laki-laki, P=Perempuan',
    agama           ENUM('Islam','Kristen Protestan','Kristen Katolik','Hindu','Budha','Konghucu','Lainnya') NOT NULL,
    status_kawin    ENUM('Belum Kawin','Kawin','Cerai Hidup','Cerai Mati') NOT NULL DEFAULT 'Belum Kawin',
    pendidikan      ENUM('Tidak/Belum Sekolah','SD/Sederajat','SMP/Sederajat','SMA/Sederajat','D1/D2/D3','S1','S2','S3') NOT NULL DEFAULT 'Tidak/Belum Sekolah',
    pekerjaan       VARCHAR(100),
    alamat          TEXT NOT NULL,
    rt              VARCHAR(5),
    rw              VARCHAR(5),
    no_telepon      VARCHAR(20),
    status_hidup    ENUM('Hidup','Meninggal','Pindah') NOT NULL DEFAULT 'Hidup',
    status_dtks     TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=termasuk Data Terpadu Kesejahteraan Sosial',
    foto            VARCHAR(255) COMMENT 'Path file foto KTP',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_desa) REFERENCES desa(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_desa (id_desa),
    INDEX idx_nik (nik),
    INDEX idx_kk (no_kk),
    INDEX idx_nama (nama_lengkap),
    INDEX idx_status (status_hidup)
) ENGINE=InnoDB COMMENT='Data warga desa';

-- ============================================================
--  TABEL PENGGUNA / USER
-- ============================================================
CREATE TABLE IF NOT EXISTS pengguna (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_desa         INT UNSIGNED COMMENT 'NULL = admin pusat (akses semua desa)',
    username        VARCHAR(50) NOT NULL UNIQUE,
    password        VARCHAR(255) NOT NULL COMMENT 'bcrypt hash',
    nama_lengkap    VARCHAR(150) NOT NULL,
    email           VARCHAR(150),
    peran           ENUM('superadmin','admin_desa','operator') NOT NULL DEFAULT 'operator',
    aktif           TINYINT(1) NOT NULL DEFAULT 1,
    last_login      TIMESTAMP NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_desa) REFERENCES desa(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB COMMENT='Akun pengguna sistem';

-- ============================================================
--  TABEL LOG AKTIVITAS
-- ============================================================
CREATE TABLE IF NOT EXISTS log_aktivitas (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_pengguna     INT UNSIGNED,
    aksi            VARCHAR(100) NOT NULL,
    tabel_terkait   VARCHAR(50),
    id_record       INT UNSIGNED,
    keterangan      TEXT,
    ip_address      VARCHAR(45),
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pengguna (id_pengguna),
    INDEX idx_created (created_at)
) ENGINE=InnoDB COMMENT='Log audit aktivitas pengguna';

-- ============================================================
--  DATA AWAL: 3 DESA CONTOH
-- ============================================================
INSERT INTO desa (kode_desa, nama_desa, kecamatan, kabupaten, provinsi, kode_pos, kepala_desa, luas_wilayah) VALUES
('DSA-001', 'Desa Tanjung Mulia', 'Medan Deli',    'Kota Medan',  'Sumatera Utara', '20241', 'Bapak Sudirman',  125.50),
('DSA-002', 'Desa Sei Mencirim', 'Sunggal',        'Deli Serdang', 'Sumatera Utara', '20351', 'Bapak Harahap',   98.75),
('DSA-003', 'Desa Namo Bintang', 'Pancur Batu',    'Deli Serdang', 'Sumatera Utara', '20353', 'Bapak Situmorang',210.00);

-- ============================================================
--  AKUN ADMIN AWAL (password: admin123)
-- ============================================================
INSERT INTO pengguna (id_desa, username, password, nama_lengkap, email, peran) VALUES
(NULL,  'superadmin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super Administrator', 'admin@desaku.id', 'superadmin'),
(1,     'admin_tanjung', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin Tanjung Mulia', 'tanjung@desaku.id', 'admin_desa'),
(2,     'admin_mencirim', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin Sei Mencirim',  'mencirim@desaku.id', 'admin_desa');

-- ============================================================
--  DATA WARGA CONTOH (10 warga untuk desa 1)
-- ============================================================
INSERT INTO warga (id_desa, nik, no_kk, nama_lengkap, tempat_lahir, tanggal_lahir, jenis_kelamin, agama, status_kawin, pendidikan, pekerjaan, alamat, rt, rw, status_hidup) VALUES
(1,'1271010101900001','1271011234560001','Ahmad Fauzi','Medan','1990-01-01','L','Islam','Kawin','S1','PNS','Jl. Tanjung No. 1','001','001','Hidup'),
(1,'1271010202880002','1271011234560001','Siti Rahmah','Medan','1988-02-02','P','Islam','Kawin','S1','Guru','Jl. Tanjung No. 1','001','001','Hidup'),
(1,'1271010303920003','1271021234560002','Budi Santoso','Binjai','1992-03-03','L','Islam','Belum Kawin','SMA/Sederajat','Petani','Jl. Mawar No. 5','001','002','Hidup'),
(1,'1271010404850004','1271021234560002','Dewi Lestari','Tebing Tinggi','1985-04-04','P','Kristen Protestan','Kawin','D1/D2/D3','Pedagang','Jl. Kenanga No. 8','002','001','Hidup'),
(1,'1271010505950005','1271031234560003','Rizky Pratama','Medan','1995-05-05','L','Islam','Belum Kawin','S1','Mahasiswa','Jl. Melati No. 3','002','001','Hidup'),
(1,'1271010606750006','1271031234560003','Hasan Basri','Pematang Siantar','1975-06-06','L','Islam','Kawin','SMP/Sederajat','Nelayan','Jl. Nelayan No. 10','003','001','Hidup'),
(1,'1271010707820007','1271041234560004','Maria Sinaga','Balige','1982-07-07','P','Kristen Katolik','Kawin','SMA/Sederajat','Ibu Rumah Tangga','Jl. Batak No. 2','003','002','Hidup'),
(1,'1271010808600008','1271041234560004','Simson Hutapea','Medan','1960-08-08','L','Kristen Protestan','Kawin','SD/Sederajat','Pensiunan','Jl. Batak No. 2','003','002','Hidup'),
(1,'1271010909980009','1271051234560005','Nur Azizah','Medan','1998-09-09','P','Islam','Belum Kawin','SMA/Sederajat','Karyawan Swasta','Jl. Anggrek No. 7','004','001','Hidup'),
(1,'1271011010700010','1271051234560005','Pak Tua Sirait','Tarutung','1970-10-10','L','Kristen Protestan','Cerai Mati','SD/Sederajat','Petani','Jl. Anggrek No. 7','004','001','Hidup');
