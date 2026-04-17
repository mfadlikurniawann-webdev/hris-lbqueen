<?php
// api/index.php
include __DIR__ . '/koneksi.php';
date_default_timezone_set('Asia/Jakarta');

// PROTEKSI HALAMAN - Pakai JWT
$karyawan = auth_required($conn);

// HAK AKSES ADMIN/HR
$posisi   = strtoupper($karyawan['posisi']);
$level    = strtoupper($karyawan['level_jabatan']);
$is_admin = in_array($posisi, ['HCG','HRD','HR']) || in_array($level, ['OWNER','DIREKTUR']);

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
$batas_check_in_akhir = '13:00'; 
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

// PENENTUAN KAMERA AKTIF ATAU TIDAK
$show_camera = false;
if (!$sudah_in && !$belum_waktunya_in && !$lewat_batas_in) {
    $show_camera = true; 
} elseif ($sudah_in && !$sudah_out && !$belum_waktunya_out) {
    $show_camera = true; 
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
    <title>HRIS - <?= $karyawan['nama'] ?></title>
    
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#C94F78">
    <link rel="icon" type="image/png" href="/logo/lbqueen_logo.PNG">
    <link rel="apple-touch-icon" href="/logo/lbqueen_logo.PNG">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="HRIS LBQueen">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    
    <style>
        /* BASE STYLE */
        :root { --lb-pink: #C94F78; --lb-pink-hover: #A83E60; }
        body { background-color: #f4f6f9; font-family: 'DM Sans', sans-serif; margin: 0; padding: 0; }
        
        .modal-content { border-radius: 20px; border: none; }
        .nav-pills .nav-link { color: #6c757d; border-radius: 10px; font-weight: bold; }
        .nav-pills .nav-link.active { background-color: var(--lb-pink); color: white; }
        .detail-box { border: 1px solid #eee; border-radius: 12px; padding: 15px; margin-bottom: 15px; }
        .status-box { border-radius: 15px; padding: 15px; display: flex; align-items: center; justify-content: space-between; }
        .status-box.terlambat { background-color: #fff4e5; border: 1px solid #ffe0b2; color: #e65100; }
        .status-box.hadir { background-color: #e8f5e9; border: 1px solid #c8e6c9; color: #2e7d32; }
        .btn-pink { background-color: var(--lb-pink); color: white; transition: 0.3s; }
        .btn-pink:hover { background-color: var(--lb-pink-hover); color: white; }
        .activity-box { background: #fff; border: 1px solid #eaeaea; border-radius: 15px; padding: 15px; height: 100%; display: flex; flex-direction: column; justify-content: space-between; }
        .table-admin td { vertical-align: middle; }
        .employee-card { transition: transform 0.2s; border: 1px solid #f0f0f0; cursor: pointer; }
        .employee-card:active { transform: scale(0.98); background-color: #f8f9fa; }
        .bg-pink { background-color: var(--lb-pink); }
        .text-pink { color: var(--lb-pink); }
        
        /* ========================================= */
        /* RESPONSIVE LAYOUT (MOBILE VS DESKTOP)     */
        /* ========================================= */
        .app-wrapper {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            padding-bottom: 75px; /* Ruang untuk bottom nav di mobile */
            width: 100%;
        }

        /* BOTTOM NAV (MOBILE DEFAULT) */
        .sidebar-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background-color: #fff;
            display: flex;
            justify-content: space-around;
            padding: 10px 0;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
            z-index: 999;
        }

        .sidebar-logo { display: none; }

        .nav-item-btn {
            background: transparent;
            border: none;
            color: #6c757d;
            display: flex;
            flex-direction: column;
            align-items: center;
            font-size: 12px;
            font-weight: 500;
            width: 100%;
            outline: none;
        }
        .nav-item-btn i { font-size: 22px; margin-bottom: 3px; }
        .nav-item-btn.active { color: var(--lb-pink); }
        .nav-item-btn.active i { color: var(--lb-pink); }

        .app-screen { display: none; }
        .app-screen.active { display: block; }

        /* SIDEBAR (TABLET & DESKTOP) */
        @media (min-width: 992px) {
            .app-wrapper {
                flex-direction: row;
            }
            .sidebar-nav {
                position: fixed;
                top: 0;
                left: 0;
                width: 250px;
                height: 100vh;
                flex-direction: column;
                justify-content: flex-start;
                padding: 30px 0;
                border-right: 1px solid #e0e0e0;
                box-shadow: 2px 0 15px rgba(0,0,0,0.03);
            }
            .sidebar-logo {
                display: block;
                text-align: center;
                margin-bottom: 40px;
                padding: 0 20px;
            }
            .nav-item-btn {
                flex-direction: row;
                padding: 15px 30px;
                font-size: 15px;
                justify-content: flex-start;
                gap: 15px;
                transition: all 0.2s;
                border-right: 4px solid transparent;
            }
            .nav-item-btn i { font-size: 20px; margin-bottom: 0; }
            .nav-item-btn:hover { background-color: #fdf0f5; color: var(--lb-pink); }
            .nav-item-btn.active { background-color: #fdf0f5; border-right-color: var(--lb-pink); }

            .main-content {
                margin-left: 250px; /* Geser konten ke kanan sebesar lebar sidebar */
                padding: 30px;
                padding-bottom: 30px;
                display: flex;
                justify-content: center;
            }

            .app-screen.active {
                width: 100%;
                max-width: 1000px; /* Agar konten tidak melar terlalu lebar */
                background: #fff;
                border-radius: 20px;
                box-shadow: 0 5px 20px rgba(0,0,0,0.03);
                overflow: hidden;
                min-height: calc(100vh - 60px);
            }
        }
    </style>
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
            <div class="bg-pink p-4 rounded-bottom-4 shadow-sm" style="border-radius: 0 0 20px 20px;">
                <div class="d-flex align-items-center justify-content-center mb-3 pb-3 border-bottom border-light border-opacity-25">
                    <img src="/logo/lbqueen_logo.PNG" alt="Logo LBQueen" onerror="this.style.display='none'" style="height:40px; margin-right:12px; background:white; border-radius:8px; padding:4px;">
                    <h5 class="mb-0 fw-bold text-white" style="font-size:16px;">HRIS LBQueen Care Beauty</h5>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="mb-0 text-white-50" id="date-display" style="font-size:13px;">Memuat tanggal...</p>
                        <h4 class="mb-0 fw-bold mt-1 text-white">Hai, <?= htmlspecialchars($karyawan['nama']) ?>!</h4>
                    </div>
                    <i class="bi bi-bell fs-4 text-white"></i>
                </div>
            </div>

            <div class="p-3">
                <div class="card border-0 shadow-sm rounded-4 mb-4" style="margin-top:-25px; z-index: 10; position:relative;">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="avatar-initials bg-pink text-white d-flex align-items-center justify-content-center rounded-circle fw-bold" style="width: 50px; height: 50px; font-size: 20px;">
                            <?= $inisial ?>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1"><?= $karyawan['posisi'] ?></h6>
                            <span class="badge bg-warning text-dark"><?= $karyawan['status_pegawai'] ?></span>
                            <span class="badge bg-secondary"><?= $karyawan['penempatan'] ?></span>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-2">
                    <h1 class="display-3 fw-bold text-pink" id="clock-display">00:00:00</h1>
                    <div class="alert alert-success d-inline-flex align-items-center gap-2 rounded-pill px-4 py-2 mt-2 mb-3">
                        <i class="bi bi-geo-alt-fill"></i> Lokasi Kerja: <?= $karyawan['penempatan'] ?>
                    </div>

                    <?php if ($show_camera): ?>
                        <div id="camera-wrapper" class="mb-3 mx-auto shadow-sm" style="width:200px; height:200px; border-radius:50%; overflow:hidden; border:4px solid #fdf0f5; background:#eee;">
                            <video id="kamera" autoplay playsinline style="width:100%; height:100%; object-fit:cover; transform:scaleX(-1);"></video>
                            <canvas id="canvas_kamera" style="display:none;"></canvas>
                        </div>
                        <small class="text-muted d-block mb-3">Pastikan wajah terlihat jelas sebelum absen.</small>
                    <?php else: ?>
                        <div class="alert alert-secondary text-center p-4 rounded-4 mb-4 mx-auto shadow-sm" style="max-width: 300px;">
                            <i class="bi bi-camera-video-off fs-1 text-muted mb-2 d-block"></i>
                            <?php if (!$sudah_in && $belum_waktunya_in): ?>
                                <h6 class="fw-bold mb-1">Kamera Belum Aktif</h6>
                                <p class="mb-0 small text-muted">Check In baru bisa dilakukan mulai pukul 08:30 WIB.</p>
                            <?php elseif (!$sudah_in && $lewat_batas_in): ?>
                                <h6 class="fw-bold mb-1 text-danger">Batas Waktu Habis</h6>
                                <p class="mb-0 small text-danger">Batas Check In (13:00 WIB) telah terlewat.</p>
                            <?php elseif ($sudah_in && !$sudah_out && $belum_waktunya_out): ?>
                                <h6 class="fw-bold mb-1">Belum Waktu Pulang</h6>
                                <p class="mb-0 small text-muted">Check Out baru bisa dilakukan mulai pukul 18:00 WIB.</p>
                            <?php elseif ($sudah_out): ?>
                                <h6 class="fw-bold mb-1 text-success">Absensi Selesai</h6>
                                <p class="mb-0 small text-muted">Terima kasih, selamat beristirahat!</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div id="absen-response" class="mb-3 fw-bold text-primary"></div>

                    <div class="d-flex justify-content-center gap-3 mb-4 mx-auto" style="max-width: 400px;">
                        <button class="btn <?= (!$sudah_in && !$belum_waktunya_in && !$lewat_batas_in) ? 'btn-success' : 'btn-secondary' ?> flex-fill rounded-4 py-3 fw-bold shadow-sm"
                                <?= (!$sudah_in && !$belum_waktunya_in && !$lewat_batas_in) ? "onclick=\"kirimAbsen('Check In')\"" : 'disabled' ?>>
                            <i class="bi bi-box-arrow-in-right me-1"></i> Check In
                        </button>
                        <button class="btn <?= ($sudah_in && !$sudah_out && !$belum_waktunya_out) ? 'btn-outline-danger' : 'btn-secondary' ?> flex-fill rounded-4 py-3 fw-bold shadow-sm"
                                <?= ($sudah_in && !$sudah_out && !$belum_waktunya_out) ? "onclick=\"kirimAbsen('Check Out')\"" : 'disabled' ?>>
                            <i class="bi bi-box-arrow-right me-1"></i> Check Out
                        </button>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
                    <h6 class="section-title mb-0 mt-0">Aktivitas Hari Ini</h6>
                    <button class="btn btn-sm btn-link text-pink text-decoration-none fw-bold p-0" onclick="switchScreen('riwayat')">Lihat Riwayat</button>
                </div>

                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center mb-3 border-bottom pb-3">
                            <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-3" style="width:40px; height:40px;">
                                <i class="bi bi-clock-history text-pink fs-5"></i>
                            </div>
                            <div>
                                <small class="text-muted fw-bold d-block" style="font-size:11px; letter-spacing:0.5px;">JADWAL KERJA KAMU</small>
                                <span class="fw-bold fs-6">09:00 - 19:00 <small class="text-muted">WIB</small></span>
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="activity-box">
                                    <div class="d-flex align-items-start mb-3">
                                        <i class="bi bi-check-circle-fill fs-5 me-2 <?= $sudah_in ? 'text-success' : 'text-secondary opacity-50' ?>"></i>
                                        <div>
                                            <small class="fw-bold d-block text-dark lh-1 mb-1">Status</small>
                                            <small class="text-muted" style="font-size:11px;"><?= $sudah_in ? 'Sudah Check In' : 'Belum Check In' ?></small>
                                        </div>
                                    </div>
                                    <h4 class="fw-bold mb-1 <?= $sudah_in ? 'text-dark' : 'text-muted' ?>"><?= $waktu_in ?> <small class="text-muted" style="font-size:12px;">WIB</small></h4>
                                    <div class="d-flex justify-content-between align-items-end mt-2">
                                        <small class="text-success fw-bold">Masuk</small>
                                        <?php if ($sudah_in):
                                            $b = 'bg-success';
                                            if ($status_in=='Telat') $b='bg-warning text-dark';
                                            if ($status_in=='Tidak Hadir') $b='bg-danger';
                                        ?>
                                            <span class="badge <?= $b ?> rounded-pill" style="font-size:10px; padding:4px 8px;"><?= $status_in ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="activity-box">
                                    <div class="d-flex align-items-start mb-3">
                                        <i class="bi bi-check-circle-fill fs-5 me-2 <?= $sudah_out ? 'text-success' : 'text-secondary opacity-50' ?>"></i>
                                        <div>
                                            <small class="fw-bold d-block text-dark lh-1 mb-1">Status</small>
                                            <small class="text-muted" style="font-size:11px;"><?= $sudah_out ? 'Sudah Check Out' : 'Belum Check Out' ?></small>
                                        </div>
                                    </div>
                                    <h4 class="fw-bold mb-1 <?= $sudah_out ? 'text-dark' : 'text-muted' ?>"><?= $waktu_out ?> <small class="text-muted" style="font-size:12px;">WIB</small></h4>
                                    <div class="d-flex justify-content-between align-items-end mt-2">
                                        <small class="text-secondary fw-bold">Pulang</small>
                                        <?php if ($sudah_out): ?>
                                            <span class="badge bg-info text-white rounded-pill" style="font-size:10px; padding:4px 8px;">Selesai</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="screen-riwayat" class="app-screen">
            <div class="bg-pink p-3 position-relative shadow-sm text-center mb-3" style="border-radius: 0 0 20px 20px;">
                <h5 class="mb-0 text-white fw-bold mt-2 pb-2">Riwayat Kehadiran</h5>
            </div>
            <div class="p-3 pt-0">
                <?php if ($full_history->num_rows > 0): ?>
                    <?php while ($data = $full_history->fetch_assoc()):
                        $durasi_teks = '-';
                        if ($data['in_time'] && $data['out_time']) {
                            $diff = strtotime($data['out_time']) - strtotime($data['in_time']);
                            $durasi_teks = floor($diff/3600).' jam '.floor(($diff%3600)/60).' menit';
                        }
                        $waktu_in_view  = $data['in_time']  ? date('H.i', strtotime($data['in_time']))  : '-';
                        $waktu_out_view = $data['out_time'] ? date('H.i', strtotime($data['out_time'])) : '-';
                        $status_in = $data['status_in'] ?: 'Tidak Hadir';
                        $badge_bg  = 'bg-success';
                        if ($status_in=='Telat') $badge_bg='bg-warning text-dark';
                        if ($status_in=='Tidak Hadir') $badge_bg='bg-danger';
                        $modalData = htmlspecialchars(json_encode([
                            'tanggal'    => formatTanggalIndo($data['tgl']),
                            'nama'       => $karyawan['nama'],
                            'status'     => $status_in,
                            'durasi'     => $durasi_teks,
                            'in_time'    => $data['in_time']  ? date('H:i', strtotime($data['in_time']))  : '-',
                            'out_time'   => $data['out_time'] ? date('H:i', strtotime($data['out_time'])) : '-',
                            'in_lokasi'  => $data['lok_in']  ?: 'Tidak ada data lokasi',
                            'out_lokasi' => $data['lok_out'] ?: 'Tidak ada data lokasi',
                            'in_foto'    => $data['foto_in']  ?: '',
                            'out_foto'   => $data['foto_out'] ?: ''
                        ]));
                    ?>
                    <div class="card border-0 shadow-sm rounded-4 mb-3" onclick="bukaDetail(<?= $modalData ?>)" style="cursor:pointer;">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-2 border-bottom pb-2">
                                <span class="fw-bold"><i class="bi bi-calendar-event me-2 text-pink"></i><?= formatTanggalIndo($data['tgl']) ?></span>
                                <span class="badge <?= $badge_bg ?> rounded-pill px-3"><?= $status_in ?></span>
                            </div>
                            <div class="row text-center mt-2">
                                <div class="col-5"><small class="text-muted d-block">Check In</small><span class="fw-bold fs-5 text-success"><?= $waktu_in_view ?></span></div>
                                <div class="col-2 d-flex align-items-center justify-content-center"><i class="bi bi-arrow-right text-muted"></i></div>
                                <div class="col-5"><small class="text-muted d-block">Check Out</small><span class="fw-bold fs-5 text-danger"><?= $waktu_out_view ?></span></div>
                            </div>
                            <div class="mt-3 bg-light rounded-3 p-2 text-center text-muted small">
                                <i class="bi bi-stopwatch"></i> Durasi Kerja: <strong class="text-dark"><?= $durasi_teks ?></strong>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">Belum ada riwayat kehadiran tersimpan.</div>
                <?php endif; ?>
            </div>
        </div>

        <div id="screen-layanan" class="app-screen">
            <div class="bg-pink p-3 text-center shadow-sm mb-3" style="border-radius: 0 0 20px 20px;">
                <h5 class="mb-0 text-white fw-bold mt-2 pb-2">Layanan HRIS</h5>
            </div>
            <div class="p-3">
                <?php if ($is_admin): ?>
                <h6 class="section-title mt-0 text-pink"><i class="bi bi-shield-lock-fill me-2"></i>Menu HR & Owner</h6>
                <div class="row g-3 mb-4">
                    <div class="col-12" onclick="switchScreen('admin-absen')">
                        <div class="action-card shadow-sm p-3 bg-white rounded-3 d-flex align-items-center cursor-pointer" style="border-left:5px solid var(--lb-pink); cursor:pointer;">
                            <i class="bi bi-people-fill text-pink fs-3 me-3"></i>
                            <div class="flex-grow-1"><h6 class="fw-bold mb-0">Kehadiran Karyawan</h6><small class="text-muted">Pantau absensi seluruh tim & foto</small></div>
                            <i class="bi bi-chevron-right ms-auto text-muted"></i>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <h6 class="section-title mt-0">Pengajuan & Dokumen</h6>
                <div class="row g-3">
                    <div class="col-12" onclick="showModernAlert('Informasi Cuti','Status Anda <b><?= $karyawan['status_pegawai'] ?></b>.<br>Cuti tahunan baru dapat digunakan setelah <b><?= formatTanggal($karyawan['akhir_probation']) ?></b>.<br><br><span class=\'text-pink fw-bold\'>Fitur Izin Sakit akan segera hadir.</span>','bi bi-calendar-x-fill','var(--lb-pink)')">
                        <div class="action-card shadow-sm p-3 bg-white rounded-3 d-flex align-items-center" style="cursor:pointer;">
                            <i class="bi bi-calendar-event text-dark fs-3 me-3"></i>
                            <div><h6 class="fw-bold mb-0">Pengajuan Cuti / Izin</h6><small class="text-muted">Cek kuota dan ajukan libur</small></div>
                        </div>
                    </div>
                    <div class="col-12" onclick="aksesGajiDitolak()">
                        <div class="action-card shadow-sm p-3 bg-light rounded-3 d-flex align-items-center" style="cursor:pointer;">
                            <i class="bi bi-lock-fill text-secondary fs-3 me-3"></i>
                            <div><h6 class="fw-bold mb-0 text-secondary">Slip Gaji <span class="badge bg-secondary ms-2" style="font-size:10px;">Terkunci</span></h6><small class="text-muted">Akses melalui portal HRD/Finance</small></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($is_admin): ?>
        <div id="screen-admin-absen" class="app-screen">
            
            <div id="admin-view-list">
                <div class="bg-pink p-3 position-relative shadow-sm text-center mb-3" style="border-radius: 0 0 20px 20px;">
                    <button class="btn btn-link text-white position-absolute top-50 translate-middle-y start-0 ms-2" onclick="switchScreen('layanan')"><i class="bi bi-arrow-left fs-3"></i></button>
                    <h5 class="mb-0 text-white fw-bold mt-2 pb-2">Pilih Karyawan</h5>
                </div>
                <div class="p-3 pt-0">
                    <p class="text-muted small mb-3">Ketuk nama karyawan untuk melihat riwayat absensinya.</p>
                    <?php foreach ($semua_karyawan as $kar): ?>
                    <div class="card employee-card rounded-4 mb-3 shadow-sm" onclick="showEmployeeLog('<?= $kar['nik'] ?>', '<?= htmlspecialchars($kar['nama']) ?>')">
                        <div class="card-body p-3 d-flex align-items-center gap-3">
                            <div class="avatar-initials bg-pink text-white d-flex align-items-center justify-content-center rounded-circle fw-bold" style="width: 45px; height: 45px; font-size: 18px;">
                                <?= $kar['inisial'] ?>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="fw-bold mb-1 text-dark"><?= $kar['nama'] ?></h6>
                                <div class="text-muted small mb-1"><i class="bi bi-briefcase me-1"></i><?= $kar['posisi'] ?></div>
                                <span class="badge bg-warning text-dark" style="font-size: 10px;"><?= $kar['status_pegawai'] ?></span>
                            </div>
                            <i class="bi bi-chevron-right text-muted"></i>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="admin-view-detail" style="display:none;">
                <div class="bg-pink p-3 position-relative shadow-sm text-center mb-3" style="border-radius: 0 0 20px 20px;">
                    <button class="btn btn-link text-white position-absolute top-50 translate-middle-y start-0 ms-2" onclick="backToAdminList()"><i class="bi bi-arrow-left fs-3"></i></button>
                    <h5 class="mb-0 text-white fw-bold mt-2 pb-2" id="detail-nama-karyawan">Log Kehadiran</h5>
                </div>
                <div class="p-3 pt-0">
                    
                    <div class="card border-0 shadow-sm rounded-4 mb-3 bg-light">
                        <div class="card-body p-3">
                            <h6 class="small fw-bold text-muted mb-2"><i class="bi bi-funnel-fill me-1"></i> Filter Tanggal</h6>
                            <div class="d-flex gap-2">
                                <div class="flex-fill">
                                    <small class="text-muted" style="font-size:10px;">Dari</small>
                                    <input type="date" id="filter-start" class="form-control form-control-sm border-0 shadow-sm rounded-3">
                                </div>
                                <div class="flex-fill">
                                    <small class="text-muted" style="font-size:10px;">Sampai</small>
                                    <input type="date" id="filter-end" class="form-control form-control-sm border-0 shadow-sm rounded-3">
                                </div>
                                <div class="d-flex align-items-end">
                                    <button class="btn btn-sm btn-pink shadow-sm rounded-3 px-3 h-100" onclick="renderAdminTable()"><i class="bi bi-search"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive bg-white rounded-4 shadow-sm border" style="overflow: hidden;">
                        <table class="table table-hover table-admin align-middle mb-0" style="font-size: 13px;">
                            <thead class="table-light">
                                <tr>
                                    <th class="px-3 py-3">Tanggal</th>
                                    <th class="text-center py-3">Masuk</th>
                                    <th class="text-center py-3">Pulang</th>
                                </tr>
                            </thead>
                            <tbody id="admin-detail-tbody">
                                </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
        <?php endif; ?>

        <div id="screen-profil" class="app-screen">
            <div class="bg-pink p-4 pb-5 text-center shadow-sm" style="border-radius: 0 0 20px 20px;">
                <div class="avatar-initials mx-auto mt-2 mb-2 bg-white text-pink d-flex align-items-center justify-content-center rounded-circle fw-bold" style="width:80px; height:80px; font-size:32px;"><?= $inisial ?></div>
                <h4 class="text-white fw-bold mb-0"><?= $karyawan['nama'] ?></h4>
                <p class="text-white-50 mb-0"><?= $karyawan['posisi'] ?></p>
            </div>
            <div class="p-3" style="margin-top:-30px;">
                <div class="card border-0 shadow-sm rounded-4 mb-3">
                    <div class="card-body p-0">
                        <div class="p-3 pb-0"><h6 class="section-title mt-0">Informasi Pribadi</h6></div>
                        <ul class="list-group list-group-flush rounded-4">
                            <li class="list-group-item d-flex justify-content-between p-3"><span class="text-muted">NIK</span><span class="fw-bold"><?= $karyawan['nik'] ?></span></li>
                            <li class="list-group-item d-flex justify-content-between p-3"><span class="text-muted">Email</span><span class="fw-bold small text-end"><?= $karyawan['email'] ?></span></li>
                            <li class="list-group-item d-flex justify-content-between p-3"><span class="text-muted">No. HP</span><span class="fw-bold"><?= $karyawan['no_hp'] ?></span></li>
                            <li class="list-group-item d-flex justify-content-between p-3"><span class="text-muted">Tgl Bergabung</span><span class="fw-bold"><?= formatTanggal($karyawan['tgl_bergabung']) ?></span></li>
                        </ul>
                    </div>
                </div>
                <a href="/logout" class="btn btn-outline-danger w-100 rounded-4 py-3 fw-bold mt-3 shadow-sm">
                    <i class="bi bi-box-arrow-right me-2"></i> Keluar Aplikasi
                </a>
            </div>
        </div>

    </div> </div> <div class="modal fade" id="modalDetailAbsen" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="modal-title fw-bold">Detail Attendance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
                <p class="text-pink small fw-bold mb-3" id="mdl-header">Nama Karyawan • Tanggal</p>
                <div id="mdl-status-box" class="status-box hadir mb-3 shadow-sm">
                    <div class="d-flex align-items-center gap-2">
                        <i id="mdl-status-icon" class="bi bi-check-circle-fill fs-4"></i>
                        <span class="fw-bold fs-5" id="mdl-status-text">Hadir</span>
                    </div>
                    <div class="text-end">
                        <small class="d-block opacity-75">Jam Kerja</small>
                        <span class="fw-bold" id="mdl-durasi">-</span>
                    </div>
                </div>
                <ul class="nav nav-pills nav-fill bg-light p-1 rounded-3 mb-3 shadow-sm" id="pills-tab" role="tablist">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#pills-in" type="button"><i class="bi bi-box-arrow-in-right me-1"></i> Check In</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#pills-out" type="button"><i class="bi bi-box-arrow-right me-1"></i> Check Out</button></li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="pills-in">
                        <div class="detail-box"><h6 class="fw-bold small text-muted"><i class="bi bi-clock me-2"></i>Waktu Check In</h6><div class="d-flex justify-content-between text-success fw-bold fs-5"><span>Pukul</span><span id="mdl-in-time">00:00</span></div></div>
                        <div class="detail-box"><h6 class="fw-bold small text-muted"><i class="bi bi-geo-alt me-2"></i>Lokasi Absen</h6><p class="mb-0 small fw-bold" id="mdl-in-lokasi">-</p></div>
                        <div class="detail-box border-0 p-0 overflow-hidden text-center bg-light" style="border-radius:15px;">
                            <img id="mdl-in-foto" src="" alt="Foto Check In" style="width:100%; height:auto; max-height:500px; object-fit:contain;" onerror="this.src='https://placehold.co/300x400?text=Tidak+Ada+Foto'">
                        </div>
                    </div>
                    <div class="tab-pane fade" id="pills-out">
                        <div class="detail-box"><h6 class="fw-bold small text-muted"><i class="bi bi-clock me-2"></i>Waktu Check Out</h6><div class="d-flex justify-content-between text-danger fw-bold fs-5"><span>Pukul</span><span id="mdl-out-time">00:00</span></div></div>
                        <div class="detail-box"><h6 class="fw-bold small text-muted"><i class="bi bi-geo-alt me-2"></i>Lokasi Absen</h6><p class="mb-0 small fw-bold" id="mdl-out-lokasi">-</p></div>
                        <div class="detail-box border-0 p-0 overflow-hidden text-center bg-light" style="border-radius:15px;">
                            <img id="mdl-out-foto" src="" alt="Foto Check Out" style="width:100%; height:auto; max-height:500px; object-fit:contain;" onerror="this.src='https://placehold.co/300x400?text=Tidak+Ada+Foto'">
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const userNIK = '<?= $karyawan['nik'] ?>';
    const screens = ['beranda','riwayat','layanan','profil','admin-absen'];

    function switchScreen(target) {
        screens.forEach(s => {
            const el = document.getElementById('screen-'+s);
            const nav = document.getElementById('nav-'+s);
            if (el) el.classList.remove('active');
            if (nav) nav.classList.remove('active');
        });
        const t = document.getElementById('screen-'+target);
        const n = document.getElementById('nav-'+target);
        if (t) t.classList.add('active');
        if (n) n.classList.add('active');
        window.scrollTo(0,0);
    }

    function showModernAlert(title, message, iconClass, iconColor) {
        document.getElementById('modernAlertTitle').innerText = title;
        document.getElementById('modernAlertMessage').innerHTML = message;
        document.getElementById('modernAlertIcon').innerHTML = `<i class="${iconClass}" style="font-size:3.5rem; color:${iconColor};"></i>`;
        new bootstrap.Modal(document.getElementById('modernAlertModal')).show();
    }

    function aksesGajiDitolak() {
        showModernAlert('Akses Terkunci','Slip gaji bersifat rahasia dan saat ini hanya dapat diakses melalui portal HRD / Finance.','bi bi-lock-fill','#6c757d');
    }

    // =======================================================
    // LOGIKA ADMIN: FILTER & TAMPILAN KARTU KE TABEL
    // =======================================================
    const adminHistData = <?= json_encode($admin_hist_arr) ?>;
    let selectedNikAdmin = null;
    let selectedNamaAdmin = null;

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

    function formatTanggalIndoJS(tglStr) {
        if (!tglStr) return '-';
        const bln = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        const p = tglStr.split('-');
        return p[2] + ' ' + bln[parseInt(p[1])-1] + ' ' + p[0];
    }

    window.bukaDetailDariAdmin = function(idx) {
        const data = filteredDataAdmin[idx]; 
        
        let durasi_teks = '-';
        if (data.in_time && data.out_time) {
            const inDate = new Date(`1970-01-01T${data.in_time}Z`);
            const outDate = new Date(`1970-01-01T${data.out_time}Z`);
            const diffMs = outDate - inDate;
            if(diffMs > 0) {
                const diffHrs = Math.floor(diffMs / 3600000);
                const diffMins = Math.floor((diffMs % 3600000) / 60000);
                durasi_teks = `${diffHrs} jam ${diffMins} menit`;
            }
        }

        const modalData = {
            tanggal: formatTanggalIndoJS(data.tgl),
            nama: data.nama,
            status: data.status_in || 'Tidak Hadir',
            durasi: durasi_teks,
            in_time: data.in_time ? data.in_time.substring(11, 16) : '-',
            out_time: data.out_time ? data.out_time.substring(11, 16) : '-',
            in_lokasi: data.lok_in || 'Tidak ada data lokasi',
            out_lokasi: data.lok_out || 'Tidak ada data lokasi',
            in_foto: data.foto_in || '',
            out_foto: data.foto_out || ''
        };
        bukaDetail(modalData);
    };

    let filteredDataAdmin = []; 

    function renderAdminTable() {
        const tbody = document.getElementById('admin-detail-tbody');
        tbody.innerHTML = ''; 
        
        const start = document.getElementById('filter-start').value;
        const end = document.getElementById('filter-end').value;

        filteredDataAdmin = adminHistData.filter(d => d.nik === selectedNikAdmin);

        if (start) filteredDataAdmin = filteredDataAdmin.filter(d => d.tgl >= start);
        if (end) filteredDataAdmin = filteredDataAdmin.filter(d => d.tgl <= end);

        if (filteredDataAdmin.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center py-5 text-muted"><i class="bi bi-folder-x fs-1 d-block mb-2"></i>Tidak ada data absensi di rentang tanggal ini.</td></tr>';
            return;
        }

        let htmlRows = '';
        filteredDataAdmin.forEach((data, index) => {
            const inTimeView = data.in_time ? data.in_time.substring(11, 16) : '-';
            const outTimeView = data.out_time ? data.out_time.substring(11, 16) : '-';
            
            const statusIn = data.status_in || 'Tidak Hadir';
            let badgeBg = 'bg-success';
            if (statusIn === 'Telat') badgeBg = 'bg-warning text-dark';
            if (statusIn === 'Tidak Hadir') badgeBg = 'bg-danger';

            const tglParts = data.tgl.split('-');
            const ddmmyyyy = `${tglParts[2]}/${tglParts[1]}/${tglParts[0]}`;

            htmlRows += `
                <tr onclick="bukaDetailDariAdmin(${index})" style="cursor:pointer;">
                    <td class="px-3 py-2">
                        <span class="fw-bold d-block text-dark"><i class="bi bi-calendar-check me-1 text-pink"></i> ${ddmmyyyy}</span>
                        <span class="badge ${badgeBg} rounded-pill mt-1" style="font-size:9px;">${statusIn}</span>
                    </td>
                    <td class="text-center text-success fw-bold">${inTimeView}</td>
                    <td class="text-center text-danger fw-bold">${outTimeView}</td>
                </tr>
            `;
        });
        
        tbody.innerHTML = htmlRows;
    }

    // =======================================================
    // FUNGSI TAMPIL DETAIL MODAL
    // =======================================================
    function bukaDetail(data) {
        document.getElementById('mdl-header').innerText = data.nama + ' • ' + data.tanggal;
        
        const boxStatus = document.getElementById('mdl-status-box');
        const iconStatus = document.getElementById('mdl-status-icon');
        
        document.getElementById('mdl-status-text').innerText = data.status;
        document.getElementById('mdl-durasi').innerText = data.durasi;
        
        if (data.status === 'Telat') {
            boxStatus.className = 'status-box terlambat mb-3 shadow-sm';
            iconStatus.className = 'bi bi-exclamation-circle-fill fs-4';
            document.getElementById('mdl-status-text').innerText = 'Terlambat';
        } else if (data.status === 'Hadir') {
            boxStatus.className = 'status-box hadir mb-3 shadow-sm';
            iconStatus.className = 'bi bi-check-circle-fill fs-4';
        } else {
            boxStatus.className = 'status-box mb-3 shadow-sm bg-light text-secondary border';
            iconStatus.className = 'bi bi-x-circle-fill fs-4';
        }
        
        document.getElementById('mdl-in-time').innerText    = data.in_time;
        document.getElementById('mdl-out-time').innerText   = data.out_time;
        document.getElementById('mdl-in-lokasi').innerText  = data.in_lokasi;
        document.getElementById('mdl-out-lokasi').innerText = data.out_lokasi;
        
        const inFotoEl = document.getElementById('mdl-in-foto');
        if (data.in_foto && data.in_foto !== '' && data.in_foto !== 'NULL' && data.in_foto !== '-') {
            inFotoEl.src = data.in_foto;
        } else {
            inFotoEl.src = 'https://placehold.co/300x400?text=Tidak+Ada+Foto';
        }

        const outFotoEl = document.getElementById('mdl-out-foto');
        if (data.out_foto && data.out_foto !== '' && data.out_foto !== 'NULL' && data.out_foto !== '-') {
            outFotoEl.src = data.out_foto;
        } else {
            outFotoEl.src = 'https://placehold.co/300x400?text=Tidak+Ada+Foto';
        }
        
        new bootstrap.Modal(document.getElementById('modalDetailAbsen')).show();
    }

    // Kamera
    const video = document.getElementById('kamera');
    const canvas = document.getElementById('canvas_kamera');
    let kameraAktif = false;
    if (video) {
        navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" } })
            .then(stream => { video.srcObject = stream; kameraAktif = true; })
            .catch(() => {
                const w = document.getElementById('camera-wrapper');
                if (w) w.innerHTML = '<div class="d-flex align-items-center justify-content-center h-100 bg-light"><small class="text-danger fw-bold text-center px-2">Kamera tidak diizinkan / tidak ditemukan.</small></div>';
            });
    }

    function kirimAbsen(jenis) {
        if (!confirm("Apakah Anda yakin ingin melakukan " + jenis + " sekarang?")) return;
        const responseDiv = document.getElementById('absen-response');
        responseDiv.innerText = "Mengambil data dan mengirim absen...";
        
        const formData = new FormData();
        formData.append('jenis_absen', jenis);
        formData.append('nik', userNIK);
        
        if (kameraAktif && video && canvas) {
            try {
                canvas.width = video.videoWidth || 300;
                canvas.height = video.videoHeight || 400;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                const imageData = canvas.toDataURL('image/jpeg', 0.8);
                formData.append('foto', imageData);
            } catch (err) {
                formData.append('foto', '');
            }
        } else {
            formData.append('foto', '');
        }

        fetch('/proses_absen', { method: 'POST', body: formData })
            .then(res => res.text())
            .then(data => { responseDiv.innerText = data; setTimeout(() => location.reload(), 1500); })
            .catch(() => { responseDiv.innerText = "Terjadi kesalahan saat memproses absensi."; });
    }

    // Jam & Tanggal
    setInterval(() => {
        const now = new Date();
        if (document.getElementById('clock-display'))
            document.getElementById('clock-display').innerText = now.toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit', second:'2-digit' }) + ' WIB';
        if (document.getElementById('date-display'))
            document.getElementById('date-display').innerText = now.toLocaleDateString('id-ID', { weekday:'long', day:'numeric', month:'long', year:'numeric' });
    }, 1000);
</script>
</body>
</html>