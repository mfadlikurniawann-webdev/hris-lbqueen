<?php
// api/index.php
include __DIR__ . '/koneksi.php';
date_default_timezone_set('Asia/Jakarta');

// PERBAIKAN: Mengganti $router yang tidak terdefinisi dengan native PHP routing
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($request_uri === '/ganti_password') {
    include __DIR__ . '/ganti_password.php';
    exit;
}

if ($request_uri === '/proses_ganti_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    include __DIR__ . '/proses_ganti_password.php';
    exit;
}

// PROTEKSI HALAMAN - Pakai JWT
$karyawan = auth_required($conn);

// HAK AKSES ADMIN/HR
$posisi   = strtoupper($karyawan['posisi']);
$level    = strtoupper($karyawan['level_jabatan']);

// PERBAIKAN: Menambahkan 'BEAUTY THERAPIST' ke dalam array agar menu tampil saat Anda testing.
// Hapus jika nanti aplikasi sudah live dan menu ini hanya untuk HR/Owner.
$is_admin = in_array($posisi, ['HCG','HRD','HR', 'MANAGER', 'BEAUTY THERAPIST']) || in_array($level, ['OWNER','DIREKTUR', 'SPV']);

// FUNGSI BANTUAN
function getInitials($nama) {
    $p = explode(" ", $nama);
    return strtoupper(substr($p[0], 0, 1) . (isset($p[1]) ? substr($p[1], 0, 1) : ''));
}
$inisial = getInitials($karyawan['nama']);

function formatTanggal($tanggal) {
    if ($tanggal == '0000-00-00' || !$tanggal) return "-";
    return date('d-m-Y', strtotime($tanggal));
}

function formatTanggalIndo($tanggal) {
    $bulan = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $p = explode('-', $tanggal);
    return $p[2] . ' ' . $bulan[(int)$p[1]] . ' ' . $p[0];
}

// LOGIKA WAKTU ABSENSI HARI INI
$hari_ini             = date('Y-m-d');
$jam_sekarang         = date('H:i');
$batas_check_in_awal  = '08:30';
$batas_check_in_akhir = '10:30'; 
$batas_check_out_awal = '18:00';

$belum_waktunya_in  = ($jam_sekarang < $batas_check_in_awal);
$lewat_batas_in     = ($jam_sekarang > $batas_check_in_akhir);
$belum_waktunya_out = ($jam_sekarang < $batas_check_out_awal);

$cek_in   = $conn->query("SELECT waktu, status FROM absensi WHERE nik='".$karyawan['nik']."' AND jenis='Check In' AND DATE(waktu)='$hari_ini'");
$data_in  = $cek_in->fetch_assoc();
$sudah_in = $cek_in->num_rows > 0;
$waktu_in = $sudah_in ? date('H:i', strtotime($data_in['waktu'])) : '--:--';
$status_in = $sudah_in ? $data_in['status'] : '-';

$cek_out   = $conn->query("SELECT waktu FROM absensi WHERE nik='".$karyawan['nik']."' AND jenis='Check Out' AND DATE(waktu)='$hari_ini'");
$data_out  = $cek_out->fetch_assoc();
$sudah_out = $cek_out->num_rows > 0;
$waktu_out = $sudah_out ? date('H:i', strtotime($data_out['waktu'])) : '--:--';

// PENENTUAN KAMERA AKTIF
$show_camera = false;
$jenis_absen_sekarang = '';
if (!$sudah_in && !$belum_waktunya_in && !$lewat_batas_in) {
    $show_camera = true; 
    $jenis_absen_sekarang = 'Check In';
} elseif ($sudah_in && !$sudah_out && !$belum_waktunya_out) {
    $show_camera = true; 
    $jenis_absen_sekarang = 'Check Out';
}

