<?php
/**
 * SIAK CLIENT — ENGINE INTEGRASI DUKCAPIL KEMENDAGRI
 * ====================================================
 * Menangani semua komunikasi dengan:
 *   1. API Wilayah Kemendagri (publik, tanpa MOU)
 *   2. API Verifikasi NIK Dukcapil (butuh MOU)
 *   3. Mode Simulasi untuk testing/pengembangan
 *
 * Dasar hukum:
 *   - UU No. 24 Tahun 2013 tentang Administrasi Kependudukan
 *   - Permendagri No. 102 Tahun 2019 tentang Hak Akses Data Kependudukan
 *
 * FNA & Kawan-kawan — Universitas Sumatera Utara — 2025
 */

class SiakClient
{
    // ── Endpoint resmi Kemendagri ────────────────────────────
    const URL_WILAYAH_PROVINSI   = 'https://www.emsifa.com/api-wilayah-indonesia/api/provinces.json';
    const URL_WILAYAH_KABUPATEN  = 'https://www.emsifa.com/api-wilayah-indonesia/api/regencies/%s.json';
    const URL_WILAYAH_KECAMATAN  = 'https://www.emsifa.com/api-wilayah-indonesia/api/districts/%s.json';
    const URL_WILAYAH_DESA       = 'https://www.emsifa.com/api-wilayah-indonesia/api/villages/%s.json';

    // Endpoint resmi SIAK Dukcapil (aktif setelah MOU disetujui)
    const URL_SIAK_VERIFIKASI    = 'https://layananonline.dukcapil.kemendagri.go.id/api/v1/verifikasi-nik';
    const URL_SIAK_TOKEN         = 'https://layananonline.dukcapil.kemendagri.go.id/oauth/token';

    const TIMEOUT_DETIK = 15;

    private array  $config;
    private string $mode;
    private ?PDO   $db;

    public function __construct(array $config, PDO $db)
    {
        $this->config = $config;
        $this->mode   = $config['mode'] ?? 'simulasi';
        $this->db     = $db;
    }

    // ════════════════════════════════════════════════════════
    //  BAGIAN 1: API WILAYAH KEMENDAGRI (PUBLIK)
    // ════════════════════════════════════════════════════════

    /**
     * Ambil daftar provinsi dari API wilayah Indonesia
     * Sumber: emsifa.com (mirror resmi data Kemendagri)
     * Tidak memerlukan MOU, bisa langsung digunakan
     */
    public function getProvinsi(): array
    {
        // Cek cache di database dulu
        $cached = $this->db->query("SELECT kode, nama FROM wilayah_provinsi ORDER BY nama")->fetchAll();
        if (!empty($cached)) return $cached;

        // Fetch dari API
        $data = $this->httpGet(self::URL_WILAYAH_PROVINSI);
        if (empty($data)) return [];

        // Simpan ke database sebagai cache
        $stmt = $this->db->prepare("INSERT IGNORE INTO wilayah_provinsi (kode, nama) VALUES (?,?)");
        foreach ($data as $item) {
            $stmt->execute([$item['id'], $item['name']]);
        }

        return $this->db->query("SELECT kode, nama FROM wilayah_provinsi ORDER BY nama")->fetchAll();
    }

    public function getKabupaten(string $kodeProvinsi): array
    {
        $cached = $this->db->prepare("SELECT kode, nama FROM wilayah_kabupaten WHERE kode_provinsi=? ORDER BY nama");
        $cached->execute([$kodeProvinsi]);
        $rows = $cached->fetchAll();
        if (!empty($rows)) return $rows;

        $url  = sprintf(self::URL_WILAYAH_KABUPATEN, $kodeProvinsi);
        $data = $this->httpGet($url);
        if (empty($data)) return [];

        $stmt = $this->db->prepare("INSERT IGNORE INTO wilayah_kabupaten (kode, kode_provinsi, nama) VALUES (?,?,?)");
        foreach ($data as $item) {
            $stmt->execute([$item['id'], $kodeProvinsi, $item['name']]);
        }

        $result = $this->db->prepare("SELECT kode, nama FROM wilayah_kabupaten WHERE kode_provinsi=? ORDER BY nama");
        $result->execute([$kodeProvinsi]);
        return $result->fetchAll();
    }

