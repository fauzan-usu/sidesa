-- ============================================================
--  MIGRASI: INTEGRASI SIAK KEMENDAGRI
--  Jalankan setelah database.sql dan database_portal.sql
--  FNA & Kawan-kawan — Universitas Sumatera Utara — 2025
-- ============================================================

USE sistem_desa;

-- ============================================================
--  TABEL WILAYAH KEMENDAGRI
--  Sumber: API e-database.kemendagri.go.id / kode wilayah resmi
--  Digunakan untuk memastikan kode desa sesuai standar nasional
-- ============================================================
CREATE TABLE IF NOT EXISTS wilayah_provinsi (
    kode    VARCHAR(10) NOT NULL PRIMARY KEY,
    nama    VARCHAR(100) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB COMMENT='Referensi provinsi dari Kemendagri';

CREATE TABLE IF NOT EXISTS wilayah_kabupaten (
    kode         VARCHAR(10) NOT NULL PRIMARY KEY,
    kode_provinsi VARCHAR(10) NOT NULL,
    nama         VARCHAR(100) NOT NULL,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_prov (kode_provinsi)
) ENGINE=InnoDB COMMENT='Referensi kabupaten/kota dari Kemendagri';

CREATE TABLE IF NOT EXISTS wilayah_kecamatan (
    kode          VARCHAR(10) NOT NULL PRIMARY KEY,
    kode_kabupaten VARCHAR(10) NOT NULL,
    nama          VARCHAR(100) NOT NULL,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_kab (kode_kabupaten)
) ENGINE=InnoDB COMMENT='Referensi kecamatan dari Kemendagri';

CREATE TABLE IF NOT EXISTS wilayah_desa (
    kode           VARCHAR(15) NOT NULL PRIMARY KEY,
    kode_kecamatan VARCHAR(10) NOT NULL,
    nama           VARCHAR(100) NOT NULL,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_kec (kode_kecamatan)
) ENGINE=InnoDB COMMENT='Referensi desa/kelurahan dari Kemendagri';

-- ============================================================
--  TABEL KONFIGURASI SIAK PER DESA
--  Setiap desa bisa punya kredensial SIAK sendiri (dari MOU)
-- ============================================================
CREATE TABLE IF NOT EXISTS siak_config (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_desa        INT UNSIGNED NOT NULL UNIQUE,
    -- Kredensial API Dukcapil (diisi setelah MOU disetujui)
    client_id      VARCHAR(200) DEFAULT NULL COMMENT 'Client ID dari Dukcapil setelah MOU',
    client_secret  VARCHAR(500) DEFAULT NULL COMMENT 'Secret key terenkripsi',
    api_url        VARCHAR(300) DEFAULT 'https://layananonline.dukcapil.kemendagri.go.id/api/v1',
    -- Kode wilayah resmi Kemendagri
    kode_provinsi  VARCHAR(10) DEFAULT NULL,
    kode_kabupaten VARCHAR(10) DEFAULT NULL,
    kode_kecamatan VARCHAR(10) DEFAULT NULL,
    kode_desa_kemendagri VARCHAR(15) DEFAULT NULL,
    -- Mode operasi
    mode           ENUM('simulasi','aktif','nonaktif') NOT NULL DEFAULT 'simulasi'
                   COMMENT 'simulasi=testing tanpa koneksi nyata, aktif=pakai API sungguhan',
    verifikasi_aktif TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=verifikasi NIK otomatis saat input warga',
    -- Status MOU
    status_mou     ENUM('belum_diajukan','dalam_proses','disetujui','ditolak') NOT NULL DEFAULT 'belum_diajukan',
    tgl_mou        DATE DEFAULT NULL,
    nomor_mou      VARCHAR(100) DEFAULT NULL,
    catatan        TEXT DEFAULT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_desa) REFERENCES desa(id) ON DELETE CASCADE
) ENGINE=InnoDB COMMENT='Konfigurasi integrasi SIAK per desa';

-- ============================================================
--  LOG VERIFIKASI NIK
--  Setiap pemanggilan API dicatat untuk audit dan monitoring
-- ============================================================
CREATE TABLE IF NOT EXISTS siak_log_verifikasi (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_desa        INT UNSIGNED NOT NULL,
    id_pengguna    INT UNSIGNED,
    id_warga       INT UNSIGNED COMMENT 'NULL jika verifikasi sebelum simpan',
    nik_input      CHAR(16) NOT NULL,
    nama_input     VARCHAR(150),
    tgl_lahir_input DATE,
    -- Respons dari API
    status_respons ENUM('sesuai','tidak_sesuai','tidak_ditemukan','error','simulasi') NOT NULL,
    kode_respons   VARCHAR(20),
    pesan_respons  TEXT,
    durasi_ms      SMALLINT UNSIGNED COMMENT 'Waktu respons dalam milidetik',
    -- Meta
    mode           ENUM('simulasi','aktif') NOT NULL DEFAULT 'simulasi',
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_desa (id_desa),
    INDEX idx_nik  (nik_input),
    INDEX idx_created (created_at)
) ENGINE=InnoDB COMMENT='Log setiap verifikasi NIK ke SIAK Dukcapil';

-- ============================================================
--  KOLOM TAMBAHAN DI TABEL WARGA
--  Untuk menyimpan kode wilayah resmi Kemendagri dan status verifikasi
-- ============================================================
ALTER TABLE warga
    ADD COLUMN kode_wilayah_kemendagri VARCHAR(15) DEFAULT NULL
        COMMENT 'Kode desa/kel resmi Kemendagri dari tabel wilayah_desa'
        AFTER id_desa,
    ADD COLUMN siak_verified TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1=NIK sudah diverifikasi ke SIAK Dukcapil'
        AFTER foto,
    ADD COLUMN siak_verified_at TIMESTAMP NULL DEFAULT NULL
        COMMENT 'Waktu terakhir verifikasi NIK ke SIAK'
        AFTER siak_verified,
    ADD COLUMN siak_log_id BIGINT UNSIGNED DEFAULT NULL
        COMMENT 'FK ke siak_log_verifikasi terakhir'
        AFTER siak_verified_at;

-- ============================================================
--  INISIALISASI KONFIGURASI SIAK UNTUK DESA YANG ADA
-- ============================================================
INSERT IGNORE INTO siak_config (id_desa, mode, verifikasi_aktif, status_mou)
SELECT id, 'simulasi', 0, 'belum_diajukan' FROM desa WHERE aktif = 1;
