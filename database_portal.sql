-- ============================================================
--  MIGRASI TAMBAHAN: PORTAL PUBLIK DESA
--  Jalankan file ini setelah database.sql sudah diimpor
--  FNA & Kawan-kawan — Universitas Sumatera Utara — 2025
-- ============================================================

USE sistem_desa;

-- ============================================================
--  TABEL PROFIL PUBLIK DESA
--  Setiap desa bisa punya foto sampul, visi-misi, sejarah, dll.
-- ============================================================
CREATE TABLE IF NOT EXISTS profil_desa (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_desa         INT UNSIGNED NOT NULL UNIQUE,
    slogan          VARCHAR(200) DEFAULT NULL COMMENT 'Tagline singkat desa',
    visi            TEXT DEFAULT NULL,
    misi            TEXT DEFAULT NULL,
    sejarah         TEXT DEFAULT NULL,
    foto_sampul     VARCHAR(255) DEFAULT NULL COMMENT 'Nama file foto di /uploads/profil/',
    foto_logo       VARCHAR(255) DEFAULT NULL COMMENT 'Nama file logo desa',
    warna_tema      VARCHAR(7) NOT NULL DEFAULT '#5B4FCF' COMMENT 'Warna HEX tema portal desa',
    luas_wilayah    DECIMAL(10,2) DEFAULT NULL COMMENT 'Hektar',
    jumlah_dusun    SMALLINT DEFAULT NULL,
    batas_utara     VARCHAR(100) DEFAULT NULL,
    batas_selatan   VARCHAR(100) DEFAULT NULL,
    batas_timur     VARCHAR(100) DEFAULT NULL,
    batas_barat     VARCHAR(100) DEFAULT NULL,
    telepon         VARCHAR(20) DEFAULT NULL,
    email           VARCHAR(100) DEFAULT NULL,
    website         VARCHAR(200) DEFAULT NULL,
    jam_layanan     VARCHAR(100) DEFAULT 'Senin–Jumat, 08.00–16.00 WIB',
    portal_aktif    TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0 = portal dinonaktifkan admin desa',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_desa) REFERENCES desa(id) ON DELETE CASCADE
) ENGINE=InnoDB COMMENT='Profil dan pengaturan portal publik desa';

-- ============================================================
--  TABEL BERITA / PENGUMUMAN DESA
-- ============================================================
CREATE TABLE IF NOT EXISTS berita (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_desa         INT UNSIGNED NOT NULL,
    id_penulis      INT UNSIGNED COMMENT 'FK ke pengguna',
    judul           VARCHAR(300) NOT NULL,
    slug            VARCHAR(320) NOT NULL COMMENT 'URL-friendly judul, unik per desa',
    ringkasan       VARCHAR(500) DEFAULT NULL COMMENT 'Cuplikan singkat untuk kartu berita',
    isi             LONGTEXT NOT NULL COMMENT 'Konten penuh berita (bisa HTML sederhana)',
    foto_utama      VARCHAR(255) DEFAULT NULL COMMENT 'Nama file foto di /uploads/berita/',
    kategori        ENUM('berita','pengumuman','agenda','pembangunan','sosial','lainnya') NOT NULL DEFAULT 'berita',
    status          ENUM('draft','terbit','arsip') NOT NULL DEFAULT 'draft',
    tampil_di_depan TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = tampil di slider/featured portal',
    views           INT UNSIGNED NOT NULL DEFAULT 0,
    tgl_terbit      DATETIME DEFAULT NULL COMMENT 'Waktu resmi tayang (bisa dijadwalkan)',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_slug_desa (id_desa, slug),
    FOREIGN KEY (id_desa)    REFERENCES desa(id)     ON DELETE CASCADE,
    FOREIGN KEY (id_penulis) REFERENCES pengguna(id) ON DELETE SET NULL,
    INDEX idx_status    (status),
    INDEX idx_kategori  (kategori),
    INDEX idx_desa_terbit (id_desa, tgl_terbit)
) ENGINE=InnoDB COMMENT='Berita dan pengumuman desa untuk portal publik';

-- ============================================================
--  DATA AWAL PROFIL DESA (untuk 3 desa contoh pertama)
-- ============================================================
INSERT IGNORE INTO profil_desa (id_desa, slogan, visi, misi, warna_tema, luas_wilayah, batas_utara, batas_selatan, batas_timur, batas_barat, jam_layanan, portal_aktif)
SELECT
    id,
    CONCAT('Desa ', nama_desa, ' — Maju, Sejahtera, dan Mandiri'),
    CONCAT('Terwujudnya ', nama_desa, ' yang maju, sejahtera, berkeadilan, dan berdaya saing berbasis kearifan lokal pada tahun 2029.'),
    CONCAT(
        '1. Meningkatkan kualitas pelayanan publik yang profesional dan transparan.\n',
        '2. Mengembangkan potensi ekonomi lokal dan pemberdayaan masyarakat.\n',
        '3. Membangun infrastruktur desa yang merata dan berkualitas.\n',
        '4. Memperkuat tata kelola pemerintahan desa yang bersih dan akuntabel.\n',
        '5. Meningkatkan kualitas sumber daya manusia melalui pendidikan dan kesehatan.'
    ),
    '#5B4FCF',
    125.50,
    CONCAT('Berbatasan dengan ', kecamatan),
    'Persawahan dan ladang penduduk',
    CONCAT('Jalan raya ', kecamatan),
    'Sungai dan kebun masyarakat',
    'Senin–Jumat, 08.00–16.00 WIB',
    1
FROM desa WHERE aktif = 1;