    public function getKecamatan(string $kodeKabupaten): array
    {
        $cached = $this->db->prepare("SELECT kode, nama FROM wilayah_kecamatan WHERE kode_kabupaten=? ORDER BY nama");
        $cached->execute([$kodeKabupaten]);
        $rows = $cached->fetchAll();
        if (!empty($rows)) return $rows;

        $url  = sprintf(self::URL_WILAYAH_KECAMATAN, $kodeKabupaten);
        $data = $this->httpGet($url);
        if (empty($data)) return [];

        $stmt = $this->db->prepare("INSERT IGNORE INTO wilayah_kecamatan (kode, kode_kabupaten, nama) VALUES (?,?,?)");
        foreach ($data as $item) {
            $stmt->execute([$item['id'], $kodeKabupaten, $item['name']]);
        }

        $result = $this->db->prepare("SELECT kode, nama FROM wilayah_kecamatan WHERE kode_kabupaten=? ORDER BY nama");
        $result->execute([$kodeKabupaten]);
        return $result->fetchAll();
    }

    public function getDesaKelurahan(string $kodeKecamatan): array
    {
        $cached = $this->db->prepare("SELECT kode, nama FROM wilayah_desa WHERE kode_kecamatan=? ORDER BY nama");
        $cached->execute([$kodeKecamatan]);
        $rows = $cached->fetchAll();
        if (!empty($rows)) return $rows;

        $url  = sprintf(self::URL_WILAYAH_DESA, $kodeKecamatan);
        $data = $this->httpGet($url);
        if (empty($data)) return [];

        $stmt = $this->db->prepare("INSERT IGNORE INTO wilayah_desa (kode, kode_kecamatan, nama) VALUES (?,?,?)");
        foreach ($data as $item) {
            $stmt->execute([$item['id'], $kodeKecamatan, $item['name']]);
        }

        $result = $this->db->prepare("SELECT kode, nama FROM wilayah_desa WHERE kode_kecamatan=? ORDER BY nama");
        $result->execute([$kodeKecamatan]);
        return $result->fetchAll();
    }

    /**
     * Cari nama desa/kecamatan berdasarkan kode wilayah Kemendagri
     */
    public function getNamaWilayah(string $kode): string
    {
        $len = strlen($kode);
        if ($len <= 2) {
            $r = $this->db->prepare("SELECT nama FROM wilayah_provinsi WHERE kode=?");
        } elseif ($len <= 5) {
            $r = $this->db->prepare("SELECT nama FROM wilayah_kabupaten WHERE kode=?");
        } elseif ($len <= 8) {
            $r = $this->db->prepare("SELECT nama FROM wilayah_kecamatan WHERE kode=?");
        } else {
            $r = $this->db->prepare("SELECT nama FROM wilayah_desa WHERE kode=?");
        }
        $r->execute([$kode]);
        return $r->fetchColumn() ?: $kode;
    }

    // ════════════════════════════════════════════════════════
    //  BAGIAN 2: VERIFIKASI NIK KE SIAK DUKCAPIL
    // ════════════════════════════════════════════════════════

