<?php
// api/index.php
include __DIR__ . '/koneksi.php';
date_default_timezone_set('Asia/Jakarta');

// AUTO-CREATE TABLES (ANTI ERROR / DATABASE TIDAK SINKRON)
$conn->query("CREATE TABLE IF NOT EXISTS `lembur` (
  `id` int(11) NOT NULL AUTO_INCREMENT, `nik` varchar(20) NOT NULL, `tanggal` date NOT NULL, `jam_mulai` time NOT NULL, `jam_selesai` time NOT NULL, `keterangan` text NOT NULL, `status` varchar(20) DEFAULT 'Pending', `created_at` timestamp DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`), FOREIGN KEY (`nik`) REFERENCES `karyawan` (`nik`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `pengajuan_cuti` (
  `id` int(11) NOT NULL AUTO_INCREMENT, `nik` varchar(20) NOT NULL, `jenis` varchar(50) NOT NULL, `tanggal_mulai` date NOT NULL, `tanggal_selesai` date NOT NULL, `keterangan` text NOT NULL, `status` varchar(20) DEFAULT 'Pending', `created_at` timestamp DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`), FOREIGN KEY (`nik`) REFERENCES `karyawan` (`nik`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `perjalanan_dinas` (
  `id` int(11) NOT NULL AUTO_INCREMENT, `nik` varchar(20) NOT NULL, `tujuan` varchar(255) NOT NULL, `tgl_berangkat` date NOT NULL, `tgl_kembali` date NOT NULL, `keterangan` text NOT NULL, `status` varchar(20) DEFAULT 'Pending', `created_at` timestamp DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`), FOREIGN KEY (`nik`) REFERENCES `karyawan` (`nik`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `reimburse` (
  `id` int(11) NOT NULL AUTO_INCREMENT, `nik` varchar(20) NOT NULL, `kategori` varchar(50) NOT NULL, `nominal` int(11) NOT NULL, `foto_nota` longtext NOT NULL, `keterangan` text NOT NULL, `status` varchar(20) DEFAULT 'Pending', `created_at` timestamp DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`), FOREIGN KEY (`nik`) REFERENCES `karyawan` (`nik`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// PROTEKSI HALAMAN - Pakai JWT
$karyawan = auth_required($conn);

// HAK AKSES ADMIN/HR (ANTI GAGAL & KEBAL SPASI)
$posisi   = trim(strtoupper($karyawan['posisi'] ?? ''));
$level    = trim(strtoupper($karyawan['level_jabatan'] ?? ''));
$is_admin = (strpos($posisi, 'HC') !== false || strpos($posisi, 'HR') !== false || in_array($level, ['OWNER', 'DIREKTUR']));

// FUNGSI BANTUAN
function getInitials($nama)
{
    $p = explode(" ", $nama);
    return strtoupper(substr($p[0], 0, 1) . (isset($p[1]) ? substr($p[1], 0, 1) : ''));
}
$inisial = getInitials($karyawan['nama']);

function formatTanggal($tanggal)
{
    if ($tanggal == '0000-00-00' || !$tanggal) return "-";
    return date('d-m-Y', strtotime($tanggal));
}

function formatTanggalIndo($tanggal)
{
    $bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
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

$cek_in   = $conn->query("SELECT waktu, status FROM absensi WHERE nik='" . $karyawan['nik'] . "' AND jenis='Check In' AND DATE(waktu)='$hari_ini'");
$data_in  = $cek_in->fetch_assoc();
$sudah_in = $cek_in->num_rows > 0;
$waktu_in = $sudah_in ? date('H:i', strtotime($data_in['waktu'])) : '--:--';
$status_in = $sudah_in ? $data_in['status'] : '-';

$cek_out   = $conn->query("SELECT waktu FROM absensi WHERE nik='" . $karyawan['nik'] . "' AND jenis='Check Out' AND DATE(waktu)='$hari_ini'");
$data_out  = $cek_out->fetch_assoc();
$sudah_out = $cek_out->num_rows > 0;
$waktu_out = $sudah_out ? date('H:i', strtotime($data_out['waktu'])) : '--:--';

$show_camera = false;
$jenis_absen_sekarang = '';
if (!$sudah_in && !$belum_waktunya_in && !$lewat_batas_in) {
    $show_camera = true;
    $jenis_absen_sekarang = 'Check In';
} elseif ($sudah_in && !$sudah_out && !$belum_waktunya_out) {
    $show_camera = true;
    $jenis_absen_sekarang = 'Check Out';
}

// =========================================================
// PENGGABUNGAN DATA RIWAYAT PRIBADI (ABSEN + LEMBUR + CUTI)
// =========================================================
$riwayat_pribadi = [];

// 1. Ambil Absensi
$q_absensi = $conn->query("SELECT DATE(waktu) as tgl, MAX(CASE WHEN jenis='Check In' THEN waktu END) as in_time, MAX(CASE WHEN jenis='Check Out' THEN waktu END) as out_time, MAX(CASE WHEN jenis='Check In' THEN status END) as status_in, MAX(CASE WHEN jenis='Check In' THEN lokasi END) as lok_in, MAX(CASE WHEN jenis='Check Out' THEN lokasi END) as lok_out, MAX(CASE WHEN jenis='Check In' THEN foto END) as foto_in, MAX(CASE WHEN jenis='Check Out' THEN foto END) as foto_out FROM absensi WHERE nik='" . $karyawan['nik'] . "' GROUP BY DATE(waktu)");
if ($q_absensi) {
    while ($row = $q_absensi->fetch_assoc()) {
        $riwayat_pribadi[$row['tgl']]['absen'] = $row;
    }
}

// 2. Ambil Lembur
$q_lembur_pribadi = $conn->query("SELECT * FROM lembur WHERE nik='" . $karyawan['nik'] . "'");
if ($q_lembur_pribadi) {
    while ($row = $q_lembur_pribadi->fetch_assoc()) {
        $riwayat_pribadi[$row['tanggal']]['lembur'] = $row;
    }
}

// 3. Ambil Cuti/Libur
$q_cuti_pribadi = $conn->query("SELECT * FROM pengajuan_cuti WHERE nik='" . $karyawan['nik'] . "'");
if ($q_cuti_pribadi) {
    while ($row = $q_cuti_pribadi->fetch_assoc()) {
        $start = new DateTime($row['tanggal_mulai']);
        $end = new DateTime($row['tanggal_selesai']);
        $end->modify('+1 day'); // include hari terakhir
        $period = new DatePeriod($start, new DateInterval('P1D'), $end);
        foreach ($period as $dt) {
            $riwayat_pribadi[$dt->format('Y-m-d')]['cuti'] = $row;
        }
    }
}

// 4. Ambil Dinas
$q_dinas_pribadi = $conn->query("SELECT * FROM perjalanan_dinas WHERE nik='" . $karyawan['nik'] . "'");
if ($q_dinas_pribadi) {
    while ($row = $q_dinas_pribadi->fetch_assoc()) {
        $start = new DateTime($row['tgl_berangkat']);
        $end = new DateTime($row['tgl_kembali']);
        $end->modify('+1 day');
        $period = new DatePeriod($start, new DateInterval('P1D'), $end);
        foreach ($period as $dt) {
            $riwayat_pribadi[$dt->format('Y-m-d')]['dinas'] = $row;
        }
    }
}

krsort($riwayat_pribadi);

// DATA ADMIN
$semua_karyawan = [];
$admin_hist_arr = [];
$data_approval_lembur = [];
$data_approval_cuti = [];
$data_approval_dinas = [];
$data_approval_reimburse = [];

if ($is_admin) {
    $q_kar = $conn->query("SELECT nik, nama, posisi, status_pegawai FROM karyawan ORDER BY nama ASC");
    while ($r = $q_kar->fetch_assoc()) {
        $r['inisial'] = getInitials($r['nama']);
        $semua_karyawan[] = $r;
    }

    $q_hist = $conn->query("SELECT 
        DATE(a.waktu) as tgl, a.nik, k.nama, k.posisi, MAX(CASE WHEN a.jenis='Check In' THEN a.waktu END) as in_time, MAX(CASE WHEN a.jenis='Check Out' THEN a.waktu END) as out_time, MAX(CASE WHEN a.jenis='Check In' THEN a.status END) as status_in, MAX(CASE WHEN a.jenis='Check In' THEN a.lokasi END) as lok_in, MAX(CASE WHEN a.jenis='Check Out' THEN a.lokasi END) as lok_out, MAX(CASE WHEN a.jenis='Check In' THEN a.foto END) as foto_in, MAX(CASE WHEN a.jenis='Check Out' THEN a.foto END) as foto_out
    FROM absensi a JOIN karyawan k ON a.nik=k.nik GROUP BY DATE(a.waktu), a.nik ORDER BY tgl DESC");
    while ($r = $q_hist->fetch_assoc()) {
        $admin_hist_arr[] = $r;
    }

    try {
        $q_lembur = $conn->query("SELECT l.*, k.nama, k.posisi FROM lembur l JOIN karyawan k ON l.nik=k.nik ORDER BY l.id DESC");
        if ($q_lembur) {
            while ($r = $q_lembur->fetch_assoc()) {
                $data_approval_lembur[] = $r;
            }
        }

        $q_cuti = $conn->query("SELECT c.*, k.nama, k.posisi FROM pengajuan_cuti c JOIN karyawan k ON c.nik=k.nik ORDER BY c.id DESC");
        if ($q_cuti) {
            while ($r = $q_cuti->fetch_assoc()) {
                $data_approval_cuti[] = $r;
            }
        }

        $q_dinas = $conn->query("SELECT d.*, k.nama, k.posisi FROM perjalanan_dinas d JOIN karyawan k ON d.nik=k.nik ORDER BY d.id DESC");
        if ($q_dinas) {
            while ($r = $q_dinas->fetch_assoc()) {
                $data_approval_dinas[] = $r;
            }
        }

        $q_reimburse = $conn->query("SELECT r.*, k.nama, k.posisi FROM reimburse r JOIN karyawan k ON r.nik=k.nik ORDER BY r.id DESC");
        if ($q_reimburse) {
            while ($r = $q_reimburse->fetch_assoc()) {
                $data_approval_reimburse[] = $r;
            }
        }
    } catch (Exception $e) {
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="/style/style.css">
</head>

<body>

    <div id="splash-screen" class="splash-screen">
        <div class="splash-content px-4">
            <div class="logo-box-splash mb-3 mx-auto shadow-lg">
                <img src="/logo/lbqueen_logo.PNG" alt="LBQueen Logo" onerror="this.style.display='none'">
            </div>
            <h4 class="text-white fw-bold mb-1 tracking-wider splash-title">HRIS LBQueen</h4>
            <p class="text-white-50 mb-4 splash-subtitle">Mempersiapkan ruang kerja...</p>
            <div class="spinner-border text-white opacity-75" role="status" style="width: 2rem; height: 2rem; border-width: 0.2em;">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    </div>

    <div class="app-wrapper">

        <div class="sidebar-nav">
            <div class="sidebar-logo">
                <img src="/logo/lbqueen_logo.PNG" alt="LBQueen" style="width:80px; margin-bottom:10px; border-radius:10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
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
                        <img src="/logo/lbqueen_logo.PNG" alt="Logo" style="height:45px; background:white; border-radius:12px; padding:6px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                    </div>
                </div>

                <div class="p-3 desktop-px mx-auto" style="max-width: 1200px;">
                    <div class="card mb-3 overlap-card shadow-sm" style="margin-top:-35px; z-index: 10; position:relative;">
                        <div class="card-body d-flex align-items-center gap-3 p-3">
                            <div class="avatar-initials" style="width: 55px; height: 55px; font-size: 22px;"><?= $inisial ?></div>
                            <div>
                                <h6 class="fw-bold mb-1 text-dark section-title" style="margin-bottom:2px; font-size:16px;"><?= $karyawan['posisi'] ?></h6>
                                <span class="badge bg-warning text-dark me-1 shadow-sm"><?= $karyawan['status_pegawai'] ?></span>
                                <span class="badge bg-secondary shadow-sm"><i class="bi bi-geo-alt-fill me-1"></i> <?= $karyawan['penempatan'] ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3 clock-banner position-relative shadow-sm rounded-4">
                        <div class="card-body p-4">
                            <div class="position-relative" style="z-index: 2;">
                                <p class="mb-1 text-white-50 small fw-bold text-uppercase tracking-wider">Jam Digital Kehadiran</p>
                                <h1 class="display-4 fw-bold text-white mb-3" id="clock-display" style="letter-spacing: -1px;">00:00:00 <span class="fs-4">WIB</span></h1>
                                <div class="d-inline-flex align-items-center bg-white bg-opacity-25 text-white rounded-pill px-3 py-2 shadow-sm">
                                    <i class="bi bi-calendar3 me-2"></i>
                                    <span id="date-display" class="small fw-bold">Memuat tanggal...</span>
                                </div>
                            </div>
                            <i class="bi bi-clock position-absolute text-white opacity-10" style="font-size: 11rem; right: -20px; bottom: -30px; z-index: 1;"></i>
                        </div>
                    </div>

                    <div class="card mb-3 shadow-sm rounded-4">
                        <div class="card-body p-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div class="d-flex align-items-center gap-3">
                                <div class="status-icon bg-light text-secondary shadow-sm" style="width:40px; height:40px;"><i class="bi bi-stopwatch fs-5"></i></div>
                                <div>
                                    <small class="text-muted fw-bold d-block text-uppercase" style="font-size:10px; letter-spacing:0.5px;">Jadwal Kerja Anda</small>
                                    <span class="fw-bold fs-6 text-dark">09:00 - 19:00 <small class="text-muted fw-normal">WIB</small></span>
                                </div>
                            </div>
                            <span class="badge bg-light text-success border border-success rounded-pill px-3 py-2 fw-bold"><i class="bi bi-info-circle-fill me-1"></i> Penempatan Aktif</span>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <div class="card h-100 shadow-sm rounded-4">
                                <div class="card-body p-3">
                                    <div class="d-flex align-items-center gap-2 mb-3">
                                        <div class="status-icon <?= $sudah_in ? 'bg-success text-white' : 'bg-light text-secondary' ?> shadow-sm"><i class="bi <?= $sudah_in ? 'bi-check-lg' : 'bi-dash-lg' ?>"></i></div>
                                        <div>
                                            <h6 class="fw-bold mb-0 text-dark" style="font-size: 13px;">Status Masuk</h6>
                                            <small class="text-muted" style="font-size: 10px;"><?= $sudah_in ? 'Selesai' : 'Belum Absen' ?></small>
                                        </div>
                                    </div>
                                    <h4 class="fw-bold mb-1 <?= $sudah_in ? 'text-dark' : 'text-muted' ?>"><?= $waktu_in ?> <small class="fs-6 text-muted fw-normal">WIB</small></h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card h-100 shadow-sm rounded-4">
                                <div class="card-body p-3">
                                    <div class="d-flex align-items-center gap-2 mb-3">
                                        <div class="status-icon <?= $sudah_out ? 'bg-danger text-white' : 'bg-light text-secondary' ?> shadow-sm"><i class="bi <?= $sudah_out ? 'bi-check-lg' : 'bi-dash-lg' ?>"></i></div>
                                        <div>
                                            <h6 class="fw-bold mb-0 text-dark" style="font-size: 13px;">Status Pulang</h6>
                                            <small class="text-muted" style="font-size: 10px;"><?= $sudah_out ? 'Selesai' : 'Belum Absen' ?></small>
                                        </div>
                                    </div>
                                    <h4 class="fw-bold mb-1 <?= $sudah_out ? 'text-dark' : 'text-muted' ?>"><?= $waktu_out ?> <small class="fs-6 text-muted fw-normal">WIB</small></h4>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-6">
                            <button class="btn btn-warning text-dark w-100 rounded-pill py-3 fw-bold shadow-sm" onclick="new bootstrap.Modal(document.getElementById('modalLembur')).show()"><i class="bi bi-clock-fill me-2"></i> Ajukan Lembur</button>
                        </div>
                        <div class="col-6">
                            <button class="btn btn-info text-white w-100 rounded-pill py-3 fw-bold shadow-sm" onclick="new bootstrap.Modal(document.getElementById('modalCuti')).show()"><i class="bi bi-calendar-event-fill me-2"></i> Ajukan Libur</button>
                        </div>
                    </div>

                    <div class="card mb-4 shadow-sm rounded-4 border-0" style="background: linear-gradient(to bottom, #ffffff, #fafafa);">
                        <div class="card-body p-4">
                            <div class="text-center mb-4">
                                <h5 class="fw-bold mb-1 text-dark"><?= $show_camera ? ($jenis_absen_sekarang == 'Check In' ? 'Akses Check In Dibuka' : 'Akses Check Out Dibuka') : 'Absensi Selesai / Terkunci' ?></h5>
                                <p class="text-muted small">Sistem merekam lokasi, IP, dan foto secara real-time.</p>
                            </div>

                            <?php if ($show_camera): ?>
                                <div class="mb-3 text-center">
                                    <div class="camera-box bg-dark">
                                        <video id="kamera" autoplay playsinline></video>
                                        <img id="kamera-preview" style="display:none;" />
                                        <canvas id="canvas_kamera" style="display:none;"></canvas>
                                    </div>
                                    <small class="text-muted d-block mt-3"><i class="bi bi-shield-check text-success me-1"></i>Pastikan wajah terlihat jelas dan berada di area kantor.</small>
                                </div>

                                <div id="absen-response" class="mb-3 text-center fw-bold"></div>

                                <div id="btn-action-group" class="d-flex justify-content-center gap-3 mt-4 mx-auto" style="max-width: 400px;">
                                    <?php if ($jenis_absen_sekarang == 'Check In'): ?>
                                        <button class="btn btn-success w-100 rounded-pill py-3 fw-bold shadow" onclick="ambilFoto('Check In')"><i class="bi bi-camera-fill me-2 fs-5"></i> Ambil Foto Check In</button>
                                    <?php else: ?>
                                        <button class="btn btn-danger w-100 rounded-pill py-3 fw-bold shadow" onclick="ambilFoto('Check Out')"><i class="bi bi-camera-fill me-2 fs-5"></i> Ambil Foto Check Out</button>
                                    <?php endif; ?>
                                </div>

                                <div id="btn-confirm-group" class="d-flex justify-content-center gap-3 mt-4 mx-auto" style="max-width: 400px; display:none !important;">
                                    <button class="btn btn-outline-secondary flex-fill rounded-pill py-3 fw-bold bg-white" onclick="batalFoto()"><i class="bi bi-arrow-counterclockwise me-1"></i> Batal</button>
                                    <button class="btn btn-pink flex-fill rounded-pill py-3 fw-bold shadow" onclick="submitAbsen()"><span id="confirm-text">Kirim Absen</span> <i class="bi bi-send-fill ms-2"></i></button>
                                </div>
                            <?php else: ?>
                                <div class="text-center p-4 rounded-4 mx-auto border" style="max-width: 350px; background-color:#fff;">
                                    <?php if (!$sudah_in && $belum_waktunya_in): ?>
                                        <div class="bg-warning bg-opacity-10 rounded-circle d-inline-flex p-3 mb-3"><i class="bi bi-clock-history text-warning" style="font-size: 2.5rem;"></i></div>
                                        <h6 class="fw-bold text-dark">Belum Waktunya</h6>
                                        <p class="mb-0 text-muted small">Check In baru bisa dilakukan mulai pukul 08:30 WIB.</p>
                                    <?php elseif (!$sudah_in && $lewat_batas_in): ?>
                                        <div class="bg-danger bg-opacity-10 rounded-circle d-inline-flex p-3 mb-3"><i class="bi bi-x-circle text-danger" style="font-size: 2.5rem;"></i></div>
                                        <h6 class="fw-bold text-danger">Terlambat Check In</h6>
                                        <p class="mb-0 text-muted small">Batas Check In (10:30 WIB) telah terlewat untuk hari ini.</p>
                                    <?php elseif ($sudah_in && !$sudah_out && $belum_waktunya_out): ?>
                                        <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex p-3 mb-3"><i class="bi bi-briefcase text-primary" style="font-size: 2.5rem;"></i></div>
                                        <h6 class="fw-bold text-dark">Selamat Bekerja!</h6>
                                        <p class="mb-0 text-muted small">Absen Check Out baru bisa dilakukan mulai pukul 18:00 WIB.</p>
                                    <?php elseif ($sudah_out): ?>
                                        <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex p-3 mb-3"><i class="bi bi-calendar2-check text-success" style="font-size: 2.5rem;"></i></div>
                                        <h6 class="fw-bold text-dark">Absensi Selesai</h6>
                                        <p class="mb-0 text-muted small">Terima kasih, Anda telah menyelesaikan absensi hari ini.</p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div id="screen-riwayat" class="app-screen">
                <div class="bg-pink-wave header-top p-4 desktop-px position-relative shadow-sm text-center mb-4" style="border-radius: 0 0 25px 25px;">
                    <h4 class="mb-0 text-white fw-bold mt-2 pb-2"><i class="bi bi-clock-history me-2"></i>Riwayat Kehadiran</h4>
                </div>

                <div class="p-3 desktop-px mx-auto" style="max-width: 1200px;">
                    <div class="d-flex justify-content-end mb-3">
                        <button class="btn btn-light border btn-sm rounded-pill px-4 fw-bold shadow-sm text-dark">
                            <i class="bi bi-funnel-fill me-1"></i> Filter Pencarian
                        </button>
                    </div>

                    <div class="table-responsive bg-white rounded-4 shadow-sm border p-0">
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
                                <?php if (count($riwayat_pribadi) > 0): ?>
                                    <?php foreach ($riwayat_pribadi as $tgl => $data):
                                        $w_in = '-';
                                        $w_out = '-';
                                        $durasi_teks = '-';
                                        $status_absen = 'Tidak Hadir';
                                        $badge_class = 'bg-secondary';
                                        $lembur_teks = '-';
                                        $modal_in_time = '-';
                                        $modal_out_time = '-';
                                        $modal_in_loc = '-';
                                        $modal_out_loc = '-';
                                        $modal_in_foto = '';
                                        $modal_out_foto = '';

                                        // Set Cuti (Jika ada)
                                        if (isset($data['cuti'])) {
                                            $status_absen = $data['cuti']['jenis'];
                                            if ($data['cuti']['status'] == 'Pending') {
                                                $badge_class = 'bg-warning text-dark';
                                                $status_absen .= ' (Pending)';
                                            } elseif ($data['cuti']['status'] == 'Ditolak') {
                                                $badge_class = 'bg-danger';
                                                $status_absen .= ' (Ditolak)';
                                            } else {
                                                $badge_class = 'bg-info text-dark';
                                            }
                                        }

                                        // Set Dinas (Jika ada)
                                        if (isset($data['dinas'])) {
                                            $status_absen = 'Dinas Luar Kota';
                                            if ($data['dinas']['status'] == 'Pending') {
                                                $badge_class = 'bg-warning text-dark';
                                                $status_absen .= ' (Pending)';
                                            } elseif ($data['dinas']['status'] == 'Ditolak') {
                                                $badge_class = 'bg-danger';
                                                $status_absen .= ' (Ditolak)';
                                            } else {
                                                $badge_class = 'bg-primary';
                                            }
                                        }

                                        // Set Absensi
                                        if (isset($data['absen'])) {
                                            $abs = $data['absen'];
                                            if ($abs['in_time'] && $abs['out_time']) {
                                                $diff = strtotime($abs['out_time']) - strtotime($abs['in_time']);
                                                $durasi_teks = floor($diff / 3600) . ' jam ' . floor(($diff % 3600) / 60) . ' mnt';
                                            }
                                            $w_in  = $abs['in_time'] ? date('H:i', strtotime($abs['in_time'])) : '-';
                                            $w_out = $abs['out_time'] ? date('H:i', strtotime($abs['out_time'])) : '-';
                                            $modal_in_time = $abs['in_time'] ? date('H.i', strtotime($abs['in_time'])) : '-';
                                            $modal_out_time = $abs['out_time'] ? date('H.i', strtotime($abs['out_time'])) : '-';
                                            $modal_in_loc = $abs['lok_in'];
                                            $modal_out_loc = $abs['lok_out'];
                                            $modal_in_foto = $abs['foto_in'];
                                            $modal_out_foto = $abs['foto_out'];

                                            if (!isset($data['cuti'])) {
                                                $st = $abs['status_in'];
                                                if ($st == 'Telat' || $st == 'Terlambat') {
                                                    $status_absen = 'Terlambat';
                                                    $badge_class = 'bg-warning text-dark';
                                                } else {
                                                    $status_absen = 'Hadir';
                                                    $badge_class = 'bg-success';
                                                }
                                            }
                                        }

                                        // Set Lembur
                                        if (isset($data['lembur'])) {
                                            $lmb = $data['lembur'];
                                            $lembur_teks = date('H:i', strtotime($lmb['jam_mulai'])) . ' - ' . date('H:i', strtotime($lmb['jam_selesai']));
                                            if ($lmb['status'] == 'Pending') $lembur_teks .= ' <span class="badge bg-warning text-dark border border-dark" style="font-size:9px;">Pending</span>';
                                            elseif ($lmb['status'] == 'Ditolak') $lembur_teks .= ' <span class="badge bg-danger" style="font-size:9px;">Ditolak</span>';
                                            else $lembur_teks .= ' <span class="badge bg-success" style="font-size:9px;">Disetujui</span>';
                                        }

                                        $modalData = htmlspecialchars(json_encode([
                                            'tanggal'    => formatTanggalIndo($tgl),
                                            'nama'       => $karyawan['nama'],
                                            'status'     => $status_absen,
                                            'durasi'     => $durasi_teks,
                                            'in_time'    => $modal_in_time,
                                            'out_time'   => $modal_out_time,
                                            'in_lokasi'  => $modal_in_loc ?: $karyawan['penempatan'],
                                            'out_lokasi' => $modal_out_loc ?: $karyawan['penempatan'],
                                            'in_foto'    => $modal_in_foto ?: '',
                                            'out_foto'   => $modal_out_foto ?: '',
                                            'penempatan' => $karyawan['penempatan']
                                        ]));
                                    ?>
                                        <tr style="cursor: pointer;" onclick="bukaDetail(<?= $modalData ?>)">
                                            <td class="py-3 px-4 fw-bold text-dark"><?= formatTanggalIndo($tgl) ?></td>
                                            <td><i class="bi bi-clock-fill text-success me-1 opacity-75"></i> <?= $w_in ?> <span class="text-muted mx-1">&mdash;</span> <?= $w_out ?></td>
                                            <td class="text-muted fw-500"><?= $durasi_teks ?></td>
                                            <td class="text-muted fw-500"><?= $lembur_teks ?></td>
                                            <td><span class="badge <?= $badge_class ?> shadow-sm border"><?= $status_absen ?></span></td>
                                            <td class="text-end px-4">
                                                <button class="btn btn-sm btn-light border rounded-circle shadow-sm" onclick="event.stopPropagation(); bukaDetail(<?= $modalData ?>)">
                                                    <i class="bi bi-three-dots-vertical text-dark"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-folder2-open fs-1 d-block mb-2 text-black-50"></i> Belum ada riwayat kehadiran.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="screen-layanan" class="app-screen">
                <div class="bg-pink-wave header-top p-4 desktop-px shadow-sm text-center mb-4" style="border-radius: 0 0 25px 25px;">
                    <h4 class="mb-0 text-white fw-bold mt-2 pb-2">Pusat Layanan HRIS</h4>
                </div>
                <div class="p-3 desktop-px mx-auto" style="max-width: 1200px;">

                    <?php if ($is_admin): ?>
                        <h6 class="section-title mt-0 text-pink fw-bold"><i class="bi bi-shield-lock-fill me-2"></i>Menu Administrator & HR</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6 col-lg-3" onclick="switchScreen('admin-absen')">
                                <div class="action-card shadow-sm p-4 bg-white rounded-4 d-flex align-items-center employee-card" style="border-left:6px solid var(--lb-pink);">
                                    <div class="action-icon bg-pink-light text-pink fs-3 shadow-sm rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;"><i class="bi bi-people-fill"></i></div>
                                    <div class="flex-grow-1">
                                        <h6 class="fw-bold mb-0 fs-6 text-dark">Kehadiran Tim</h6><small class="text-muted">Pantau & Cetak Laporan</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-3" onclick="switchScreen('admin-lembur')">
                                <div class="action-card shadow-sm p-4 bg-white rounded-4 d-flex align-items-center employee-card" style="border-left:6px solid #fd7e14;">
                                    <div class="action-icon bg-warning bg-opacity-10 text-warning fs-3 shadow-sm rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;"><i class="bi bi-clock-history"></i></div>
                                    <div class="flex-grow-1">
                                        <h6 class="fw-bold mb-0 fs-6 text-dark">Approval Lembur</h6><small class="text-muted">Persetujuan & Riwayat</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-3" onclick="switchScreen('admin-cuti')">
                                <div class="action-card shadow-sm p-4 bg-white rounded-4 d-flex align-items-center employee-card" style="border-left:6px solid #0dcaf0;">
                                    <div class="action-icon bg-info bg-opacity-10 text-info fs-3 shadow-sm rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;"><i class="bi bi-calendar2-check-fill"></i></div>
                                    <div class="flex-grow-1">
                                        <h6 class="fw-bold mb-0 fs-6 text-dark">Approval Libur</h6><small class="text-muted">Cuti & Libur Mingguan</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-3" onclick="switchScreen('admin-dinas')">
                                <div class="action-card shadow-sm p-4 bg-white rounded-4 d-flex align-items-center employee-card" style="border-left:6px solid #0d6efd;">
                                    <div class="action-icon bg-primary bg-opacity-10 text-primary fs-3 shadow-sm rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;"><i class="bi bi-car-front-fill"></i></div>
                                    <div class="flex-grow-1">
                                        <h6 class="fw-bold mb-0 fs-6 text-dark">Approval Dinas</h6><small class="text-muted">Perjalanan Luar Kota</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-3" onclick="switchScreen('admin-reimburse')">
                                <div class="action-card shadow-sm p-4 bg-white rounded-4 d-flex align-items-center employee-card" style="border-left:6px solid #198754;">
                                    <div class="action-icon bg-success bg-opacity-10 text-success fs-3 shadow-sm rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;"><i class="bi bi-receipt-cutoff"></i></div>
                                    <div class="flex-grow-1">
                                        <h6 class="fw-bold mb-0 fs-6 text-dark">Approval Reimburse</h6><small class="text-muted">Klaim Dana Operasional</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-3" onclick="new bootstrap.Modal(document.getElementById('modalPayroll')).show()">
                                <div class="action-card shadow-sm p-4 bg-white rounded-4 d-flex align-items-center employee-card" style="border-left:6px solid #198754;">
                                    <div class="action-icon bg-success bg-opacity-10 text-success fs-3 shadow-sm rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;"><i class="bi bi-cash-coin"></i></div>
                                    <div class="flex-grow-1">
                                        <h6 class="fw-bold mb-0 fs-6 text-dark">Sistem Payroll</h6><small class="text-muted">Cetak Slip Gaji Karyawan</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <h6 class="section-title mt-0 fw-bold text-dark"><i class="bi bi-folder-fill text-muted me-2"></i>Pengajuan & Dokumen</h6>
                    <div class="row g-3">
                        <div class="col-md-6 col-lg-3" onclick="new bootstrap.Modal(document.getElementById('modalCuti')).show()">
                            <div class="action-card shadow-sm p-4 bg-white rounded-4 d-flex align-items-center employee-card border">
                                <div class="action-icon bg-info bg-opacity-10 text-info fs-3 shadow-sm rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;"><i class="bi bi-calendar-event-fill"></i></div>
                                <div class="flex-grow-1">
                                    <h6 class="fw-bold mb-0 fs-6 text-dark">Libur & Cuti</h6><small class="text-muted">Pilih hari libur mingguan</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3" onclick="new bootstrap.Modal(document.getElementById('modalLembur')).show()">
                            <div class="action-card shadow-sm p-4 bg-white rounded-4 d-flex align-items-center employee-card border">
                                <div class="action-icon bg-warning bg-opacity-10 text-warning fs-3 shadow-sm rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;"><i class="bi bi-stopwatch-fill"></i></div>
                                <div class="flex-grow-1">
                                    <h6 class="fw-bold mb-0 fs-6 text-dark">Pengajuan Lembur</h6><small class="text-muted">Rencana kerja tambahan</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3" onclick="new bootstrap.Modal(document.getElementById('modalDinas')).show()">
                            <div class="action-card shadow-sm p-4 bg-white rounded-4 d-flex align-items-center employee-card border">
                                <div class="action-icon bg-primary bg-opacity-10 text-primary fs-3 shadow-sm rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;"><i class="bi bi-car-front-fill"></i></div>
                                <div class="flex-grow-1">
                                    <h6 class="fw-bold mb-0 fs-6 text-dark">Perjalanan Dinas</h6><small class="text-muted">Formulir penugasan luar kota</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3" onclick="new bootstrap.Modal(document.getElementById('modalReimburse')).show()">
                            <div class="action-card shadow-sm p-4 bg-white rounded-4 d-flex align-items-center employee-card border">
                                <div class="action-icon bg-success bg-opacity-10 text-success fs-3 shadow-sm rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;"><i class="bi bi-receipt-cutoff"></i></div>
                                <div class="flex-grow-1">
                                    <h6 class="fw-bold mb-0 fs-6 text-dark">Reimbursement</h6><small class="text-muted">Pengajuan dana operasional</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($is_admin): ?>

                <div id="screen-admin-absen" class="app-screen">
                    <div id="admin-view-list">
                        <div class="bg-pink-wave header-top p-4 desktop-px position-relative shadow-sm text-center mb-4" style="border-radius: 0 0 25px 25px;">
                            <button class="btn btn-link text-white position-absolute top-50 translate-middle-y start-0 ms-md-4 ms-2" onclick="switchScreen('layanan')"><i class="bi bi-arrow-left fs-3"></i></button>
                            <h4 class="mb-0 text-white fw-bold mt-2 pb-2">Log Kehadiran Tim</h4>
                        </div>
                        <div class="p-3 desktop-px mx-auto" style="max-width: 1200px;">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <p class="text-muted small mb-0 fw-bold">Pilih Karyawan</p>
                                <button class="btn btn-outline-danger btn-sm rounded-pill fw-bold shadow-sm" onclick="new bootstrap.Modal(document.getElementById('modalCetakLaporan')).show()"><i class="bi bi-printer-fill me-1"></i> Cetak Rekap Bulanan</button>
                            </div>

                            <div class="row g-3">
                                <?php foreach ($semua_karyawan as $kar): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="card action-card rounded-4 h-100 shadow-sm border" onclick="showEmployeeLog('<?= $kar['nik'] ?>', '<?= htmlspecialchars($kar['nama']) ?>')">
                                            <div class="avatar-initials shadow-sm" style="width: 50px; height: 50px; font-size: 20px;">
                                                <?= $kar['inisial'] ?>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="fw-bold mb-1 text-dark"><?= $kar['nama'] ?></h6>
                                                <div class="text-muted small mb-1"><i class="bi bi-briefcase-fill me-1 opacity-50"></i><?= $kar['posisi'] ?></div>
                                                <span class="badge bg-warning text-dark shadow-sm" style="font-size: 10px;"><?= $kar['status_pegawai'] ?></span>
                                            </div>
                                            <i class="bi bi-chevron-right text-muted fs-5"></i>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div id="admin-view-detail" style="display:none;">
                        <div class="bg-pink-wave header-top p-4 desktop-px position-relative shadow-sm text-center mb-4" style="border-radius: 0 0 25px 25px;">
                            <button class="btn btn-link text-white position-absolute top-50 translate-middle-y start-0 ms-md-4 ms-2" onclick="backToAdminList()"><i class="bi bi-arrow-left fs-3"></i></button>
                            <h4 class="mb-0 text-white fw-bold mt-2 pb-2" id="detail-nama-karyawan">Log Kehadiran</h4>
                        </div>
                        <div class="p-3 desktop-px mx-auto" style="max-width: 1200px;">

                            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                                <h6 class="small fw-bold text-muted mb-0"><i class="bi bi-funnel-fill me-1 text-pink"></i> Filter Pencarian</h6>
                                <button class="btn btn-outline-danger btn-sm rounded-pill fw-bold shadow-sm" onclick="bukaModalCetakKaryawan()"><i class="bi bi-file-earmark-pdf-fill me-1"></i> Cetak Laporan Pribadi</button>
                            </div>

                            <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
                                <div class="card-body p-3 p-md-4">
                                    <div class="d-flex flex-wrap gap-2 gap-md-4">
                                        <div class="flex-fill">
                                            <small class="text-muted fw-bold" style="font-size:11px;">Dari Tanggal</small>
                                            <input type="date" id="filter-start" class="form-control form-control-lg border-0 bg-light shadow-sm rounded-3 fs-6">
                                        </div>
                                        <div class="flex-fill">
                                            <small class="text-muted fw-bold" style="font-size:11px;">Sampai Tanggal</small>
                                            <input type="date" id="filter-end" class="form-control form-control-lg border-0 bg-light shadow-sm rounded-3 fs-6">
                                        </div>
                                        <div class="d-flex align-items-end mt-2 mt-md-0 w-100 w-md-auto">
                                            <button class="btn btn-lg btn-pink shadow-sm rounded-3 px-4 w-100 fw-bold" onclick="renderAdminTable()"><i class="bi bi-search me-2"></i>Cari Data</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive bg-white rounded-4 shadow-sm border p-0">
                                <table class="table table-hover table-admin align-middle mb-0 table-riwayat">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="px-4 py-3 border-0">Tanggal & Status</th>
                                            <th class="text-center py-3 border-0">Jam Masuk</th>
                                            <th class="text-center py-3 border-0">Jam Pulang</th>
                                            <th class="text-end py-3 border-0 px-4">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="admin-detail-tbody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="screen-admin-lembur" class="app-screen">
                    <div class="bg-pink-wave header-top p-4 desktop-px position-relative shadow-sm text-center mb-4" style="border-radius: 0 0 25px 25px;">
                        <button class="btn btn-link text-white position-absolute top-50 translate-middle-y start-0 ms-md-4 ms-2" onclick="switchScreen('layanan')"><i class="bi bi-arrow-left fs-3"></i></button>
                        <h4 class="mb-0 text-white fw-bold mt-2 pb-2">Approval Lembur</h4>
                    </div>
                    <div class="p-3 desktop-px mx-auto" style="max-width: 1200px;">
                        <div class="table-responsive bg-white rounded-4 shadow-sm border p-0">
                            <table class="table table-hover table-admin align-middle mb-0 table-riwayat">
                                <thead class="table-light">
                                    <tr>
                                        <th class="px-4 py-3 border-0">Tanggal & Karyawan</th>
                                        <th class="text-center py-3 border-0">Jam Lembur</th>
                                        <th class="py-3 border-0">Keterangan</th>
                                        <th class="text-center py-3 border-0">Status</th>
                                        <th class="text-center py-3 border-0 px-4">Aksi HR</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($data_approval_lembur) > 0): foreach ($data_approval_lembur as $lmb): ?>
                                            <tr>
                                                <td class="px-4 py-3">
                                                    <span class="fw-bold d-block text-dark"><?= formatTanggalIndo($lmb['tanggal']) ?></span>
                                                    <small class="text-muted"><?= htmlspecialchars($lmb['nama']) ?></small>
                                                </td>
                                                <td class="text-center text-primary fw-bold"><?= date('H:i', strtotime($lmb['jam_mulai'])) ?> - <?= date('H:i', strtotime($lmb['jam_selesai'])) ?></td>
                                                <td><span class="d-inline-block text-truncate" style="max-width: 150px; font-size:12px;" title="<?= htmlspecialchars($lmb['keterangan']) ?>"><?= htmlspecialchars($lmb['keterangan']) ?></span></td>
                                                <td class="text-center">
                                                    <?php if ($lmb['status'] == 'Pending') echo '<span class="badge bg-warning text-dark border border-warning">Pending</span>';
                                                    elseif ($lmb['status'] == 'Disetujui') echo '<span class="badge bg-success">Disetujui</span>';
                                                    else echo '<span class="badge bg-danger">Ditolak</span>'; ?>
                                                </td>
                                                <td class="text-center px-4">
                                                    <?php if ($lmb['status'] == 'Pending'): ?>
                                                        <button class="btn btn-sm btn-success rounded-circle shadow-sm me-1" onclick="prosesApproval('<?= $lmb['id'] ?>', 'Disetujui', 'lembur')" title="Setujui"><i class="bi bi-check-lg"></i></button>
                                                        <button class="btn btn-sm btn-danger rounded-circle shadow-sm" onclick="prosesApproval('<?= $lmb['id'] ?>', 'Ditolak', 'lembur')" title="Tolak"><i class="bi bi-x-lg"></i></button>
                                                    <?php else: ?>
                                                        <span class="text-muted small"><i class="bi bi-check2-all"></i> Selesai</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach;
                                    else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-folder2-open fs-1 d-block mb-2 text-black-50"></i> Belum ada pengajuan lembur.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="screen-admin-cuti" class="app-screen">
                    <div class="bg-pink-wave header-top p-4 desktop-px position-relative shadow-sm text-center mb-4" style="border-radius: 0 0 25px 25px;">
                        <button class="btn btn-link text-white position-absolute top-50 translate-middle-y start-0 ms-md-4 ms-2" onclick="switchScreen('layanan')"><i class="bi bi-arrow-left fs-3"></i></button>
                        <h4 class="mb-0 text-white fw-bold mt-2 pb-2">Approval Libur & Cuti</h4>
                    </div>
                    <div class="p-3 desktop-px mx-auto" style="max-width: 1200px;">
                        <div class="table-responsive bg-white rounded-4 shadow-sm border p-0">
                            <table class="table table-hover table-admin align-middle mb-0 table-riwayat">
                                <thead class="table-light">
                                    <tr>
                                        <th class="px-4 py-3 border-0">Jenis & Karyawan</th>
                                        <th class="text-center py-3 border-0">Tanggal</th>
                                        <th class="py-3 border-0">Keterangan</th>
                                        <th class="text-center py-3 border-0">Status</th>
                                        <th class="text-center py-3 border-0 px-4">Aksi HR</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($data_approval_cuti) > 0): foreach ($data_approval_cuti as $cuti): ?>
                                            <tr>
                                                <td class="px-4 py-3">
                                                    <span class="fw-bold d-block text-dark"><?= htmlspecialchars($cuti['jenis']) ?></span>
                                                    <small class="text-muted"><?= htmlspecialchars($cuti['nama']) ?></small>
                                                </td>
                                                <td class="text-center text-info fw-bold">
                                                    <?= formatTanggalIndo($cuti['tanggal_mulai']) ?>
                                                    <?= $cuti['tanggal_mulai'] != $cuti['tanggal_selesai'] ? 's/d ' . formatTanggalIndo($cuti['tanggal_selesai']) : '' ?>
                                                </td>
                                                <td><span class="d-inline-block text-truncate" style="max-width: 150px; font-size:12px;" title="<?= htmlspecialchars($cuti['keterangan']) ?>"><?= htmlspecialchars($cuti['keterangan']) ?></span></td>
                                                <td class="text-center">
                                                    <?php if ($cuti['status'] == 'Pending') echo '<span class="badge bg-warning text-dark border border-warning">Pending</span>';
                                                    elseif ($cuti['status'] == 'Disetujui') echo '<span class="badge bg-success">Disetujui</span>';
                                                    else echo '<span class="badge bg-danger">Ditolak</span>'; ?>
                                                </td>
                                                <td class="text-center px-4">
                                                    <?php if ($cuti['status'] == 'Pending'): ?>
                                                        <button class="btn btn-sm btn-success rounded-circle shadow-sm me-1" onclick="prosesApproval('<?= $cuti['id'] ?>', 'Disetujui', 'cuti')" title="Setujui"><i class="bi bi-check-lg"></i></button>
                                                        <button class="btn btn-sm btn-danger rounded-circle shadow-sm" onclick="prosesApproval('<?= $cuti['id'] ?>', 'Ditolak', 'cuti')" title="Tolak"><i class="bi bi-x-lg"></i></button>
                                                    <?php else: ?>
                                                        <span class="text-muted small"><i class="bi bi-check2-all"></i> Selesai</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach;
                                    else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-folder2-open fs-1 d-block mb-2 text-black-50"></i> Belum ada pengajuan Libur/Cuti.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="screen-admin-dinas" class="app-screen">
                    <div class="bg-pink-wave header-top p-4 desktop-px position-relative shadow-sm text-center mb-4" style="border-radius: 0 0 25px 25px;">
                        <button class="btn btn-link text-white position-absolute top-50 translate-middle-y start-0 ms-md-4 ms-2" onclick="switchScreen('layanan')"><i class="bi bi-arrow-left fs-3"></i></button>
                        <h4 class="mb-0 text-white fw-bold mt-2 pb-2">Approval Dinas</h4>
                    </div>
                    <div class="p-3 desktop-px mx-auto" style="max-width: 1200px;">
                        <div class="table-responsive bg-white rounded-4 shadow-sm border p-0">
                            <table class="table table-hover table-admin align-middle mb-0 table-riwayat">
                                <thead class="table-light">
                                    <tr>
                                        <th class="px-4 py-3 border-0">Tujuan & Karyawan</th>
                                        <th class="text-center py-3 border-0">Tanggal</th>
                                        <th class="py-3 border-0">Keterangan</th>
                                        <th class="text-center py-3 border-0">Status</th>
                                        <th class="text-center py-3 border-0 px-4">Aksi HR</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($data_approval_dinas) > 0): foreach ($data_approval_dinas as $dns): ?>
                                            <tr>
                                                <td class="px-4 py-3">
                                                    <span class="fw-bold d-block text-dark"><?= htmlspecialchars($dns['tujuan']) ?></span>
                                                    <small class="text-muted"><?= htmlspecialchars($dns['nama']) ?></small>
                                                </td>
                                                <td class="text-center text-primary fw-bold">
                                                    <?= formatTanggalIndo($dns['tgl_berangkat']) ?> s/d <?= formatTanggalIndo($dns['tgl_kembali']) ?>
                                                </td>
                                                <td><span class="d-inline-block text-truncate" style="max-width: 150px; font-size:12px;" title="<?= htmlspecialchars($dns['keterangan']) ?>"><?= htmlspecialchars($dns['keterangan']) ?></span></td>
                                                <td class="text-center">
                                                    <?php if ($dns['status'] == 'Pending') echo '<span class="badge bg-warning text-dark border border-warning">Pending</span>';
                                                    elseif ($dns['status'] == 'Disetujui') echo '<span class="badge bg-success">Disetujui</span>';
                                                    else echo '<span class="badge bg-danger">Ditolak</span>'; ?>
                                                </td>
                                                <td class="text-center px-4">
                                                    <?php if ($dns['status'] == 'Pending'): ?>
                                                        <button class="btn btn-sm btn-success rounded-circle shadow-sm me-1" onclick="prosesApproval('<?= $dns['id'] ?>', 'Disetujui', 'dinas')" title="Setujui"><i class="bi bi-check-lg"></i></button>
                                                        <button class="btn btn-sm btn-danger rounded-circle shadow-sm" onclick="prosesApproval('<?= $dns['id'] ?>', 'Ditolak', 'dinas')" title="Tolak"><i class="bi bi-x-lg"></i></button>
                                                    <?php else: ?>
                                                        <span class="text-muted small"><i class="bi bi-check2-all"></i> Selesai</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; else: ?>
                                        <tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-folder2-open fs-1 d-block mb-2 text-black-50"></i> Belum ada pengajuan Dinas.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="screen-admin-reimburse" class="app-screen">
                    <div class="bg-pink-wave header-top p-4 desktop-px position-relative shadow-sm text-center mb-4" style="border-radius: 0 0 25px 25px;">
                        <button class="btn btn-link text-white position-absolute top-50 translate-middle-y start-0 ms-md-4 ms-2" onclick="switchScreen('layanan')"><i class="bi bi-arrow-left fs-3"></i></button>
                        <h4 class="mb-0 text-white fw-bold mt-2 pb-2">Approval Reimburse</h4>
                    </div>
                    <div class="p-3 desktop-px mx-auto" style="max-width: 1200px;">
                        <div class="table-responsive bg-white rounded-4 shadow-sm border p-0">
                            <table class="table table-hover table-admin align-middle mb-0 table-riwayat">
                                <thead class="table-light">
                                    <tr>
                                        <th class="px-4 py-3 border-0">Kategori & Karyawan</th>
                                        <th class="text-center py-3 border-0">Nominal (Rp)</th>
                                        <th class="py-3 border-0 text-center">Foto Nota</th>
                                        <th class="text-center py-3 border-0">Status</th>
                                        <th class="text-center py-3 border-0 px-4">Aksi HR</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($data_approval_reimburse) > 0): foreach ($data_approval_reimburse as $rmb): ?>
                                            <tr>
                                                <td class="px-4 py-3">
                                                    <span class="fw-bold d-block text-dark"><?= htmlspecialchars($rmb['kategori']) ?></span>
                                                    <small class="text-muted"><?= htmlspecialchars($rmb['nama']) ?></small>
                                                </td>
                                                <td class="text-center text-success fw-bold">Rp <?= number_format($rmb['nominal'], 0, ',', '.') ?></td>
                                                <td class="text-center">
                                                    <a href="<?= htmlspecialchars($rmb['foto_nota']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill shadow-sm"><i class="bi bi-image"></i> Lihat</a>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($rmb['status'] == 'Pending') echo '<span class="badge bg-warning text-dark border border-warning">Pending</span>';
                                                    elseif ($rmb['status'] == 'Disetujui') echo '<span class="badge bg-success">Disetujui</span>';
                                                    else echo '<span class="badge bg-danger">Ditolak</span>'; ?>
                                                </td>
                                                <td class="text-center px-4">
                                                    <?php if ($rmb['status'] == 'Pending'): ?>
                                                        <button class="btn btn-sm btn-success rounded-circle shadow-sm me-1" onclick="prosesApproval('<?= $rmb['id'] ?>', 'Disetujui', 'reimburse')" title="Setujui"><i class="bi bi-check-lg"></i></button>
                                                        <button class="btn btn-sm btn-danger rounded-circle shadow-sm" onclick="prosesApproval('<?= $rmb['id'] ?>', 'Ditolak', 'reimburse')" title="Tolak"><i class="bi bi-x-lg"></i></button>
                                                    <?php else: ?>
                                                        <span class="text-muted small"><i class="bi bi-check2-all"></i> Selesai</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; else: ?>
                                        <tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-folder2-open fs-1 d-block mb-2 text-black-50"></i> Belum ada pengajuan Reimburse.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div id="screen-profil" class="app-screen">
                <div class="bg-pink-wave header-top p-5 desktop-px text-center shadow-sm" style="border-radius: 0 0 25px 25px;">
                    <div class="avatar-initials mx-auto mt-2 mb-3 bg-white text-pink shadow-lg" style="width:110px; height:110px; font-size:42px; border:4px solid white;"><?= $inisial ?></div>
                    <h3 class="text-white fw-bold mb-1"><?= htmlspecialchars($karyawan['nama']) ?></h3>
                    <p class="text-white-50 mb-0 fs-5"><?= $karyawan['posisi'] ?></p>
                </div>
                <div class="p-3 desktop-px mx-auto" style="margin-top:-30px; max-width: 800px;">
                    <div class="card mb-4 border-0 shadow-sm rounded-4">
                        <div class="card-body p-0">
                            <div class="p-4 pb-0">
                                <h6 class="section-title mt-0 text-muted fw-bold"><i class="bi bi-person-lines-fill me-2"></i>Informasi Pribadi</h6>
                            </div>
                            <ul class="list-group list-group-flush rounded-4">
                                <li class="list-group-item d-flex justify-content-between p-4"><span class="text-muted">Nomor Induk Karyawan</span><span class="fw-bold text-dark"><?= $karyawan['nik'] ?></span></li>
                                <li class="list-group-item d-flex justify-content-between p-4"><span class="text-muted">Alamat Email</span><span class="fw-bold text-dark text-end"><?= $karyawan['email'] ?></span></li>
                                <li class="list-group-item d-flex justify-content-between p-4"><span class="text-muted">No. Handphone</span><span class="fw-bold text-dark"><?= $karyawan['no_hp'] ?></span></li>
                                <li class="list-group-item d-flex justify-content-between p-4"><span class="text-muted">Tanggal Bergabung</span><span class="fw-bold text-dark"><?= formatTanggal($karyawan['tgl_bergabung']) ?></span></li>
                            </ul>
                        </div>
                    </div>

                    <button class="btn btn-light bg-white border w-100 rounded-4 py-3 fw-bold mt-2 shadow-sm fs-5 text-dark" onclick="new bootstrap.Modal(document.getElementById('modalUbahPassword')).show()">
                        <i class="bi bi-key-fill me-2 text-warning"></i> Ubah Kata Sandi
                    </button>

                    <a href="/logout" class="btn btn-outline-danger bg-white w-100 rounded-4 py-3 fw-bold mt-3 shadow-sm fs-5">
                        <i class="bi bi-box-arrow-right me-2"></i> Keluar dari Aplikasi
                    </a>
                    <p class="text-center text-muted small mt-4">HRIS LBQueen v2.0 - 2026</p>
                </div>
            </div>

        </div>
    </div>

    <div class="modal fade" id="modalDetailAbsen" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
            <div class="modal-content rounded-4 border-0 shadow-lg">
                <div class="modal-header border-bottom-0 pb-0 px-4 pt-4">
                    <h5 class="modal-title fw-bold text-dark fs-5">Detail Kehadiran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 pt-2">
                    <p class="text-muted small mb-3 fs-6 fw-bold" id="mdl-header">Nama Karyawan • Tanggal</p>

                    <div class="rounded-3 p-3 mb-4 shadow-sm" id="mdl-status-box" style="background-color: #e8f5e9; border: 1px solid #c8e6c9;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-box-arrow-in-right text-success fs-4" id="mdl-status-icon"></i>
                                <span class="fw-bold fs-5 text-dark" id="mdl-status-text">Hadir</span>
                            </div>
                        </div>
                    </div>

                    <ul class="nav nav-pills custom-tabs mb-4 w-100 d-flex shadow-sm rounded-3 overflow-hidden" id="pills-tab" role="tablist">
                        <li class="nav-item flex-fill text-center"><button class="nav-link active w-100" data-bs-toggle="pill" data-bs-target="#pills-in" type="button"><i class="bi bi-box-arrow-in-right me-1"></i> Masuk</button></li>
                        <li class="nav-item flex-fill text-center"><button class="nav-link w-100" data-bs-toggle="pill" data-bs-target="#pills-out" type="button"><i class="bi bi-box-arrow-right me-1"></i> Pulang</button></li>
                        <li class="nav-item flex-fill text-center"><button class="nav-link w-100" data-bs-toggle="pill" data-bs-target="#pills-data" type="button"><i class="bi bi-person-fill me-1"></i> Profil</button></li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="pills-in">
                            <div class="detail-box p-4 shadow-sm border">
                                <h6 class="fw-bold small text-muted mb-3 text-uppercase tracking-wider"><i class="bi bi-clock me-2 text-dark"></i>Waktu Masuk</h6>
                                <div class="d-flex justify-content-between text-success fw-bold fs-5"><span class="text-muted fs-6 fw-normal">Pukul</span><span id="mdl-in-time">00.00</span></div>
                            </div>
                            <div class="detail-box p-4 shadow-sm border">
                                <h6 class="fw-bold small text-muted mb-3 text-uppercase tracking-wider"><i class="bi bi-geo-alt me-2 text-dark"></i>Lokasi Absen</h6>
                                <p class="mb-3 fs-6 fw-bold text-dark" id="mdl-in-lokasi">-</p>
                                <hr class="text-muted opacity-25" style="border-style: dashed;">
                                <div class="d-flex justify-content-between small text-success mb-2"><span>IP Address</span><span class="text-dark fw-bold" id="mdl-in-ip">Memuat...</span></div>
                                <div class="d-flex justify-content-between small text-success"><span>Koordinat</span><span class="text-dark fw-bold" id="mdl-in-coord">Memuat...</span></div>
                            </div>
                            <div class="detail-box p-4 shadow-sm border">
                                <h6 class="fw-bold small text-muted mb-3 text-uppercase tracking-wider"><i class="bi bi-camera me-2 text-dark"></i>Foto Bukti</h6>
                                <div class="camera-box bg-light border" style="aspect-ratio: auto; min-height:300px;"><img id="mdl-in-foto" src="" onerror="this.src='https://placehold.co/400x500?text=Tidak+Ada+Foto'"></div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="pills-out">
                            <div class="detail-box p-4 shadow-sm border">
                                <h6 class="fw-bold small text-muted mb-3 text-uppercase tracking-wider"><i class="bi bi-clock me-2 text-dark"></i>Waktu Pulang</h6>
                                <div class="d-flex justify-content-between text-danger fw-bold fs-5"><span class="text-muted fs-6 fw-normal">Pukul</span><span id="mdl-out-time">00.00</span></div>
                            </div>
                            <div class="detail-box p-4 shadow-sm border">
                                <h6 class="fw-bold small text-muted mb-3 text-uppercase tracking-wider"><i class="bi bi-geo-alt me-2 text-dark"></i>Lokasi Absen</h6>
                                <p class="mb-3 fs-6 fw-bold text-dark" id="mdl-out-lokasi">-</p>
                                <hr class="text-muted opacity-25" style="border-style: dashed;">
                                <div class="d-flex justify-content-between small text-danger mb-2"><span>IP Address</span><span class="text-dark fw-bold" id="mdl-out-ip">Memuat...</span></div>
                                <div class="d-flex justify-content-between small text-danger"><span>Koordinat</span><span class="text-dark fw-bold" id="mdl-out-coord">Memuat...</span></div>
                            </div>
                            <div class="detail-box p-4 shadow-sm border">
                                <h6 class="fw-bold small text-muted mb-3 text-uppercase tracking-wider"><i class="bi bi-camera me-2 text-dark"></i>Foto Bukti</h6>
                                <div class="camera-box bg-light border" style="aspect-ratio: auto; min-height:300px;"><img id="mdl-out-foto" src="" onerror="this.src='https://placehold.co/400x500?text=Tidak+Ada+Foto'"></div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="pills-data">
                            <div class="detail-box p-5 shadow-sm border text-center">
                                <div class="avatar-initials mx-auto mb-3 shadow-sm" style="width: 80px; height: 80px; font-size: 30px;"><?= $inisial ?></div>
                                <h5 class="fw-bold text-dark mb-1"><?= $karyawan['nama'] ?></h5>
                                <p class="text-muted mb-4"><?= $karyawan['posisi'] ?></p>
                                <div class="text-start bg-light p-4 rounded-4 border">
                                    <p class="mb-2 fs-6"><span class="text-muted fw-bold">NIK</span> <strong class="float-end text-dark"><?= $karyawan['nik'] ?></strong></p>
                                    <p class="mb-2 fs-6"><span class="text-muted fw-bold">Status</span> <strong class="float-end text-dark"><?= $karyawan['status_pegawai'] ?></strong></p>
                                    <p class="mb-0 fs-6"><span class="text-muted fw-bold">Penempatan</span> <strong class="float-end text-dark"><?= $karyawan['penempatan'] ?></strong></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalCuti" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow-lg">
                <div class="modal-header border-bottom-0 pb-0 p-4">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-calendar-event-fill text-info me-2"></i>Form Libur & Cuti</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 pt-2">
                    <p class="text-muted small mb-4">Ajukan jadwal libur mingguan (Selain Sabtu/Minggu) atau cuti tahunan.</p>
                    <form id="formCuti" onsubmit="submitPengajuan(event, 'cuti')">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark">Jenis Pengajuan</label>
                            <select class="form-select form-select-lg bg-light fs-6" name="kategori_cuti" id="kategori_cuti" required onchange="validasiHariLibur()">
                                <option value="Libur Mingguan">Libur Mingguan (1 Hari)</option>
                                <option value="Cuti Tahunan">Cuti Tahunan</option>
                                <option value="Sakit">Izin Sakit</option>
                            </select>
                        </div>
                        <div class="row mb-3 g-3">
                            <div class="col-6"><label class="form-label small fw-bold text-dark">Tgl Mulai</label><input type="date" class="form-control form-control-lg bg-light fs-6" name="tgl_mulai" id="tgl_mulai_cuti" required onchange="validasiHariLibur()"></div>
                            <div class="col-6"><label class="form-label small fw-bold text-dark">Tgl Selesai</label><input type="date" class="form-control form-control-lg bg-light fs-6" name="tgl_selesai" id="tgl_selesai_cuti" required></div>
                        </div>
                        <div class="mb-4"><label class="form-label small fw-bold text-dark">Alasan / Keterangan</label><textarea class="form-control form-control-lg bg-light fs-6" name="keterangan" rows="2" placeholder="Tuliskan keterangan..." required></textarea></div>
                        <button type="submit" class="btn btn-info w-100 py-3 fw-bold rounded-pill shadow-sm text-white">Kirim Pengajuan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalLembur" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow-lg">
                <div class="modal-header border-bottom-0 pb-0 p-4">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-clock-fill text-warning me-2"></i>Form Rencana Lembur</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 pt-2">
                    <form id="formLembur" onsubmit="submitPengajuan(event, 'lembur')">
                        <div class="mb-3"><label class="form-label small fw-bold text-dark">Tanggal Lembur</label><input type="date" class="form-control form-control-lg bg-light fs-6" name="tanggal" required></div>
                        <div class="row mb-3 g-3">
                            <div class="col-6"><label class="form-label small fw-bold text-dark">Jam Mulai</label><input type="time" class="form-control form-control-lg bg-light fs-6" name="jam_mulai" required></div>
                            <div class="col-6"><label class="form-label small fw-bold text-dark">Jam Selesai</label><input type="time" class="form-control form-control-lg bg-light fs-6" name="jam_selesai" required></div>
                        </div>
                        <div class="mb-4"><label class="form-label small fw-bold text-dark">Keterangan / Tugas Lembur</label><textarea class="form-control form-control-lg bg-light fs-6" name="keterangan" rows="2" required></textarea></div>
                        <button type="submit" class="btn btn-warning text-dark w-100 py-3 fw-bold rounded-pill shadow-sm">Kirim Rencana Lembur</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalDinas" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow-lg">
                <div class="modal-header border-bottom-0 pb-0 p-4">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-car-front-fill text-primary me-2"></i>Form Perjalanan Dinas</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 pt-2">
                    <form id="formDinas" onsubmit="submitPengajuan(event, 'dinas')">
                        <div class="mb-3"><label class="form-label small fw-bold text-dark">Tujuan / Kota Penugasan</label><input type="text" class="form-control form-control-lg bg-light fs-6" name="tujuan" required></div>
                        <div class="row mb-3 g-3">
                            <div class="col-6"><label class="form-label small fw-bold text-dark">Tgl Berangkat</label><input type="date" class="form-control form-control-lg bg-light fs-6" name="tgl_berangkat" required></div>
                            <div class="col-6"><label class="form-label small fw-bold text-dark">Tgl Kembali</label><input type="date" class="form-control form-control-lg bg-light fs-6" name="tgl_kembali" required></div>
                        </div>
                        <div class="mb-4"><label class="form-label small fw-bold text-dark">Keterangan</label><textarea class="form-control form-control-lg bg-light fs-6" name="keterangan" rows="2" required></textarea></div>
                        <button type="submit" class="btn btn-primary w-100 py-3 fw-bold rounded-pill shadow-sm">Kirim Pengajuan Dinas</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalReimburse" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow-lg">
                <div class="modal-header border-bottom-0 pb-0 p-4">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-receipt-cutoff text-success me-2"></i>Form Reimbursement</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 pt-2">
                    <form id="formReimburse" onsubmit="submitPengajuan(event, 'reimburse')">
                        <div class="mb-3"><label class="form-label small fw-bold text-dark">Kategori Pengeluaran</label><select class="form-select form-select-lg bg-light fs-6" name="kategori" required>
                                <option value="Transportasi">Transportasi / Bensin</option>
                                <option value="Konsumsi">Konsumsi / Makan</option>
                                <option value="Akomodasi">Akomodasi / Penginapan</option>
                                <option value="Operasional Lainnya">Operasional Lainnya</option>
                            </select></div>
                        <div class="mb-3"><label class="form-label small fw-bold text-dark">Nominal Klaim (Rp)</label><input type="number" class="form-control form-control-lg bg-light fs-6" name="nominal" required></div>
                        <div class="mb-3"><label class="form-label small fw-bold text-dark">Foto Bukti Nota</label><input type="file" class="form-control form-control-lg bg-light fs-6" id="inputFotoReimburse" accept="image/*" required><input type="hidden" name="foto_nota" id="base64Reimburse"></div>
                        <div class="mb-4"><label class="form-label small fw-bold text-dark">Keterangan</label><textarea class="form-control form-control-lg bg-light fs-6" name="keterangan" rows="2" required></textarea></div>
                        <button type="submit" class="btn btn-success w-100 py-3 fw-bold rounded-pill shadow-sm">Kirim Klaim Dana</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if ($is_admin): ?>
        <div class="modal fade" id="modalPayroll" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow-lg">
                    <div class="modal-header border-bottom-0 pb-0 p-4">
                        <h5 class="modal-title fw-bold text-dark"><i class="bi bi-cash-coin text-success me-2"></i>Generate Slip Gaji</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4 pt-2">
                        <form action="/cetak_slip" method="GET" target="_blank">
                            <div class="mb-3"><label class="form-label small fw-bold text-dark">Pilih Karyawan</label><select name="nik" class="form-select form-select-lg bg-light fs-6" required><?php foreach ($semua_karyawan as $kar): ?><option value="<?= $kar['nik'] ?>"><?= $kar['nama'] ?> (<?= $kar['posisi'] ?>)</option><?php endforeach; ?></select></div>
                            <div class="row mb-3 g-3">
                                <div class="col-6"><label class="form-label small fw-bold text-dark">Bulan</label><select name="bulan" class="form-select form-select-lg bg-light fs-6"><?php for ($i = 1; $i <= 12; $i++): ?><option value="<?= str_pad($i, 2, '0', STR_PAD_LEFT) ?>" <?= date('m') == $i ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $i, 1)) ?></option><?php endfor; ?></select></div>
                                <div class="col-6"><label class="form-label small fw-bold text-dark">Tahun</label><input type="number" class="form-control form-control-lg bg-light fs-6" name="tahun" value="<?= date('Y') ?>" required></div>
                            </div>
                            <div class="row mb-3 g-3">
                                <div class="col-6"><label class="form-label small fw-bold text-dark">Total Hari Kerja</label><input type="number" class="form-control form-control-lg bg-light fs-6" name="hari_kerja" value="26" required></div>
                                <div class="col-6"><label class="form-label small fw-bold text-dark">Uang Makan</label><input type="number" class="form-control form-control-lg bg-light fs-6" name="uang_makan" value="200000" required></div>
                            </div>
                            <div class="mb-4 bg-light p-3 rounded-3 border">
                                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="capai_target" value="1" id="c_target"><label class="form-check-label small fw-bold text-dark" for="c_target">Capai Target Omset (Bonus 600rb)</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="alpa_banyak" value="1" id="c_alpa"><label class="form-check-label small fw-bold text-danger" for="c_alpa">Alpa >= 2x (Kerajinan Hangus)</label></div>
                            </div>
                            <button type="submit" class="btn btn-success w-100 py-3 fw-bold rounded-pill shadow-sm">Cetak Slip Gaji PDF</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="modalCetakLaporan" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content rounded-4 border-0 shadow-lg">
                    <div class="modal-body p-4 text-center"><i class="bi bi-printer text-pink" style="font-size: 3rem;"></i>
                        <h5 class="fw-bold mt-3 mb-1 text-dark">Cetak Rekap Tim</h5>
                        <form action="/cetak_rekap" method="GET" target="_blank"><select name="bulan" class="form-select form-select-lg bg-light mb-3 fs-6 mt-3"><?php for ($i = 1; $i <= 12; $i++): ?><option value="<?= str_pad($i, 2, '0', STR_PAD_LEFT) ?>" <?= date('m') == $i ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $i, 1)) ?></option><?php endfor; ?></select><select name="tahun" class="form-select form-select-lg bg-light mb-4 fs-6"><?php for ($i = date('Y') - 1; $i <= date('Y') + 1; $i++): ?><option value="<?= $i ?>" <?= date('Y') == $i ? 'selected' : '' ?>><?= $i ?></option><?php endfor; ?></select><button type="submit" class="btn btn-pink w-100 rounded-pill py-3 fw-bold shadow-sm">Buka Laporan PDF</button></form>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" id="modalCetakLogKaryawan" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content rounded-4 border-0 shadow-lg">
                    <div class="modal-body p-4 text-center"><i class="bi bi-file-earmark-pdf-fill text-danger" style="font-size: 3rem;"></i>
                        <h5 class="fw-bold mt-3 mb-1 text-dark">Cetak Laporan Pribadi</h5>
                        <p class="text-muted small mb-4">Siklus: Tgl 26 s/d Tgl 25</p>
                        <form action="/cetak_pribadi" method="GET" target="_blank"><input type="hidden" name="nik" id="cetak_nik_karyawan"><select name="bulan" class="form-select form-select-lg bg-light mb-3 fs-6"><?php for ($i = 1; $i <= 12; $i++): ?><option value="<?= str_pad($i, 2, '0', STR_PAD_LEFT) ?>" <?= date('m') == $i ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $i, 1)) ?></option><?php endfor; ?></select><select name="tahun" class="form-select form-select-lg bg-light mb-4 fs-6"><?php for ($i = date('Y') - 1; $i <= date('Y') + 1; $i++): ?><option value="<?= $i ?>" <?= date('Y') == $i ? 'selected' : '' ?>><?= $i ?></option><?php endfor; ?></select><button type="submit" class="btn btn-danger w-100 rounded-pill py-3 fw-bold shadow-sm"><i class="bi bi-printer-fill me-2"></i> Cetak Laporan PDF</button></form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="modal fade" id="modalUbahPassword" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow-lg">
                <div class="modal-header border-bottom-0 pb-0 p-4">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-key-fill text-warning me-2"></i>Ubah Kata Sandi</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 pt-2">
                    <form id="formUbahPassword" onsubmit="submitUbahPassword(event)">
                        <div class="mb-3"><label class="form-label small fw-bold text-dark">Kata Sandi Lama</label><input type="password" class="form-control form-control-lg bg-light fs-6" name="password_lama" required></div>
                        <div class="mb-3"><label class="form-label small fw-bold text-dark">Kata Sandi Baru</label><input type="password" class="form-control form-control-lg bg-light fs-6" id="password_baru" name="password_baru" required minlength="6" oninput="checkStrength(this.value); checkMatch()"></div>
                        <div class="strength-bar-wrap d-flex gap-1 mb-2" id="strength-bars">
                            <div class="strength-bar flex-fill rounded" id="bar1" style="height:4px; background:#eee;"></div>
                            <div class="strength-bar flex-fill rounded" id="bar2" style="height:4px; background:#eee;"></div>
                            <div class="strength-bar flex-fill rounded" id="bar3" style="height:4px; background:#eee;"></div>
                            <div class="strength-bar flex-fill rounded" id="bar4" style="height:4px; background:#eee;"></div>
                        </div>
                        <div class="strength-label small fw-bold mb-3" id="strength-label"></div>
                        <div class="mb-4"><label class="form-label small fw-bold text-dark">Konfirmasi Kata Sandi Baru</label><input type="password" class="form-control form-control-lg bg-light fs-6" id="password_konfirmasi" name="konfirmasi_password" required minlength="6" oninput="checkMatch()">
                            <div id="match-msg" style="font-size:0.78rem; margin-top:5px; font-weight:600;"></div>
                        </div><button type="submit" class="btn btn-pink w-100 py-3 fw-bold rounded-pill shadow-sm">Simpan Kata Sandi Baru</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modernAlertModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content rounded-4 border-0 shadow-lg">
                <div class="modal-body text-center p-4 pt-5">
                    <div id="modernAlertIcon" class="mb-3"><i class="bi bi-info-circle-fill" style="font-size:3.5rem; color:var(--lb-pink);"></i></div>
                    <h5 class="fw-bold mb-3 text-dark" id="modernAlertTitle">Pemberitahuan</h5>
                    <p class="text-muted mb-4" id="modernAlertMessage" style="font-size:14px;"></p><button type="button" class="btn btn-pink w-100 rounded-pill py-3 fw-bold shadow-sm" data-bs-dismiss="modal">Saya Mengerti</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.HRIS_CONFIG = {
            userNIK: '<?= $karyawan['nik'] ?>',
            karyawanPenempatan: <?= json_encode($karyawan['penempatan']) ?>,
            adminHistData: <?= json_encode($admin_hist_arr) ?>
        };

        // JS Validasi Khusus Hari Libur Mingguan (Tidak Boleh Sabtu/Minggu)
        function validasiHariLibur() {
            const jenis = document.getElementById('kategori_cuti').value;
            const inputMulai = document.getElementById('tgl_mulai_cuti');
            const inputSelesai = document.getElementById('tgl_selesai_cuti');

            if (jenis === 'Libur Mingguan') {
                inputSelesai.value = inputMulai.value;
                inputSelesai.readOnly = true;

                if (inputMulai.value) {
                    const date = new Date(inputMulai.value);
                    if (date.getDay() === 0 || date.getDay() === 6) {
                        showModernAlert('Gagal', 'Sesuai aturan, Libur Mingguan tidak boleh diambil pada hari Sabtu atau Minggu (Wajib Masuk).', 'bi bi-x-circle-fill', '#dc3545');
                        inputMulai.value = '';
                        inputSelesai.value = '';
                    }
                }
            } else {
                inputSelesai.readOnly = false;
            }
        }

    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/style/script.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            if (typeof window.switchScreen === 'function') {
                const originalSwitchScreen = window.switchScreen;
                window.switchScreen = function(screenId) {
                    document.querySelectorAll('.app-screen').forEach(function(screen) {
                        screen.classList.remove('active');
                        screen.style.display = 'none';
                    });
                    try {
                        originalSwitchScreen(screenId);
                    } catch (e) {}
                    const activeScreen = document.getElementById('screen-' + screenId);
                    if (activeScreen) {
                        activeScreen.classList.add('active');
                        activeScreen.style.display = 'block';
                    }
                };
            }
        });
    </script>
</body>

</html>