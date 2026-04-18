// public/style/script.js

// Ambil Konfigurasi dari PHP
const userNIK = window.HRIS_CONFIG.userNIK;
const adminHistData = window.HRIS_CONFIG.adminHistData || [];
const karyawanPenempatan = window.HRIS_CONFIG.karyawanPenempatan || 'WFO';

const screens = ['beranda', 'riwayat', 'layanan', 'profil', 'admin-absen'];

function switchScreen(target) {
    screens.forEach(s => {
        const el = document.getElementById('screen-' + s);
        const nav = document.getElementById('nav-' + s);
        if (el) el.classList.remove('active');
        if (nav) nav.classList.remove('active');
    });
    const t = document.getElementById('screen-' + target);
    const n = document.getElementById('nav-' + target);
    if (t) t.classList.add('active');
    if (n) n.classList.add('active');
    window.scrollTo(0, 0);
}

function showModernAlert(title, message, iconClass, iconColor) {
    document.getElementById('modernAlertTitle').innerText = title;
    document.getElementById('modernAlertMessage').innerHTML = message;
    document.getElementById('modernAlertIcon').innerHTML = `<i class="${iconClass}" style="font-size:3.5rem; color:${iconColor};"></i>`;
    new bootstrap.Modal(document.getElementById('modernAlertModal')).show();
}

function aksesGajiDitolak() {
    showModernAlert('Akses Terkunci', 'Slip gaji bersifat rahasia dan saat ini hanya dapat diakses melalui portal HRD / Finance.', 'bi bi-lock-fill', '#6c757d');
}

// =======================================================
// LOGIKA ADMIN FILTER & CETAK
// =======================================================
let selectedNikAdmin = null;
let selectedNamaAdmin = null;
let filteredDataAdmin = [];

function showEmployeeLog(nik, nama) {
    selectedNikAdmin = nik;
    selectedNamaAdmin = nama;

    document.getElementById('admin-view-list').style.display = 'none';
    document.getElementById('admin-view-detail').style.display = 'block';

    const firstName = nama.split(' ')[0];
    document.getElementById('detail-nama-karyawan').innerText = 'Log ' + firstName;

    document.getElementById('filter-start').value = '';
    document.getElementById('filter-end').value = '';

    renderAdminTable();
}

function backToAdminList() {
    document.getElementById('admin-view-list').style.display = 'block';
    document.getElementById('admin-view-detail').style.display = 'none';
}

function renderAdminTable() {
    const tbody = document.getElementById('admin-detail-tbody');
    tbody.innerHTML = '';
    const start = document.getElementById('filter-start').value;
    const end = document.getElementById('filter-end').value;

    filteredDataAdmin = adminHistData.filter(d => d.nik === selectedNikAdmin);
    if (start) filteredDataAdmin = filteredDataAdmin.filter(d => d.tgl >= start);
    if (end) filteredDataAdmin = filteredDataAdmin.filter(d => d.tgl <= end);

    if (filteredDataAdmin.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-folder-x fs-1 d-block mb-2"></i>Tidak ada data absensi di rentang tanggal ini.</td></tr>';
        return;
    }

    let htmlRows = '';
    filteredDataAdmin.forEach((data, index) => {
        const inTimeView = data.in_time ? data.in_time.substring(11, 16) : '-';
        const outTimeView = data.out_time ? data.out_time.substring(11, 16) : '-';

        let statusIn = data.status_in || 'Tidak Hadir';
        let badgeBg = 'bg-hadir';
        if (statusIn === 'Telat') { statusIn = 'Terlambat'; badgeBg = 'bg-terlambat'; }
        if (statusIn === 'Tidak Hadir') badgeBg = 'bg-absen';
        if (data.in_time && !data.out_time) { statusIn = 'Check In'; badgeBg = 'bg-checkin'; }

        const tglParts = data.tgl.split('-');
        const ddmmyyyy = `${tglParts[2]}/${tglParts[1]}/${tglParts[0]}`;

        htmlRows += `
            <tr style="cursor:pointer;" onclick="bukaDetailDariAdmin(${index})">
                <td class="px-4 py-3 fw-bold text-dark">${ddmmyyyy}</td>
                <td><i class="bi bi-clock-fill text-success me-1 opacity-75"></i> ${inTimeView} <span class="text-muted mx-1">&mdash;</span> ${outTimeView}</td>
                <td class="text-end px-4">
                    <button class="btn btn-sm btn-light border rounded-circle shadow-sm">
                        <i class="bi bi-three-dots-vertical text-dark"></i>
                    </button>
                </td>
            </tr>
        `;
    });
    tbody.innerHTML = htmlRows;
}