    /**
     * Verifikasi NIK ke SIAK Kemendagri
     *
     * Mode SIMULASI: tidak ada koneksi ke server manapun,
     *   hanya validasi format NIK dan menghasilkan respons simulasi
     *   berdasarkan struktur NIK (kode wilayah dari 6 digit pertama)
     *
     * Mode AKTIF: menghubungi API Dukcapil yang sebenarnya
     *   (memerlukan client_id dan client_secret dari MOU)
     *
     * @param string $nik          16 digit NIK
     * @param string $namaLengkap  Nama sesuai KTP
     * @param string $tanggalLahir Format YYYY-MM-DD
     * @param int    $idDesa       ID desa untuk logging
     * @param int    $idPengguna   ID pengguna yang melakukan verifikasi
     * @param int    $idWarga      ID warga (0 jika belum tersimpan)
     */
    public function verifikasiNIK(
        string $nik,
        string $namaLengkap,
        string $tanggalLahir,
        int $idDesa,
        int $idPengguna = 0,
        int $idWarga = 0
    ): array {
        $mulai = microtime(true);

        // Validasi format NIK dasar
        if (strlen($nik) !== 16 || !ctype_digit($nik)) {
            return $this->simpanLogDanReturn($idDesa, $idPengguna, $idWarga, $nik, $namaLengkap, $tanggalLahir, [
                'status'  => 'error',
                'kode'    => 'FORMAT_INVALID',
                'pesan'   => 'NIK harus terdiri dari 16 digit angka.',
                'detail'  => null,
            ], 0);
        }

        if ($this->mode === 'simulasi') {
            return $this->verifikasiSimulasi($nik, $namaLengkap, $tanggalLahir, $idDesa, $idPengguna, $idWarga, $mulai);
        }

        return $this->verifikasiAktif($nik, $namaLengkap, $tanggalLahir, $idDesa, $idPengguna, $idWarga, $mulai);
    }

    /**
     * MODE SIMULASI — tidak menghubungi server Dukcapil
     * Memvalidasi berdasarkan:
     *   1. Format 16 digit
     *   2. Kode wilayah 6 digit pertama (harus ada di tabel wilayah_desa)
     *   3. Digit ke-7: kode jenis kelamin & tanggal lahir
     */
    private function verifikasiSimulasi(
        string $nik, string $nama, string $tgl,
        int $idDesa, int $idPengguna, int $idWarga, float $mulai
    ): array {
        // Ekstrak informasi dari struktur NIK
        $kodeWilayah = substr($nik, 0, 6);
        $tglKode     = substr($nik, 6, 6); // DDMMYY
        $dd          = (int)substr($tglKode, 0, 2);
        $mm          = (int)substr($tglKode, 2, 2);
        $yy          = (int)substr($tglKode, 4, 2);

        // Perempuan: tanggal + 40
        $isPerempuan = $dd > 40;
        $ddAsli      = $isPerempuan ? $dd - 40 : $dd;

        // Validasi bulan
        if ($mm < 1 || $mm > 12) {
            $hasil = ['status'=>'error','kode'=>'NIK_INVALID','pesan'=>'Struktur NIK tidak valid (kode bulan tidak sesuai).','detail'=>null];
            return $this->simpanLogDanReturn($idDesa,$idPengguna,$idWarga,$nik,$nama,$tgl,$hasil,
                (int)((microtime(true)-$mulai)*1000));
        }

        // Validasi tanggal
        if ($ddAsli < 1 || $ddAsli > 31) {
            $hasil = ['status'=>'error','kode'=>'NIK_INVALID','pesan'=>'Struktur NIK tidak valid (kode tanggal tidak sesuai).','detail'=>null];
            return $this->simpanLogDanReturn($idDesa,$idPengguna,$idWarga,$nik,$nama,$tgl,$hasil,
                (int)((microtime(true)-$mulai)*1000));
        }

        // Cek kode wilayah — apakah ada di database wilayah Kemendagri?
        $cekWilayah = $this->db->prepare("SELECT nama FROM wilayah_desa WHERE kode LIKE ?");
        $cekWilayah->execute([$kodeWilayah . '%']);
        $namaWilayah = $cekWilayah->fetchColumn();

        // Cocokkan tanggal lahir dari NIK vs input
        $tahunLengkap = $yy >= 0 && $yy <= date('y') ? '20'.$yy : '19'.$yy;
        $tglDariNIK   = sprintf('%04d-%02d-%02d', $tahunLengkap, $mm, $ddAsli);
        $cocokTgl     = ($tgl && $tglDariNIK === $tgl);

        // Delay simulasi (realistis 200-800ms)
        usleep(rand(200000, 600000));
        $durasi = (int)((microtime(true) - $mulai) * 1000);

        if ($namaWilayah && $cocokTgl) {
            $hasil = [
                'status' => 'sesuai',
                'kode'   => 'DATA_FOUND',
                'pesan'  => 'Data kependudukan sesuai. (Mode Simulasi)',
                'detail' => [
                    'kode_wilayah' => $kodeWilayah,
                    'nama_wilayah' => $namaWilayah,
                    'jenis_kelamin'=> $isPerempuan ? 'P' : 'L',
                    'tgl_dari_nik' => $tglDariNIK,
                    'mode'         => 'SIMULASI — bukan data real Dukcapil',
                ],
            ];
        } elseif (!$namaWilayah) {
            $hasil = [
                'status' => 'tidak_sesuai',
                'kode'   => 'WILAYAH_NOT_FOUND',
                'pesan'  => 'Kode wilayah pada NIK tidak ditemukan dalam referensi Kemendagri. Pastikan kode wilayah desa sudah dimuat.',
                'detail' => ['kode_wilayah_input' => $kodeWilayah, 'mode' => 'SIMULASI'],
            ];
        } else {
            $hasil = [
                'status' => 'tidak_sesuai',
                'kode'   => 'DATA_MISMATCH',
                'pesan'  => 'Tanggal lahir tidak sesuai dengan yang tersimpan dalam NIK. (Mode Simulasi)',
                'detail' => [
                    'tgl_dari_nik'   => $tglDariNIK,
                    'tgl_diinput'    => $tgl,
                    'mode'           => 'SIMULASI',
                ],
            ];
        }

        return $this->simpanLogDanReturn($idDesa, $idPengguna, $idWarga, $nik, $nama, $tgl, $hasil, $durasi, 'simulasi');
    }

