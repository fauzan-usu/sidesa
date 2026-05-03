<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db  = getDB();
$id  = (int) ($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT w.*, d.nama_desa, d.kecamatan, d.kabupaten FROM warga w JOIN desa d ON w.id_desa=d.id WHERE w.id=?");
$stmt->execute([$id]);
$w = $stmt->fetch();
if (!$w) { setFlash('error','Data tidak ditemukan.'); header('Location: index.php'); exit; }

$pageTitle = 'Detail Warga';
$pageBreadcrumb = ['Dashboard'=>APP_URL.'/index.php','Data Warga'=>APP_URL.'/modules/warga/index.php','Detail'=>null];
require_once __DIR__ . '/../../includes/header.php';

function row(string $label, string $value): void {
    echo '<tr>
        <td style="width:35%;font-size:12.5px;color:var(--text-3);padding:9px 0;border-bottom:1px solid var(--border-soft)">' . htmlspecialchars($label) . '</td>
        <td style="font-size:13.5px;padding:9px 0 9px 12px;border-bottom:1px solid var(--border-soft);font-weight:500">' . htmlspecialchars($value) . '</td>
    </tr>';
}
?>
<div style="display:flex;gap:20px;flex-wrap:wrap;align-items:flex-start">
    <!-- Kartu Identitas Utama -->
    <div style="flex:2;min-width:280px">
        <div class="card mb-3">
            <div class="card-header">
                <div style="display:flex;align-items:center;gap:14px">
                    <div style="width:52px;height:52px;background:var(--brand-light);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:700;color:var(--brand)">
                        <?= strtoupper(substr($w['nama_lengkap'], 0, 1)) ?>
                    </div>
                    <div>
                        <div style="font-size:18px;font-weight:700"><?= e($w['nama_lengkap']) ?></div>
                        <div style="font-size:13px;color:var(--text-3)">NIK: <?= e($w['nik']) ?></div>
                    </div>
                </div>
                <div style="display:flex;gap:8px">
                    <a href="form.php?id=<?= $w['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                    <a href="index.php" class="btn btn-secondary btn-sm">← Kembali</a>
                </div>
            </div>
            <div class="card-body">
                <table style="width:100%;border-collapse:collapse">
                    <?php
                    row('No. Kartu Keluarga',    $w['no_kk']);
                    row('Tempat, Tanggal Lahir', $w['tempat_lahir'] . ', ' . formatTanggal($w['tanggal_lahir']) . ' (' . hitungUmur($w['tanggal_lahir']) . ' tahun)');
                    row('Jenis Kelamin',          $w['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan');
                    row('Agama',                  $w['agama']);
                    row('Status Perkawinan',       $w['status_kawin']);
                    row('Pendidikan Terakhir',    $w['pendidikan']);
                    row('Pekerjaan',               $w['pekerjaan'] ?: '-');
                    row('No. Telepon',             $w['no_telepon'] ?: '-');
                    row('Alamat',                  $w['alamat'] . ($w['rt'] ? " RT {$w['rt']}/RW {$w['rw']}" : ''));
                    row('Desa',                    "{$w['nama_desa']}, Kec. {$w['kecamatan']}, {$w['kabupaten']}");
                    ?>
                </table>
            </div>
        </div>
    </div>

    <!-- Kartu Status -->
    <div style="flex:1;min-width:240px">
        <div class="card mb-3">
            <div class="card-header"><span class="card-title">Status</span></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:14px">
                <div>
                    <div style="font-size:12px;color:var(--text-3);margin-bottom:4px">Status Kependudukan</div>
                    <?php $sc = ['Hidup'=>'badge-green','Meninggal'=>'badge-red','Pindah'=>'badge-amber']; ?>
                    <span class="badge <?= $sc[$w['status_hidup']] ?? 'badge-gray' ?>" style="font-size:13px;padding:5px 14px"><?= e($w['status_hidup']) ?></span>
                </div>
                <div>
                    <div style="font-size:12px;color:var(--text-3);margin-bottom:4px">DTKS</div>
                    <span class="badge <?= $w['status_dtks'] ? 'badge-red' : 'badge-gray' ?>"><?= $w['status_dtks'] ? 'Termasuk DTKS' : 'Tidak Termasuk' ?></span>
                </div>
                <div>
                    <div style="font-size:12px;color:var(--text-3);margin-bottom:4px">Tanggal Didaftarkan</div>
                    <div style="font-size:13.5px;font-weight:500"><?= formatTanggal($w['created_at'], 'd M Y') ?></div>
                </div>
                <div>
                    <div style="font-size:12px;color:var(--text-3);margin-bottom:4px">Terakhir Diperbarui</div>
                    <div style="font-size:13.5px;font-weight:500"><?= formatTanggal($w['updated_at'], 'd M Y') ?></div>
                </div>
                <!-- Status Verifikasi SIAK -->
                <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--border)">
                    <div style="font-size:12px;color:var(--text-3);margin-bottom:8px;font-weight:600;letter-spacing:.04em;text-transform:uppercase">Status SIAK Dukcapil</div>
                    <?php if ($w['siak_verified'] ?? false): ?>
                    <div style="display:flex;align-items:center;gap:8px;padding:10px 12px;background:#d1fae5;border-radius:var(--radius);border:1px solid #6ee7b7">
                        <span style="font-size:20px">✅</span>
                        <div>
                            <div style="font-size:13px;font-weight:700;color:#065f46">NIK Terverifikasi</div>
                            <?php if (!empty($w['siak_verified_at'])): ?>
                            <div style="font-size:11.5px;color:#047857"><?= formatTanggal($w['siak_verified_at'], 'd M Y H:i') ?> WIB</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <div style="display:flex;align-items:center;gap:8px;padding:10px 12px;background:#fef3c7;border-radius:var(--radius);border:1px solid #fcd34d;margin-bottom:10px">
                        <span style="font-size:20px">⏳</span>
                        <div style="font-size:13px;font-weight:600;color:#92400e">Belum Diverifikasi ke SIAK</div>
                    </div>
                    <a href="<?= APP_URL ?>/modules/siak/verifikasi_nik.php?nik=<?= urlencode($w['nik']) ?>"
                       class="btn btn-secondary btn-sm" style="width:100%;justify-content:center">
                        🔍 Verifikasi NIK ke SIAK
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>


<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