window.bukaDetailDariAdmin = function (idx) {
    const data = filteredDataAdmin[idx];

    let durasi_teks = '-';
    if (data.in_time && data.out_time) {
        const inDate = new Date(`1970-01-01T${data.in_time.substring(11, 16)}:00Z`);
        const outDate = new Date(`1970-01-01T${data.out_time.substring(11, 16)}:00Z`);
        const diffMs = outDate - inDate;
        if (diffMs > 0) {
            const diffHrs = Math.floor(diffMs / 3600000);
            const diffMins = Math.floor((diffMs % 3600000) / 60000);
            durasi_teks = `${diffHrs} jam ${diffMins} menit`;
        }
    }

    let stat = data.status_in || 'Tidak Hadir';
    if (stat === 'Telat') stat = 'Terlambat';
    if (data.in_time && !data.out_time) stat = 'Check In';

    const tglParts = data.tgl.split('-');
    const bln = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    const formatTgl = tglParts[2] + ' ' + bln[parseInt(tglParts[1]) - 1] + ' ' + tglParts[0];

    const modalData = {
        tanggal: formatTgl,
        nama: data.nama,
        status: stat,
        durasi: durasi_teks,
        in_time: data.in_time ? data.in_time.substring(11, 16) : '-',
        out_time: data.out_time ? data.out_time.substring(11, 16) : '-',
        in_lokasi: data.lok_in || karyawanPenempatan,
        out_lokasi: data.lok_out || karyawanPenempatan,
        in_foto: data.foto_in || '',
        out_foto: data.foto_out || '',
        penempatan: karyawanPenempatan
    };
    bukaDetail(modalData);
};

function bukaModalCetakKaryawan() {
    if (!selectedNikAdmin) {
        showModernAlert('Peringatan', 'Silakan pilih karyawan terlebih dahulu.', 'bi bi-exclamation-circle-fill', '#dc3545');
        return;
    }
    document.getElementById('cetak_nik_karyawan').value = selectedNikAdmin;
    new bootstrap.Modal(document.getElementById('modalCetakLogKaryawan')).show();
}

// =======================================================
// LOGIKA KAMERA, GPS (REALTIME KORDINAT) & IP
// =======================================================
const video = document.getElementById('kamera');
const preview = document.getElementById('kamera-preview');
const canvas = document.getElementById('canvas_kamera');
let kameraAktif = false;
let fotoDataURL = '';
let absenJenisType = '';

let userLat = '';
let userLng = '';
let userIP = 'Mendeteksi...';

// Ambil IP
fetch('https://api.ipify.org?format=json')
    .then(response => response.json())
    .then(data => { userIP = data.ip; })
    .catch(error => { userIP = 'Gagal mendeteksi IP'; });

if (video) {
    navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" } })
        .then(stream => { video.srcObject = stream; kameraAktif = true; })
        .catch(() => {
            const w = document.querySelector('.camera-box');
            if (w) w.innerHTML = '<div class="d-flex align-items-center justify-content-center h-100 bg-dark w-100"><small class="text-danger fw-bold text-center px-2">Kamera Ditolak / Tidak Ditemukan.</small></div>';
        });

    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            position => {
                userLat = position.coords.latitude;
                userLng = position.coords.longitude;
            },
            error => { console.log("GPS Error: ", error); },
            { enableHighAccuracy: true }
        );
    }
}

function ambilFoto(jenis) {
    if (!kameraAktif) { alert("Kamera tidak aktif. Izinkan akses kamera browser Anda."); return; }

    absenJenisType = jenis;

    canvas.width = video.videoWidth || 320;
    canvas.height = video.videoHeight || 426;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    fotoDataURL = canvas.toDataURL('image/jpeg', 0.8); // Kualitas gambar dinaikkan ke standar

    video.style.display = 'none';
    preview.src = fotoDataURL;
    preview.style.display = 'block';

    document.getElementById('btn-action-group').style.setProperty('display', 'none', 'important');
    document.getElementById('btn-confirm-group').style.setProperty('display', 'flex', 'important');
    document.getElementById('confirm-text').innerText = "Kirim " + jenis;
}

function batalFoto() {
    fotoDataURL = '';
    absenJenisType = '';

    preview.style.display = 'none';
    video.style.display = 'block';

    document.getElementById('btn-action-group').style.setProperty('display', 'flex', 'important');
    document.getElementById('btn-confirm-group').style.setProperty('display', 'none', 'important');
}