    /**
     * MODE AKTIF — menghubungi API Dukcapil yang sesungguhnya
     * Memerlukan client_id & client_secret dari MOU yang disetujui
     * Sesuai Permendagri No. 102 Tahun 2019
     */
    private function verifikasiAktif(
        string $nik, string $nama, string $tgl,
        int $idDesa, int $idPengguna, int $idWarga, float $mulai
    ): array {
        // Cek kredensial
        if (empty($this->config['client_id']) || empty($this->config['client_secret'])) {
            return $this->simpanLogDanReturn($idDesa,$idPengguna,$idWarga,$nik,$nama,$tgl,[
                'status' => 'error',
                'kode'   => 'NO_CREDENTIALS',
                'pesan'  => 'Client ID dan Client Secret belum dikonfigurasi. Pastikan MOU dengan Dukcapil sudah disetujui dan kredensial sudah diisi di pengaturan SIAK.',
                'detail' => null,
            ], 0);
        }

        try {
            // Step 1: Ambil token OAuth
            $token = $this->getOAuthToken();
            if (!$token) {
                throw new Exception('Gagal mendapatkan token autentikasi dari server Dukcapil.');
            }

            // Step 2: Kirim request verifikasi NIK
            $payload = json_encode([
                'nik'           => $nik,
                'nama'          => strtoupper($nama),
                'tanggal_lahir' => $tgl,
            ]);

            $respJson = $this->httpPost(self::URL_SIAK_VERIFIKASI, $payload, [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ]);

            $durasi = (int)((microtime(true) - $mulai) * 1000);

            if (!$respJson) {
                throw new Exception('Tidak ada respons dari server SIAK Dukcapil.');
            }

            // Parse respons
            $resp = json_decode($respJson, true);
            $statusVerif = strtolower($resp['status'] ?? '');

            if ($statusVerif === 'sesuai' || $resp['code'] === '0000') {
                $hasil = ['status'=>'sesuai','kode'=>$resp['code']??'OK','pesan'=>'Data kependudukan sesuai dengan database SIAK Dukcapil.','detail'=>$resp];
            } elseif (str_contains(strtolower($resp['message']??''), 'tidak ditemukan')) {
                $hasil = ['status'=>'tidak_ditemukan','kode'=>$resp['code']??'NOT_FOUND','pesan'=>'Data tidak ditemukan di database SIAK Dukcapil. Pastikan NIK valid.','detail'=>$resp];
            } else {
                $hasil = ['status'=>'tidak_sesuai','kode'=>$resp['code']??'MISMATCH','pesan'=>$resp['message']??'Data tidak sesuai dengan database SIAK Dukcapil.','detail'=>$resp];
            }

        } catch (Exception $e) {
            $durasi = (int)((microtime(true) - $mulai) * 1000);
            $hasil  = ['status'=>'error','kode'=>'CONNECTION_ERROR','pesan'=>$e->getMessage(),'detail'=>null];
        }

        return $this->simpanLogDanReturn($idDesa, $idPengguna, $idWarga, $nik, $nama, $tgl, $hasil, $durasi, 'aktif');
    }