-- ============================================================
--  DATA BERITA CONTOH (untuk desa pertama)
-- ============================================================
INSERT IGNORE INTO berita (id_desa, id_penulis, judul, slug, ringkasan, isi, kategori, status, tampil_di_depan, views, tgl_terbit) VALUES
(
    1, 2,
    'Penyaluran BLT Dana Desa Tahap I Tahun 2024 Telah Dimulai',
    'penyaluran-blt-dana-desa-tahap-1-2024',
    'Pemerintah Desa telah resmi menyalurkan Bantuan Langsung Tunai (BLT) Dana Desa Tahap I Tahun Anggaran 2024 kepada seluruh Keluarga Penerima Manfaat (KPM) yang telah terverifikasi.',
    '<p>Pemerintah Desa dengan bangga mengumumkan bahwa penyaluran <strong>Bantuan Langsung Tunai (BLT) Dana Desa Tahap I</strong> Tahun Anggaran 2024 telah resmi dimulai pada tanggal 15 Januari 2024.</p>
<p>Sebanyak <strong>47 Keluarga Penerima Manfaat (KPM)</strong> yang telah terverifikasi dan tercatat dalam Data Terpadu Kesejahteraan Sosial (DTKS) berhak menerima bantuan ini. Setiap KPM menerima bantuan sebesar <strong>Rp 300.000 per bulan</strong> selama tiga bulan ke depan.</p>
<p>Kepala Desa menyampaikan bahwa proses penyaluran dilakukan secara langsung dan transparan, disaksikan oleh Badan Permusyawaratan Desa (BPD) dan pendamping desa. Warga yang berhak namun belum menerima dimohon untuk segera menghubungi kantor desa.</p>',
    'pengumuman', 'terbit', 1, 245, '2024-01-16 08:00:00'
),
(
    1, 2,
    'Pembangunan Jalan Desa Sepanjang 1,2 KM Resmi Dimulai',
    'pembangunan-jalan-desa-1-2-km-dimulai',
    'Proyek pembangunan jalan desa yang telah lama dinantikan warga akhirnya resmi dimulai. Pekerjaan ditargetkan rampung dalam waktu 60 hari kalender.',
    '<p>Setelah melalui proses perencanaan yang matang dan dianggarkan dalam APBDes 2024, proyek <strong>pembangunan jalan desa sepanjang 1,2 kilometer</strong> di wilayah RT 003/RW 001 resmi dimulai pada Senin, 5 Februari 2024.</p>
<p>Pembangunan ini menggunakan Dana Desa dengan total anggaran <strong>Rp 180.000.000</strong> dan dikerjakan secara swakelola oleh masyarakat desa sendiri melalui mekanisme Padat Karya Tunai Desa (PKTD).</p>
<p>Kepala Desa berharap proyek ini dapat meningkatkan aksesibilitas warga, memperlancar kegiatan ekonomi, dan meningkatkan nilai lahan di sekitar area pembangunan. Pengawasan dilakukan secara ketat oleh tim pengawas desa dan pendamping kecamatan.</p>',
    'pembangunan', 'terbit', 1, 189, '2024-02-06 09:00:00'
),
(
    1, 2,
    'Jadwal Posyandu Balita dan Lansia Bulan Februari 2024',
    'jadwal-posyandu-februari-2024',
    'Pemerintah Desa mengumumkan jadwal pelaksanaan Posyandu Balita dan Posyandu Lansia untuk bulan Februari 2024. Seluruh warga diharap hadir tepat waktu.',
    '<p>Pemerintah Desa bersama kader Posyandu mengumumkan jadwal kegiatan <strong>Posyandu Balita dan Posyandu Lansia</strong> untuk bulan Februari 2024 sebagai berikut:</p>
<ul>
<li><strong>Posyandu Balita:</strong> Kamis, 8 Februari 2024 — Pukul 08.00–11.00 WIB — di Balai Desa</li>
<li><strong>Posyandu Lansia:</strong> Jumat, 9 Februari 2024 — Pukul 08.00–11.00 WIB — di Balai Desa</li>
</ul>
<p>Kegiatan ini meliputi penimbangan berat badan, pengukuran tinggi badan, pemeriksaan tekanan darah, dan pemberian vitamin/obat-obatan gratis. Peserta diharapkan membawa buku KIA/KMS dan datang tepat waktu.</p>',
    'sosial', 'terbit', 0, 98, '2024-02-01 07:00:00'
),
(
    1, 2,
    'Pelatihan Kewirausahaan Digital untuk Ibu-Ibu PKK',
    'pelatihan-kewirausahaan-digital-pkk',
    'Desa bekerja sama dengan Dinas Pemberdayaan Masyarakat menyelenggarakan pelatihan kewirausahaan berbasis digital untuk 30 anggota PKK desa.',
    '<p>Dalam upaya meningkatkan kapasitas ekonomi perempuan desa, Pemerintah Desa bekerja sama dengan Dinas Pemberdayaan Masyarakat dan Desa (PMD) Kabupaten menyelenggarakan <strong>Pelatihan Kewirausahaan Digital</strong> selama dua hari penuh.</p>
<p>Sebanyak <strong>30 ibu-ibu anggota PKK</strong> desa mengikuti pelatihan ini dan mendapatkan materi tentang cara berjualan secara online melalui platform marketplace, pembuatan konten produk yang menarik, serta manajemen keuangan usaha sederhana.</p>
<p>Program ini diharapkan dapat mendorong tumbuhnya usaha mikro berbasis rumah tangga di desa, sekaligus memanfaatkan potensi produk-produk lokal unggulan desa untuk dipasarkan secara lebih luas.</p>',
    'sosial', 'terbit', 0, 134, '2024-03-12 10:00:00'
);
