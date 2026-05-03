/**
 * SiDesa — JavaScript Utama
 * Tidak bergantung pada library eksternal
 */

// -------------------------------------------------------
//  SIDEBAR TOGGLE (MOBILE)
// -------------------------------------------------------
function toggleSidebar() {
    const sidebar  = document.getElementById('sidebar');
    const overlay  = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('open');
    overlay.classList.toggle('open');
}

// -------------------------------------------------------
//  AUTO-DISMISS ALERT SETELAH 4 DETIK
// -------------------------------------------------------
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity 0.4s';
            alert.style.opacity = '0';
            setTimeout(function() { alert.remove(); }, 400);
        }, 4000);
    });
});

// -------------------------------------------------------
//  KONFIRMASI HAPUS
// -------------------------------------------------------
function konfirmasiHapus(nama) {
    return confirm('Apakah Anda yakin ingin menghapus data "' + nama + '"?\nTindakan ini tidak dapat dibatalkan.');
}

// -------------------------------------------------------
//  FORMAT NIK: Validasi 16 digit
// -------------------------------------------------------
function validasiNIK(input) {
    input.value = input.value.replace(/\D/g, '').slice(0, 16);
}

// -------------------------------------------------------
//  MODAL HELPER
// -------------------------------------------------------
function bukaModal(id) {
    document.getElementById(id).classList.add('open');
}
function tutupModal(id) {
    document.getElementById(id).classList.remove('open');
}
// Tutup modal jika klik backdrop
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-backdrop')) {
        e.target.classList.remove('open');
    }
});

// -------------------------------------------------------
//  CETAK HALAMAN
// -------------------------------------------------------
function cetakHalaman() {
    window.print();
}

// -------------------------------------------------------
//  FILTER TABEL REALTIME (ringan, tanpa library)
// -------------------------------------------------------
function filterTabel(inputId, tableId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    input.addEventListener('input', function() {
        const kata  = this.value.toLowerCase();
        const rows  = document.querySelectorAll('#' + tableId + ' tbody tr');
        let tampil  = 0;
        rows.forEach(function(row) {
            const teks = row.textContent.toLowerCase();
            if (teks.includes(kata)) {
                row.style.display = '';
                tampil++;
            } else {
                row.style.display = 'none';
            }
        });
        const info = document.getElementById('filter-info');
        if (info) info.textContent = tampil + ' data ditemukan';
    });
}