    private function getOAuthToken(): ?string
    {
        $payload = http_build_query([
            'grant_type'    => 'client_credentials',
            'client_id'     => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
        ]);
        $resp = $this->httpPost(self::URL_SIAK_TOKEN, $payload, [
            'Content-Type: application/x-www-form-urlencoded',
        ]);
        if (!$resp) return null;
        $data = json_decode($resp, true);
        return $data['access_token'] ?? null;
    }

    // ════════════════════════════════════════════════════════
    //  BAGIAN 3: UTILITAS HTTP
    // ════════════════════════════════════════════════════════

    private function httpGet(string $url): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT_DETIK,
            CURLOPT_USERAGENT      => 'SiDesa/1.0 FNA-Kawan-kawan (+https://github.com/FNA)',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err || !$resp) return null;
        $data = json_decode($resp, true);
        return is_array($data) ? $data : null;
    }

    private function httpPost(string $url, string $payload, array $headers = []): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT_DETIK,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_USERAGENT      => 'SiDesa/1.0 FNA-Kawan-kawan',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
        return $resp ?: null;
    }

    // ════════════════════════════════════════════════════════
    //  BAGIAN 4: LOGGING
    // ════════════════════════════════════════════════════════

    private function simpanLogDanReturn(
        int $idDesa, int $idPengguna, int $idWarga,
        string $nik, string $nama, string $tgl,
        array $hasil, int $durasi, string $mode = null
    ): array {
        try {
            $this->db->prepare(
                "INSERT INTO siak_log_verifikasi
                 (id_desa, id_pengguna, id_warga, nik_input, nama_input, tgl_lahir_input,
                  status_respons, kode_respons, pesan_respons, durasi_ms, mode)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)"
            )->execute([
                $idDesa,
                $idPengguna ?: null,
                $idWarga    ?: null,
                $nik,
                $nama       ?: null,
                $tgl        ?: null,
                $hasil['status'],
                $hasil['kode']  ?? null,
                $hasil['pesan'] ?? null,
                $durasi,
                $mode ?? $this->mode,
            ]);
        } catch (PDOException $e) {
            // Log gagal tidak boleh hentikan proses utama
        }

        return $hasil;
    }

    // ════════════════════════════════════════════════════════
    //  BAGIAN 5: HELPER
    // ════════════════════════════════════════════════════════

    public function getMode(): string { return $this->mode; }

    public function isMOUDisetujui(): bool
    {
        return ($this->config['status_mou'] ?? '') === 'disetujui'
            && !empty($this->config['client_id']);
    }

    /**
     * Hitung statistik penggunaan verifikasi untuk dashboard
     */
    public function getStatistik(int $idDesa, int $hari = 30): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                COUNT(*) as total,
                SUM(status_respons='sesuai') as sesuai,
                SUM(status_respons='tidak_sesuai') as tidak_sesuai,
                SUM(status_respons='tidak_ditemukan') as tidak_ditemukan,
                SUM(status_respons='error') as error,
                SUM(status_respons='simulasi') as simulasi,
                ROUND(AVG(durasi_ms)) as rata_durasi_ms
             FROM siak_log_verifikasi
             WHERE id_desa=? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)"
        );
        $stmt->execute([$idDesa, $hari]);
        return $stmt->fetch() ?: [];
    }
}