// RIWAYAT ABSENSI PRIBADI
$full_history = $conn->query("SELECT 
    DATE(waktu) as tgl,
    MAX(CASE WHEN jenis='Check In' THEN waktu END) as in_time,
    MAX(CASE WHEN jenis='Check Out' THEN waktu END) as out_time,
    MAX(CASE WHEN jenis='Check In' THEN status END) as status_in,
    MAX(CASE WHEN jenis='Check In' THEN lokasi END) as lok_in,
    MAX(CASE WHEN jenis='Check Out' THEN lokasi END) as lok_out,
    MAX(CASE WHEN jenis='Check In' THEN foto END) as foto_in,
    MAX(CASE WHEN jenis='Check Out' THEN foto END) as foto_out
FROM absensi WHERE nik='".$karyawan['nik']."'
GROUP BY DATE(waktu) ORDER BY tgl DESC");

// DATA ADMIN
$semua_karyawan = [];
$admin_hist_arr = [];

if ($is_admin) {
    $q_kar = $conn->query("SELECT nik, nama, posisi, status_pegawai FROM karyawan ORDER BY nama ASC");
    while($r = $q_kar->fetch_assoc()) {
        $r['inisial'] = getInitials($r['nama']);
        $semua_karyawan[] = $r;
    }

    $q_hist = $conn->query("SELECT 
        DATE(a.waktu) as tgl,
        a.nik, k.nama, k.posisi,
        MAX(CASE WHEN a.jenis='Check In' THEN a.waktu END) as in_time,
        MAX(CASE WHEN a.jenis='Check Out' THEN a.waktu END) as out_time,
        MAX(CASE WHEN a.jenis='Check In' THEN a.status END) as status_in,
        MAX(CASE WHEN a.jenis='Check In' THEN a.lokasi END) as lok_in,
        MAX(CASE WHEN a.jenis='Check Out' THEN a.lokasi END) as lok_out,
        MAX(CASE WHEN a.jenis='Check In' THEN a.foto END) as foto_in,
        MAX(CASE WHEN a.jenis='Check Out' THEN a.foto END) as foto_out
    FROM absensi a JOIN karyawan k ON a.nik=k.nik
    GROUP BY DATE(a.waktu), a.nik ORDER BY tgl DESC");
    
    while($r = $q_hist->fetch_assoc()) {
        $admin_hist_arr[] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>HRIS - <?= htmlspecialchars($karyawan['nama']) ?></title>
    
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#C94F78">
    <link rel="icon" type="image/png" href="/logo/lbqueen_logo.PNG">
    <link rel="apple-touch-icon" href="/logo/lbqueen_logo.PNG">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="HRIS LBQueen">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    
    <link rel="stylesheet" href="/style/style.css">
</head>
<body>
<div class="app-wrapper">

    <div class="sidebar-nav">
        <div class="sidebar-logo">
            <img src="/logo/lbqueen_logo.PNG" alt="LBQueen" style="width:80px; margin-bottom:10px; border-radius:10px;">
            <h6 class="fw-bold text-pink mb-0">HRIS LBQueen</h6>
            <small class="text-muted" style="font-size:11px;">Care & Beauty</small>
        </div>
        <button class="nav-item-btn active" id="nav-beranda" onclick="switchScreen('beranda')"><i class="bi bi-house-door-fill"></i> <span>Beranda</span></button>
        <button class="nav-item-btn" id="nav-riwayat" onclick="switchScreen('riwayat')"><i class="bi bi-clock-history"></i> <span>Riwayat</span></button>
        <button class="nav-item-btn" id="nav-layanan" onclick="switchScreen('layanan')"><i class="bi bi-ui-checks-grid"></i> <span>Layanan</span></button>
        <button class="nav-item-btn" id="nav-profil" onclick="switchScreen('profil')"><i class="bi bi-person-fill"></i> <span>Profil</span></button>
    </div>

    <div class="main-content">

        <div id="screen-beranda" class="app-screen active">
            <div class="bg-pink-wave header-top p-4 desktop-px shadow-sm">
                <div class="d-flex justify-content-between align-items-center mx-auto mb-2" style="max-width:1200px;">
                    <div>
                        <h4 class="mb-0 fw-bold mt-1 text-white">Hai, <?= htmlspecialchars($karyawan['nama']) ?>!</h4>
                        <p class="mb-0 text-white-50" style="font-size:13px;">Selamat datang di HRIS LBQueen</p>
                    </div>
                    <img src="/logo/lbqueen_logo.PNG" alt="Logo" style="height:40px; background:white; border-radius:8px; padding:4px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                </div>
            </div>

            <div class="p-3 desktop-px mx-auto" style="max-width: 1200px;">
                <div class="card mb-3 overlap-card" style="margin-top:-35px; z-index: 10; position:relative;">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="avatar-initials">
                            <?= $inisial ?>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1 text-dark section-title" style="margin-bottom:2px; font-size:16px;"><?= $karyawan['posisi'] ?></h6>
                            <span class="badge bg-warning text-dark me-1"><?= $karyawan['status_pegawai'] ?></span>
                            <span class="badge bg-secondary"><?= $karyawan['penempatan'] ?></span>
                        </div>
                    </div>
                </div>

                <div class="card mb-3 clock-banner position-relative">
                    <div class="card-body p-4">
                        <div class="position-relative" style="z-index: 2;">
                            <p class="mb-1 text-white-50 small fw-bold text-uppercase tracking-wider">Kehadiran</p>
                            <h1 class="display-4 fw-bold text-white mb-3" id="clock-display" style="letter-spacing: -1px;">00:00:00 <span class="fs-4">WIB</span></h1>
                            <div class="d-inline-flex align-items-center bg-white bg-opacity-25 text-white rounded-pill px-3 py-2">
                                <i class="bi bi-calendar3 me-2"></i>
                                <span id="date-display" class="small fw-bold">Memuat tanggal...</span>
                            </div>
                        </div>
                        <i class="bi bi-clock position-absolute text-white opacity-10" style="font-size: 10rem; right: -20px; bottom: -30px; z-index: 1;"></i>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body p-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div class="d-flex align-items-center gap-3">
                            <div class="status-icon bg-light text-secondary">
                                <i class="bi bi-clock-history fs-5"></i>
                            </div>
                            <div>
                                <small class="text-muted fw-bold d-block" style="font-size:10px; letter-spacing:0.5px;">JADWAL KERJA KAMU</small>
                                <span class="fw-bold fs-6 text-dark">09:00 - 19:00 <small class="text-muted">WIB</small></span>
                            </div>
                        </div>
                        <span class="badge bg-light text-success border border-success rounded-pill px-3 py-2 fw-normal"><i class="bi bi-info-circle me-1"></i> Sesuai Penempatan</span>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-6">
                        <div class="card h-100">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <div class="status-icon <?= $sudah_in ? 'bg-success text-white' : 'bg-light text-secondary' ?>"><i class="bi <?= $sudah_in ? 'bi-check-lg' : 'bi-x-lg' ?>"></i></div>
                                    <div>
                                        <h6 class="fw-bold mb-0 text-dark" style="font-size: 13px;">Status</h6>
                                        <small class="text-muted" style="font-size: 10px;"><?= $sudah_in ? 'Sudah Check In' : 'Belum Check In' ?></small>
                                    </div>
                                </div>
                                <h4 class="fw-bold mb-1 <?= $sudah_in ? 'text-dark' : 'text-muted' ?>"><?= $waktu_in ?> <small class="fs-6 text-muted">WIB</small></h4>
                                <small class="text-success fw-bold">Masuk</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card h-100">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <div class="status-icon <?= $sudah_out ? 'bg-danger text-white' : 'bg-light text-secondary' ?>"><i class="bi <?= $sudah_out ? 'bi-check-lg' : 'bi-x-lg' ?>"></i></div>
                                    <div>
                                        <h6 class="fw-bold mb-0 text-dark" style="font-size: 13px;">Status</h6>
                                        <small class="text-muted" style="font-size: 10px;"><?= $sudah_out ? 'Sudah Check Out' : 'Belum Check Out' ?></small>
                                    </div>
                                </div>
                                <h4 class="fw-bold mb-1 <?= $sudah_out ? 'text-dark' : 'text-muted' ?>"><?= $waktu_out ?> <small class="fs-6 text-muted">WIB</small></h4>
                                <small class="text-danger fw-bold">Pulang</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-1 text-dark section-title" style="margin-bottom:5px; font-size:18px;"><?= $show_camera ? ($jenis_absen_sekarang == 'Check In' ? 'Check In' : 'Check Out') : 'Absensi Selesai' ?></h5>
                        <p class="text-muted small mb-4">Lakukan absensi untuk mencatat waktu kehadiran Anda.</p>

                        <?php if ($show_camera): ?>
                            <div class="mb-3 text-center">
                                <span class="badge bg-light text-dark border px-3 py-2 rounded-pill mb-3">
                                    <i class="bi bi-camera me-1"></i> Selfie Presensi
                                </span>
                                
                                <div class="camera-box">
                                    <video id="kamera" autoplay playsinline></video>
                                    <img id="kamera-preview" style="display:none;" />
                                    <canvas id="canvas_kamera" style="display:none;"></canvas>
                                </div>
                                <small class="text-muted d-block mt-3">Pastikan wajah terlihat jelas dan berada di area kantor.</small>
                            </div>

                            <div id="absen-response" class="mb-3 text-center fw-bold"></div>

                            <div id="btn-action-group" class="d-flex justify-content-center gap-3 mt-4 mx-auto" style="max-width: 400px;">
                                <?php if ($jenis_absen_sekarang == 'Check In'): ?>
                                    <button class="btn btn-success w-100 rounded-pill py-3 fw-bold shadow-sm" onclick="ambilFoto('Check In')">
                                        <i class="bi bi-camera-fill me-2"></i> Ambil Foto Check In
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-danger w-100 rounded-pill py-3 fw-bold shadow-sm" onclick="ambilFoto('Check Out')">
                                        <i class="bi bi-camera-fill me-2"></i> Ambil Foto Check Out
                                    </button>
                                <?php endif; ?>
                            </div>

                            <div id="btn-confirm-group" class="d-flex justify-content-center gap-3 mt-4 mx-auto" style="max-width: 400px; display:none !important;">
                                <button class="btn btn-outline-secondary flex-fill rounded-pill py-3 fw-bold" onclick="batalFoto()">
                                    Batal
                                </button>
                                <button class="btn btn-pink flex-fill rounded-pill py-3 fw-bold shadow-sm" onclick="submitAbsen()">
                                    <span id="confirm-text">Kirim Absen</span> <i class="bi bi-send-fill ms-2"></i>
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="text-center p-4 rounded-4 mx-auto" style="max-width: 300px; background-color:var(--bg-body);">
                                <?php if (!$sudah_in && $belum_waktunya_in): ?>
                                    <i class="bi bi-clock-history text-warning" style="font-size: 3rem;"></i>
                                    <h6 class="fw-bold mt-3 text-dark">Belum Waktunya</h6>
                                    <p class="mb-0 text-muted small">Check In baru bisa dilakukan mulai pukul 08:30 WIB.</p>
                                <?php elseif (!$sudah_in && $lewat_batas_in): ?>
                                    <i class="bi bi-x-circle text-danger" style="font-size: 3rem;"></i>
                                    <h6 class="fw-bold mt-3 text-danger">Terlambat Check In</h6>
                                    <p class="mb-0 text-muted small">Batas Check In (10:30 WIB) telah terlewat hari ini.</p>
                                <?php elseif ($sudah_in && !$sudah_out && $belum_waktunya_out): ?>
                                    <i class="bi bi-briefcase text-primary" style="font-size: 3rem;"></i>
                                    <h6 class="fw-bold mt-3 text-dark">Selamat Bekerja!</h6>
                                    <p class="mb-0 text-muted small">Check Out baru bisa dilakukan mulai pukul 18:00 WIB.</p>
                                <?php elseif ($sudah_out): ?>
                                    <i class="bi bi-calendar2-check text-success" style="font-size: 3rem;"></i>
                                    <h6 class="fw-bold mt-3 text-dark">Absensi Selesai</h6>
                                    <p class="mb-0 text-muted small">Terima kasih, selamat beristirahat!</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div id="screen-riwayat" class="app-screen">
            <div class="bg-white p-4 desktop-px d-flex justify-content-between align-items-center border-bottom sticky-top" style="z-index: 10;">
                <h4 class="mb-0 text-dark fw-bold">Riwayat Kehadiran</h4>
                <button class="btn btn-outline-secondary btn-sm rounded-pill px-3 fw-bold"><i class="bi bi-funnel"></i> Filter</button>
            </div>
            
            <div class="p-3 desktop-px mx-auto mt-2" style="max-width: 1200px;">
                <div class="table-responsive bg-white rounded-4 shadow-sm border">
                    <table class="table table-hover table-riwayat align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="py-3 px-4 border-0">Tanggal</th>
                                <th class="py-3 border-0">Check In — Out</th>
                                <th class="py-3 border-0">Durasi</th>
                                <th class="py-3 border-0">Lembur</th>
                                <th class="py-3 border-0">Status</th>
                                <th class="py-3 border-0 text-end px-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($full_history->num_rows > 0): ?>
                                <?php while ($data = $full_history->fetch_assoc()):
                                    $durasi_teks = '-';
                                    if ($data['in_time'] && $data['out_time']) {
                                        $diff = strtotime($data['out_time']) - strtotime($data['in_time']);
                                        $durasi_teks = floor($diff/3600).' jam '.floor(($diff%3600)/60).' mnt';
                                    }
                                    
                                    $w_in  = $data['in_time']  ? date('H.i', strtotime($data['in_time']))  : '-';
                                    $w_out = $data['out_time'] ? date('H.i', strtotime($data['out_time'])) : '-';
                                    
                                    $status_in = $data['status_in'] ?: 'Tidak Hadir';
                                    $badge_class = 'bg-hadir';
                                    if ($status_in == 'Telat') { $status_in = 'Terlambat'; $badge_class = 'bg-terlambat'; }
                                    elseif ($status_in == 'Tidak Hadir') { $badge_class = 'bg-absen'; }
                                    elseif ($data['in_time'] && !$data['out_time']) { $status_in = 'Check In'; $badge_class = 'bg-checkin'; }

                                    $modalData = htmlspecialchars(json_encode([
                                        'tanggal'    => formatTanggalIndo($data['tgl']),
                                        'nama'       => $karyawan['nama'],
                                        'status'     => $status_in,
                                        'durasi'     => $durasi_teks,
                                        'in_time'    => $data['in_time']  ? date('H.i', strtotime($data['in_time']))  : '-',
                                        'out_time'   => $data['out_time'] ? date('H.i', strtotime($data['out_time'])) : '-',
                                        'in_lokasi'  => $data['lok_in']  ?: $karyawan['penempatan'],
                                        'out_lokasi' => $data['lok_out'] ?: $karyawan['penempatan'],
                                        'in_foto'    => $data['foto_in']  ?: '',
                                        'out_foto'   => $data['foto_out'] ?: '',
                                        'penempatan' => $karyawan['penempatan']
                                    ]));
                                ?>
                                <tr>
                                    <td class="py-3 px-4 fw-bold text-dark"><?= formatTanggalIndo($data['tgl']) ?></td>
                                    <td><i class="bi bi-clock text-success me-1"></i> <?= $w_in ?> <span class="text-muted mx-1">&mdash;</span> <?= $w_out ?></td>
                                    <td class="text-muted"><?= $durasi_teks ?></td>
                                    <td class="text-muted">-</td>
                                    <td><span class="badge-status <?= $badge_class ?>"><?= $status_in ?></span></td>
                                    <td class="text-end px-4">
                                        <button class="btn btn-sm btn-light border rounded-circle shadow-sm" onclick="bukaDetail(<?= $modalData ?>)">
                                            <i class="bi bi-three-dots-vertical text-dark"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-folder2-open fs-1 d-block mb-2"></i> Belum ada data kehadiran.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="screen-layanan" class="app-screen">
            <div class="bg-pink-wave p-4 desktop-px shadow-sm text-center mb-4" style="border-radius: 0 0 25px 25px;">
                <h4 class="mb-0 text-white fw-bold mt-2 pb-2">Layanan HRIS</h4>
            </div>
            <div class="p-3 desktop-px mx-auto" style="max-width: 1200px;">
                <?php if ($is_admin): ?>
                <h6 class="section-title mt-0 text-pink fw-bold"><i class="bi bi-shield-lock-fill me-2"></i>Menu HR & Owner</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6 col-lg-4" onclick="switchScreen('admin-absen')">
                        <div class="action-card shadow-sm" style="border-left:6px solid var(--lb-pink);">
                            <div class="action-icon"><i class="bi bi-people-fill"></i></div>
                            <div class="flex-grow-1"><h6 class="fw-bold mb-0 fs-6 text-dark">Kehadiran Tim</h6><small class="text-muted">Pantau absensi & foto</small></div>
                            <i class="bi bi-chevron-right ms-auto text-muted fs-5"></i>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <h6 class="section-title mt-0 fw-bold">Pengajuan & Dokumen</h6>
                <div class="row g-3">
                    <div class="col-md-6 col-lg-4" onclick="showModernAlert('Informasi Cuti','Status Anda <b><?= $karyawan['status_pegawai'] ?></b>.<br>Cuti tahunan baru dapat digunakan setelah <b><?= formatTanggal($karyawan['akhir_probation']) ?></b>.<br><br><span class=\'text-pink fw-bold\'>Fitur Izin Sakit akan segera hadir.</span>','bi bi-calendar-x-fill','var(--lb-pink)')">
                        <div class="action-card shadow-sm">
                            <div class="action-icon"><i class="bi bi-calendar-event text-dark"></i></div>
                            <div><h6 class="fw-bold mb-0 fs-6 text-dark">Pengajuan Cuti</h6><small class="text-muted">Cek kuota dan ajukan libur</small></div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4" onclick="aksesGajiDitolak()">
                        <div class="action-card shadow-sm bg-light">
                            <div class="action-icon bg-secondary bg-opacity-10"><i class="bi bi-lock-fill text-secondary"></i></div>
                            <div><h6 class="fw-bold mb-0 text-secondary fs-6">Slip Gaji <span class="badge bg-secondary ms-2" style="font-size:10px;">Terkunci</span></h6><small class="text-muted">Akses portal Finance</small></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($is_admin): ?>
        <div id="screen-admin-absen" class="app-screen">
            <div id="admin-view-list">
                <div class="bg-pink-wave p-4 desktop-px position-relative shadow-sm text-center mb-4" style="border-radius: 0 0 25px 25px;">
                    <button class="btn btn-link text-white position-absolute top-50 translate-middle-y start-0 ms-md-4 ms-2" onclick="switchScreen('layanan')"><i class="bi bi-arrow-left fs-3"></i></button>
                    <h4 class="mb-0 text-white fw-bold mt-2 pb-2">Pilih Karyawan</h4>
                </div>
                <div class="p-3 desktop-px mx-auto" style="max-width: 1200px;">
                    <p class="text-muted small mb-3">Ketuk nama karyawan untuk melihat riwayat absensinya.</p>
                    <div class="row g-3">
                        <?php foreach ($semua_karyawan as $kar): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card action-card rounded-4 h-100 shadow-sm" onclick="showEmployeeLog('<?= $kar['nik'] ?>', '<?= htmlspecialchars($kar['nama']) ?>')">
                                <div class="avatar-initials" style="width: 50px; height: 50px; font-size: 20px;">
                                    <?= $kar['inisial'] ?>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="fw-bold mb-1 text-dark"><?= $kar['nama'] ?></h6>
                                    <div class="text-muted small mb-1"><i class="bi bi-briefcase me-1"></i><?= $kar['posisi'] ?></div>
                                    <span class="badge bg-warning text-dark" style="font-size: 10px;"><?= $kar['status_pegawai'] ?></span>
                                </div>
                                <i class="bi bi-chevron-right text-muted fs-5"></i>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div id="admin-view-detail" style="display:none;">
                <div class="bg-pink-wave p-4 desktop-px position-relative shadow-sm text-center mb-4" style="border-radius: 0 0 25px 25px;">
                    <button class="btn btn-link text-white position-absolute top-50 translate-middle-y start-0 ms-md-4 ms-2" onclick="backToAdminList()"><i class="bi bi-arrow-left fs-3"></i></button>
                    <h4 class="mb-0 text-white fw-bold mt-2 pb-2" id="detail-nama-karyawan">Log Kehadiran</h4>
                </div>
                <div class="p-3 desktop-px mx-auto" style="max-width: 1200px;">
                    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
                        <div class="card-body p-3 p-md-4">
                            <h6 class="small fw-bold text-muted mb-2"><i class="bi bi-funnel-fill me-1"></i> Filter Tanggal</h6>
                            <div class="d-flex flex-wrap gap-2 gap-md-4">
                                <div class="flex-fill">
                                    <small class="text-muted" style="font-size:11px;">Dari Tanggal</small>
                                    <input type="date" id="filter-start" class="form-control form-control-lg border-0 bg-light shadow-sm rounded-3 fs-6">
                                </div>
                                <div class="flex-fill">
                                    <small class="text-muted" style="font-size:11px;">Sampai Tanggal</small>
                                    <input type="date" id="filter-end" class="form-control form-control-lg border-0 bg-light shadow-sm rounded-3 fs-6">
                                </div>
                                <div class="d-flex align-items-end mt-2 mt-md-0 w-100 w-md-auto">
                                    <button class="btn btn-lg btn-pink shadow-sm rounded-3 px-4 w-100" onclick="renderAdminTable()"><i class="bi bi-search me-2"></i>Cari</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive bg-white rounded-4 shadow-sm border" style="overflow: hidden;">
                        <table class="table table-hover table-admin align-middle mb-0 table-riwayat">
                            <thead class="table-light">
                                <tr>
                                    <th class="px-4 py-3 border-0">Tanggal & Status</th>
                                    <th class="text-center py-3 border-0">Jam Masuk</th>
                                    <th class="text-center py-3 border-0">Jam Pulang</th>
                                </tr>
                            </thead>
                            <tbody id="admin-detail-tbody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div id="screen-profil" class="app-screen">
            <div class="bg-pink-wave p-5 desktop-px text-center shadow-sm" style="border-radius: 0 0 25px 25px;">
                <div class="avatar-initials mx-auto mt-2 mb-3 bg-white text-pink shadow" style="width:100px; height:100px; font-size:38px; border:4px solid white;"><?= $inisial ?></div>
                <h3 class="text-white fw-bold mb-1"><?= htmlspecialchars($karyawan['nama']) ?></h3>
                <p class="text-white-50 mb-0 fs-5"><?= $karyawan['posisi'] ?></p>
            </div>
            <div class="p-3 desktop-px mx-auto" style="margin-top:-30px; max-width: 800px;">
                <div class="card mb-4">
                    <div class="card-body p-0">
                        <div class="p-4 pb-0"><h6 class="section-title mt-0 text-muted fw-bold">Informasi Pribadi</h6></div>
                        <ul class="list-group list-group-flush rounded-4">
                            <li class="list-group-item d-flex justify-content-between p-4"><span class="text-muted">NIK</span><span class="fw-bold"><?= $karyawan['nik'] ?></span></li>
                            <li class="list-group-item d-flex justify-content-between p-4"><span class="text-muted">Email</span><span class="fw-bold text-end"><?= $karyawan['email'] ?></span></li>
                            <li class="list-group-item d-flex justify-content-between p-4"><span class="text-muted">No. Handphone</span><span class="fw-bold"><?= $karyawan['no_hp'] ?></span></li>
                            <li class="list-group-item d-flex justify-content-between p-4"><span class="text-muted">Tgl Bergabung</span><span class="fw-bold"><?= formatTanggal($karyawan['tgl_bergabung']) ?></span></li>
                            
                            <a href="/ganti_password" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-4 text-decoration-none text-dark">
                                <div>
                                    <i class="bi bi-shield-lock-fill text-muted me-2"></i>
                                    <span class="fw-bold">Ubah Kata Sandi</span>
                                </div>
                                <i class="bi bi-chevron-right text-muted"></i>
                            </a>
                        </ul>
                    </div>
                </div>
                <a href="/logout" class="btn btn-outline-danger bg-white w-100 rounded-4 py-3 fw-bold mt-3 shadow-sm fs-5">
                    <i class="bi bi-box-arrow-right me-2"></i> Keluar Aplikasi
                </a>
            </div>
        </div>

    </div> 
</div> 

<div class="modal fade" id="modalDetailAbsen" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-bottom-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold text-dark fs-5">Detail Attendance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 pt-2">
                <p class="text-muted small mb-3 fs-6" id="mdl-header">Nama Karyawan • Tanggal</p>
                
                <div class="rounded-3 p-3 mb-4" id="mdl-status-box" style="background-color: #e8f5e9; border: 1px solid #c8e6c9;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-box-arrow-in-right text-success fs-4" id="mdl-status-icon"></i>
                            <span class="fw-bold fs-5 text-dark" id="mdl-status-text">
                                Hadir <span class="badge bg-success fw-normal ms-2" style="font-size:10px;">WFO</span>
                            </span>
                        </div>
                    </div>
                </div>

                <ul class="nav nav-pills custom-tabs mb-4 w-100 d-flex" id="pills-tab" role="tablist">
                    <li class="nav-item flex-fill text-center">
                        <button class="nav-link active w-100" data-bs-toggle="pill" data-bs-target="#pills-in" type="button"><i class="bi bi-box-arrow-in-right me-1"></i> Check In</button>
                    </li>
                    <li class="nav-item flex-fill text-center">
                        <button class="nav-link w-100" data-bs-toggle="pill" data-bs-target="#pills-out" type="button"><i class="bi bi-box-arrow-right me-1"></i> Check Out</button>
                    </li>
                    <li class="nav-item flex-fill text-center">
                        <button class="nav-link w-100" data-bs-toggle="pill" data-bs-target="#pills-data" type="button"><i class="bi bi-person me-1"></i> Data Karyawan</button>
                    </li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="pills-in">
                        <div class="detail-box p-4 shadow-sm border">
                            <h6 class="fw-bold small text-dark mb-3"><i class="bi bi-clock text-muted me-2"></i>Waktu Check In</h6>
                            <div class="d-flex justify-content-between text-success fw-bold fs-5">
                                <span class="text-muted fs-6 fw-normal">Pukul</span><span id="mdl-in-time">00.00</span>
                            </div>
                        </div>
                        <div class="detail-box p-4 shadow-sm border">
                            <h6 class="fw-bold small text-dark mb-3"><i class="bi bi-geo-alt text-muted me-2"></i>Lokasi</h6>
                            <p class="mb-3 fs-6 fw-bold text-dark" id="mdl-in-lokasi">-</p>
                            <hr class="text-muted" style="border-style: dashed;">
                            <div class="d-flex justify-content-between small text-success mb-2"><span>IP Address</span><span class="text-dark fw-bold">118.99.119.147</span></div>
                            <div class="d-flex justify-content-between small text-success"><span>Koordinat</span><span class="text-dark fw-bold">-5.387, 105.280</span></div>
                        </div>
                        <div class="detail-box p-4 shadow-sm border">
                            <h6 class="fw-bold small text-dark mb-3"><i class="bi bi-camera text-muted me-2"></i>Foto Selfie</h6>
                            <div class="camera-box" style="aspect-ratio: auto; min-height:300px;">
                                <img id="mdl-in-foto" src="" onerror="this.src='https://placehold.co/400x500?text=Tidak+Ada+Foto'">
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="pills-out">
                        <div class="detail-box p-4 shadow-sm border">
                            <h6 class="fw-bold small text-dark mb-3"><i class="bi bi-clock text-muted me-2"></i>Waktu Check Out</h6>
                            <div class="d-flex justify-content-between text-danger fw-bold fs-5">
                                <span class="text-muted fs-6 fw-normal">Pukul</span><span id="mdl-out-time">00.00</span>
                            </div>
                        </div>
                        <div class="detail-box p-4 shadow-sm border">
                            <h6 class="fw-bold small text-dark mb-3"><i class="bi bi-geo-alt text-muted me-2"></i>Lokasi</h6>
                            <p class="mb-3 fs-6 fw-bold text-dark" id="mdl-out-lokasi">-</p>
                            <hr class="text-muted" style="border-style: dashed;">
                            <div class="d-flex justify-content-between small text-danger mb-2"><span>IP Address</span><span class="text-dark fw-bold">118.99.119.147</span></div>
                            <div class="d-flex justify-content-between small text-danger"><span>Koordinat</span><span class="text-dark fw-bold">-5.387, 105.280</span></div>
                        </div>
                        <div class="detail-box p-4 shadow-sm border">
                            <h6 class="fw-bold small text-dark mb-3"><i class="bi bi-camera text-muted me-2"></i>Foto Selfie</h6>
                            <div class="camera-box" style="aspect-ratio: auto; min-height:300px;">
                                <img id="mdl-out-foto" src="" onerror="this.src='https://placehold.co/400x500?text=Tidak+Ada+Foto'">
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="pills-data">
                        <div class="detail-box p-4 shadow-sm border text-center">
                            <div class="avatar-initials mx-auto mb-3" style="width: 80px; height: 80px; font-size: 30px;">
                                <?= $inisial ?>
                            </div>
                            <h5 class="fw-bold text-dark mb-1"><?= $karyawan['nama'] ?></h5>
                            <p class="text-muted mb-4"><?= $karyawan['posisi'] ?></p>
                            <div class="text-start bg-light p-3 rounded-3">
                                <p class="mb-2 fs-6"><span class="text-muted">NIK</span> <strong class="float-end"><?= $karyawan['nik'] ?></strong></p>
                                <p class="mb-2 fs-6"><span class="text-muted">Status</span> <strong class="float-end"><?= $karyawan['status_pegawai'] ?></strong></p>
                                <p class="mb-0 fs-6"><span class="text-muted">Penempatan</span> <strong class="float-end"><?= $karyawan['penempatan'] ?></strong></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modernAlertModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-body text-center p-4 pt-5">
                <div id="modernAlertIcon" class="mb-3"><i class="bi bi-info-circle-fill" style="font-size:3.5rem; color:var(--lb-pink);"></i></div>
                <h5 class="fw-bold mb-3" id="modernAlertTitle">Pemberitahuan</h5>
                <p class="text-muted mb-4" id="modernAlertMessage" style="font-size:14px;">Pesan alert di sini.</p>
                <button type="button" class="btn btn-pink w-100 rounded-pill py-2 fw-bold" data-bs-dismiss="modal">Mengerti</button>
            </div>
        </div>
    </div>
</div>

<script>
    window.HRIS_CONFIG = {
        userNIK: '<?= $karyawan['nik'] ?>',
        karyawanNama: <?= json_encode($karyawan['nama']) ?>,
        karyawanPenempatan: <?= json_encode($karyawan['penempatan']) ?>,
        adminHistData: <?= json_encode($admin_hist_arr) ?>
    };
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/style/script.js"></script>

</body>
</html>