function submitAbsen() {
    const responseDiv = document.getElementById('absen-response');
    responseDiv.innerHTML = '<span class="text-warning"><i class="spinner-border spinner-border-sm"></i> Mengirim data absensi...</span>';

    document.getElementById('btn-confirm-group').style.setProperty('display', 'none', 'important');

    const koordinatRealtime = (userLat && userLng) ? `${userLat}, ${userLng}` : 'Lokasi tidak diizinkan';

    const formData = new FormData();
    formData.append('jenis_absen', absenJenisType);
    formData.append('nik', userNIK);
    formData.append('foto', fotoDataURL);
    formData.append('lokasi', koordinatRealtime);

    fetch('/proses_absen', {
        method: 'POST',
        body: formData
    })
        .then(res => res.text())
        .then(data => { responseDiv.innerHTML = data; setTimeout(() => location.reload(), 1500); })
        .catch(() => { responseDiv.innerText = "Terjadi kesalahan server saat mengirim absen."; batalFoto(); });
}

// =======================================================
// TAMPILAN DETAIL MODAL (TABS)
// =======================================================
function bukaDetail(data) {
    document.getElementById('mdl-header').innerText = data.nama + ' • ' + data.tanggal;

    const boxStatus = document.getElementById('mdl-status-box');
    const iconStatus = document.getElementById('mdl-status-icon');
    const textStatus = document.getElementById('mdl-status-text');

    let badgeWFO = `<span class="badge bg-success fw-normal ms-2 shadow-sm" style="font-size:10px;">${data.penempatan || karyawanPenempatan}</span>`;

    if (data.status === 'Terlambat' || data.status === 'Telat') {
        boxStatus.style.backgroundColor = '#fff4e5';
        boxStatus.style.borderColor = '#ffe0b2';
        iconStatus.className = 'bi bi-exclamation-circle-fill text-warning fs-3';
        textStatus.innerHTML = `Terlambat ${badgeWFO}`;
    } else if (data.status === 'Check In') {
        boxStatus.style.backgroundColor = '#e0f8f1';
        boxStatus.style.borderColor = '#b2ede0';
        iconStatus.className = 'bi bi-box-arrow-in-right text-info fs-3';
        textStatus.innerHTML = `Sudah Check In ${badgeWFO}`;
    } else if (data.status === 'Tidak Hadir' || data.status === 'Absen') {
        boxStatus.style.backgroundColor = '#f8d7da';
        boxStatus.style.borderColor = '#f5c2c7';
        iconStatus.className = 'bi bi-x-circle-fill text-danger fs-3';
        textStatus.innerHTML = `Tidak Hadir`;
    } else {
        boxStatus.style.backgroundColor = '#e8f5e9';
        boxStatus.style.borderColor = '#c8e6c9';
        iconStatus.className = 'bi bi-check-circle-fill text-success fs-3';
        textStatus.innerHTML = `Hadir ${badgeWFO}`;
    }

    document.getElementById('mdl-in-time').innerText = data.in_time;
    document.getElementById('mdl-out-time').innerText = data.out_time;

    document.getElementById('mdl-in-lokasi').innerText = `Titik Area: ${data.penempatan}`;
    document.getElementById('mdl-out-lokasi').innerText = `Titik Area: ${data.penempatan}`;

    document.getElementById('mdl-in-ip').innerText = 'Terekam Sistem';
    document.getElementById('mdl-in-coord').innerText = data.in_lokasi;

    document.getElementById('mdl-out-ip').innerText = 'Terekam Sistem';
    document.getElementById('mdl-out-coord').innerText = data.out_lokasi;

    const inFotoEl = document.getElementById('mdl-in-foto');
    if (data.in_foto && data.in_foto !== '' && data.in_foto !== 'NULL') { inFotoEl.src = data.in_foto; }
    else { inFotoEl.src = 'https://placehold.co/400x500?text=Tidak+Ada+Foto'; }

    const outFotoEl = document.getElementById('mdl-out-foto');
    if (data.out_foto && data.out_foto !== '' && data.out_foto !== 'NULL') { outFotoEl.src = data.out_foto; }
    else { outFotoEl.src = 'https://placehold.co/400x500?text=Tidak+Ada+Foto'; }

    const tabIn = new bootstrap.Tab(document.querySelector('#pills-tab button[data-bs-target="#pills-in"]'));
    tabIn.show();

    new bootstrap.Modal(document.getElementById('modalDetailAbsen')).show();
}

// =======================================================
// LOGIKA PENGAJUAN DINAS & REIMBURSE
// =======================================================
const inputFotoReimburse = document.getElementById('inputFotoReimburse');
if (inputFotoReimburse) {
    inputFotoReimburse.addEventListener('change', function () {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function (e) {
                document.getElementById('base64Reimburse').value = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    });
}

function submitPengajuan(event, jenis) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    formData.append('jenis', jenis);

    const btnSubmit = form.querySelector('button[type="submit"]');
    const originalText = btnSubmit.innerHTML;
    btnSubmit.innerHTML = '<i class="spinner-border spinner-border-sm"></i> Mengirim...';
    btnSubmit.disabled = true;

    fetch('/proses_pengajuan', {
        method: 'POST',
        body: formData
    })
        .then(res => res.text())
        .then(data => {
            bootstrap.Modal.getInstance(form.closest('.modal')).hide();
            showModernAlert('Berhasil', data, 'bi bi-check-circle-fill', '#198754');
            form.reset();
        })
        .catch(err => {
            showModernAlert('Gagal', 'Terjadi kesalahan jaringan.', 'bi bi-x-circle-fill', '#dc3545');
        })
        .finally(() => {
            btnSubmit.innerHTML = originalText;
            btnSubmit.disabled = false;
        });
}

// =======================================================
// LOGIKA UBAH KATA SANDI (DENGAN ANIMASI)
// =======================================================
function checkStrength(val) {
    const bars = [document.getElementById('bar1'), document.getElementById('bar2'), document.getElementById('bar3'), document.getElementById('bar4')];
    const label = document.getElementById('strength-label');

    bars.forEach(b => { b.style.background = '#eee'; });
    label.textContent = '';
    label.style.color = '#333';

    if (!val) return;

    let score = 0;
    if (val.length >= 6) score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score++;
    if (/[0-9]/.test(val) && /[^A-Za-z0-9]/.test(val)) score++;

    const colors = ['#e53935', '#fb8c00', '#fdd835', '#43a047'];
    const labels = ['Sangat Lemah', 'Lemah', 'Cukup Kuat', 'Kuat'];

    for (let i = 0; i < score; i++) {
        bars[i].style.background = colors[Math.min(score - 1, 3)];
    }
    label.textContent = labels[Math.min(score - 1, 3)];
    label.style.color = colors[Math.min(score - 1, 3)];
}

function checkMatch() {
    const pw1 = document.getElementById('password_baru').value;
    const pw2 = document.getElementById('password_konfirmasi').value;
    const msg = document.getElementById('match-msg');

    if (!pw2) { msg.textContent = ''; return; }
    if (pw1 === pw2) {
        msg.textContent = '✓ Kata sandi cocok';
        msg.style.color = '#43a047';
    } else {
        msg.textContent = '✗ Kata sandi tidak cocok';
        msg.style.color = '#e53935';
    }
}

function submitUbahPassword(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);

    if (formData.get('password_baru') !== formData.get('konfirmasi_password')) {
        showModernAlert('Gagal', 'Kata sandi baru dan konfirmasi tidak cocok!', 'bi bi-x-circle-fill', '#dc3545');
        return;
    }

    const btnSubmit = form.querySelector('button[type="submit"]');
    const originalText = btnSubmit.innerHTML;
    btnSubmit.innerHTML = '<i class="spinner-border spinner-border-sm"></i> Menyimpan...';
    btnSubmit.disabled = true;

    fetch('/ubah_password', {
        method: 'POST',
        body: formData
    })
        .then(res => res.text())
        .then(data => {
            bootstrap.Modal.getInstance(form.closest('.modal')).hide();
            if (data.includes('Berhasil') || data.includes('berhasil') || data.includes('✅')) {
                showModernAlert('Berhasil', data, 'bi bi-check-circle-fill', '#198754');
                form.reset();
                document.getElementById('strength-label').textContent = '';
                document.getElementById('match-msg').textContent = '';
                ['bar1', 'bar2', 'bar3', 'bar4'].forEach(id => document.getElementById(id).style.background = '#eee');
            } else {
                showModernAlert('Gagal', data, 'bi bi-x-circle-fill', '#dc3545');
            }
        })
        .catch(err => {
            showModernAlert('Gagal', 'Terjadi kesalahan jaringan.', 'bi bi-x-circle-fill', '#dc3545');
        })
        .finally(() => {
            btnSubmit.innerHTML = originalText;
            btnSubmit.disabled = false;
        });
}

// JAM DIGITAL RESPONSIVE KE BANNER
setInterval(() => {
    const now = new Date();
    const timeString = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }).replace(/\./g, ':');
    if (document.getElementById('clock-display'))
        document.getElementById('clock-display').innerHTML = timeString + ' <span class="fs-4">WIB</span>';
    if (document.getElementById('date-display'))
        document.getElementById('date-display').innerText = now.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
}, 1000);

// =======================================================
// SPLASH SCREEN / LOADING LOGIC
// =======================================================
// Event 'load' memastikan web menunggu SEMUA gambar dan sinyal selesai dimuat
window.addEventListener('load', function () {
    const splash = document.getElementById('splash-screen');
    if (splash) {
        // Memberi sedikit delay (misal 500ms) agar transisinya terlihat elegan & tidak berkedip cepat
        setTimeout(() => {
            splash.classList.add('splash-hidden');
            // Hapus elemen dari background memori setelah efek transisi (600ms) selesai
            setTimeout(() => {
                splash.remove();
            }, 600);
        }, 500);
    }
});