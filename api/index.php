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
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    
    <style>
        /* BASE STYLE */
        :root { --lb-pink: #C94F78; --lb-pink-hover: #A83E60; }
        body { background-color: #f4f6f9; font-family: 'DM Sans', sans-serif; margin: 0; padding: 0; overflow-x: hidden;}
        
        .bg-pink { background-color: var(--lb-pink); }
        .text-pink { color: var(--lb-pink); }
        .btn-pink { background-color: var(--lb-pink); color: white; transition: 0.3s; }
        .btn-pink:hover { background-color: var(--lb-pink-hover); color: white; }

        .app-wrapper { display: flex; flex-direction: column; min-height: 100vh; }
        .main-content { flex: 1; padding-bottom: 75px; width: 100%; }
        .app-screen { display: none; }
        .app-screen.active { display: block; }

        /* NAVIGASI BAWAH / SIDEBAR */
        .sidebar-nav {
            position: fixed; bottom: 0; left: 0; width: 100%; background-color: #fff;
            display: flex; justify-content: space-around; padding: 10px 0;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.05); z-index: 999;
        }
        .sidebar-logo { display: none; }
        .nav-item-btn {
            background: transparent; border: none; color: #6c757d; display: flex;
            flex-direction: column; align-items: center; font-size: 12px; font-weight: 500;
            width: 100%; outline: none; transition: 0.2s;
        }
        .nav-item-btn i { font-size: 22px; margin-bottom: 3px; }
        .nav-item-btn.active, .nav-item-btn.active i { color: var(--lb-pink); }

        /* KUSTOMISASI KAMERA BLACK BOX */
        .camera-box {
            background-color: #000; border-radius: 16px; overflow: hidden;
            width: 100%; max-width: 450px; aspect-ratio: 3/4; /* Proporsi HP Potrait */
            display: flex; justify-content: center; align-items: center; position: relative;
            margin: 0 auto; box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .camera-box video, .camera-box img {
            width: 100%; height: 100%; object-fit: contain; /* FOTO TIDAK TERPOTONG */
        }

        /* TABLE RIWAYAT (DESAIN BARU) */
        .table-riwayat th { font-size: 13px; color: #6c757d; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #eee; }
        .table-riwayat td { font-size: 14px; vertical-align: middle; color: #333; border-bottom: 1px solid #eee; }
        
        /* BADGE STATUS WARNA-WARNI */
        .badge-status { padding: 6px 12px; font-size: 11px; font-weight: 700; border-radius: 6px; letter-spacing: 0.3px; }
        .bg-terlambat { background-color: #fd7e14; color: #fff; } /* Orange */
        .bg-hadir { background-color: #198754; color: #fff; } /* Hijau Tua */
        .bg-checkin { background-color: #20c997; color: #fff; } /* Hijau Muda/Cyan */
        .bg-libur { background-color: #6f42c1; color: #fff; } /* Ungu */
        .bg-izin { background-color: #0d6efd; color: #fff; } /* Biru */
        .bg-absen { background-color: #dc3545; color: #fff; } /* Merah */
        
        /* MODAL TABS (DESAIN BARU) */
        .nav-pills.custom-tabs { background-color: #fff; border-bottom: 2px solid #eee; border-radius: 0; padding: 0; }
        .nav-pills.custom-tabs .nav-link { color: #6c757d; border-radius: 0; font-weight: 600; padding: 12px 10px; border-bottom: 3px solid transparent; transition: 0.3s; }
        .nav-pills.custom-tabs .nav-link.active { background-color: transparent; color: #198754; border-bottom-color: #198754; }
        .detail-box { background-color: #fff; border: 1px solid #eee; border-radius: 12px; margin-bottom: 15px; }

        /* KARTU AKTIVITAS HARI INI */
        .activity-box { background: #fff; border: 1px solid #eaeaea; border-radius: 15px; padding: 15px; height: 100%; display: flex; flex-direction: column; justify-content: space-between; }
        .table-admin td { vertical-align: middle; }
        .employee-card { transition: transform 0.2s; border: 1px solid #f0f0f0; cursor: pointer; }
        .employee-card:active { transform: scale(0.98); background-color: #f8f9fa; }

        /* RESPONSIVE LAYOUT (DESKTOP) */
        @media (min-width: 992px) {
            .app-wrapper { flex-direction: row; }
            .sidebar-nav {
                position: fixed; top: 0; left: 0; width: 250px; height: 100vh;
                flex-direction: column; justify-content: flex-start; padding: 30px 0;
                border-right: 1px solid #e0e0e0; box-shadow: 2px 0 15px rgba(0,0,0,0.03);
            }
            .sidebar-logo { display: block; text-align: center; margin-bottom: 40px; padding: 0 20px; }
            .nav-item-btn { flex-direction: row; padding: 15px 30px; font-size: 15px; justify-content: flex-start; gap: 15px; border-right: 4px solid transparent; }
            .nav-item-btn i { font-size: 20px; margin-bottom: 0; }
            .nav-item-btn:hover { background-color: #fdf0f5; color: var(--lb-pink); }
            .nav-item-btn.active { background-color: #fdf0f5; border-right-color: var(--lb-pink); }
            
            .main-content { margin-left: 250px; width: calc(100% - 250px); padding-bottom: 30px; }
            .desktop-px { padding-left: 50px !important; padding-right: 50px !important; }
            .bg-pink { border-radius: 0 0 40px 40px !important; padding-top: 40px !important; padding-bottom: 60px !important; }
            .overlap-card { max-width: 450px; }
            
            .camera-box { aspect-ratio: 16/9; max-width: 600px; } /* Berubah jadi Landscape di Layar Besar */
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
            
            <div class="bg-pink p-4 desktop-px shadow-sm" style="border-radius: 0 0 25px 25px;">
                <div class="d-flex justify-content-between align-items-center mx-auto mb-2" style="max-width:1200px;">
                    <div>
                        <p class="mb-0 text-white-50" id="date-display" style="font-size:13px;">Memuat tanggal...</p>
                        <h4 class="mb-0 fw-bold mt-1 text-white">Hai, <?= htmlspecialchars($karyawan['nama']) ?>!</h4>
                    </div>
                    <i class="bi bi-bell fs-4 text-white"></i>
                </div>
            </div>

            <div class="p-3 desktop-px mx-auto" style="max-width: 1200px;">
                
                <div class="card border-0 shadow-sm rounded-4 mb-4 overlap-card" style="margin-top:-35px; z-index: 10; position:relative;">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="avatar-initials bg-pink text-white d-flex align-items-center justify-content-center rounded-circle fw-bold" style="width: 50px; height: 50px; font-size: 20px;">
                            <?= $inisial ?>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1 text-dark"><?= $karyawan['posisi'] ?></h6>
                            <span class="badge bg-warning text-dark"><?= $karyawan['status_pegawai'] ?></span>
                            <span class="badge bg-secondary"><?= $karyawan['penempatan'] ?></span>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-2">
                    <h1 class="display-3 fw-bold text-pink" id="clock-display">00:00:00</h1>
                    <div class="alert alert-success d-inline-flex align-items-center gap-2 rounded-pill px-4 py-2 mt-2 mb-4 shadow-sm border-0">
                        <i class="bi bi-geo-alt-fill"></i> Lokasi Kerja: <strong><?= $karyawan['penempatan'] ?></strong>
                    </div>

                    <?php if ($show_camera): ?>
                        <div class="mb-2">
                            <small class="text-muted d-block mb-2">Pastikan wajah terlihat jelas sebelum absen.</small>
                            
                            <div class="camera-box">
                                <video id="kamera" autoplay playsinline></video>
                                <img id="kamera-preview" style="display:none;" />
                                <canvas id="canvas_kamera" style="display:none;"></canvas>
                            </div>
                        </div>

                        <div id="absen-response" class="my-3 fw-bold text-primary"></div>

                        <div id="btn-action-group" class="d-flex justify-content-center gap-3 mb-5 mx-auto mt-4" style="max-width: 450px;">
                            <?php if ($jenis_absen_sekarang == 'Check In'): ?>
                                <button class="btn btn-success flex-fill rounded-4 py-3 fw-bold shadow-sm" onclick="ambilFoto('Check In')">
                                    <i class="bi bi-camera-fill me-2"></i> Ambil Foto Check In
                                </button>
                                <button class="btn btn-secondary flex-fill rounded-4 py-3 fw-bold shadow-sm opacity-50" disabled>
                                    <i class="bi bi-box-arrow-right me-2"></i> Check Out
                                </button>
                            <?php else: ?>
                                <button class="btn btn-secondary flex-fill rounded-4 py-3 fw-bold shadow-sm opacity-50" disabled>
                                    <i class="bi bi-check-circle-fill me-2"></i> Sudah Check In
                                </button>
                                <button class="btn btn-danger flex-fill rounded-4 py-3 fw-bold shadow-sm" onclick="ambilFoto('Check Out')">
                                    <i class="bi bi-camera-fill me-2"></i> Ambil Foto Check Out
                                </button>
                            <?php endif; ?>
                        </div>

                        <div id="btn-confirm-group" class="d-flex justify-content-center gap-3 mb-5 mx-auto mt-4" style="max-width: 450px; display:none !important;">
                            <button class="btn btn-outline-secondary flex-fill rounded-4 py-3 fw-bold" onclick="batalFoto()">
                                Ulangi (Batal)
                            </button>
                            <button class="btn btn-pink flex-fill rounded-4 py-3 fw-bold shadow-sm" onclick="submitAbsen()">
                                <span id="confirm-text">Kirim Absen</span> <i class="bi bi-send-fill ms-2"></i>
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-light text-center p-5 rounded-4 mb-4 mx-auto shadow-sm border" style="max-width: 400px;">
                            <?php if (!$sudah_in && $belum_waktunya_in): ?>
                                <i class="bi bi-clock-history text-warning" style="font-size: 3rem;"></i>
                                <h5 class="fw-bold mt-3 text-dark">Belum Waktunya</h5>
                                <p class="mb-0 text-muted">Check In baru bisa dilakukan mulai pukul 08:30 WIB.</p>
                            <?php elseif (!$sudah_in && $lewat_batas_in): ?>
                                <i class="bi bi-x-circle text-danger" style="font-size: 3rem;"></i>
                                <h5 class="fw-bold mt-3 text-danger">Terlambat Check In</h5>
                                <p class="mb-0 text-muted">Batas Check In (13:00 WIB) telah terlewat hari ini.</p>
                            <?php elseif ($sudah_in && !$sudah_out && $belum_waktunya_out): ?>
                                <i class="bi bi-briefcase text-primary" style="font-size: 3rem;"></i>
                                <h5 class="fw-bold mt-3 text-dark">Selamat Bekerja!</h5>
                                <p class="mb-0 text-muted">Check Out baru bisa dilakukan mulai pukul 18:00 WIB.</p>
                            <?php elseif ($sudah_out): ?>
                                <i class="bi bi-calendar2-check text-success" style="font-size: 3rem;"></i>
                                <h5 class="fw-bold mt-3 text-dark">Absensi Selesai</h5>
                                <p class="mb-0 text-muted">Terima kasih, selamat beristirahat!</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div> <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
                    <h6 class="section-title mb-0 mt-0 text-dark fw-bold">Aktivitas Hari Ini</h6>
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
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="activity-box bg-light border-0">
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
                            <div class="col-md-6">
                                <div class="activity-box bg-light border-0">
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
            <div class="bg-white p-4 desktop-px d-flex justify-content-between align-items-center border-bottom sticky-top" style="z-index: 10;">
                <h4 class="mb-0 text-dark fw-bold">Riwayat Kehadiran</h4>
                <button class="btn btn-outline-secondary btn-sm rounded-pill px-3 fw-bold"><i class="bi bi-funnel"></i> Filter</button>
            </div>
            
            <div class="p-3 desktop-px mx-auto mt-2" style="max-width: 1200px;">
                <div class="table-responsive bg-white rounded-4 shadow-sm border" style="overflow: hidden;">
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
                                    
                                    // Logika Status untuk Badge
                                    $status_in = $data['status_in'] ?: 'Tidak Hadir';
                                    $badge_class = 'bg-hadir';
                                    
                                    if ($status_in == 'Telat') {
                                        $status_in = 'Terlambat'; // Ubah teks agar sesuai gambar
                                        $badge_class = 'bg-terlambat';
                                    } elseif ($status_in == 'Tidak Hadir') {
                                        $badge_class = 'bg-absen';
                                    } elseif ($data['in_time'] && !$data['out_time']) {
                                        // Jika hari ini sudah masuk tapi belum pulang
                                        $status_in = 'Check In';
                                        $badge_class = 'bg-checkin';
                                    }

                                    // Siapkan data untuk modal
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
                                    <td>
                                        <i class="bi bi-clock text-success me-1"></i> <?= $w_in ?> <span class="text-muted mx-1">&mdash;</span> <?= $w_out ?>
                                    </td>
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
            <div class="bg-pink p-4 desktop-px shadow-sm text-center mb-4" style="border-radius: 0 0 25px 25px;">
                <h4 class="mb-0 text-white fw-bold mt-2 pb-2">Layanan HRIS</h4>
            </div>
            <div class="p-3 desktop-px mx-auto" style="max-width: 1200px;">
                <?php if ($is_admin): ?>
                <h6 class="section-title mt-0 text-pink fw-bold"><i class="bi bi-shield-lock-fill me-2"></i>Menu HR & Owner</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6 col-lg-4" onclick="switchScreen('admin-absen')">
                        <div class="action-card shadow-sm p-4 bg-white rounded-4 d-flex align-items-center employee-card" style="border-left:6px solid var(--lb-pink);">
                            <i class="bi bi-people-fill text-pink fs-1 me-3"></i>
                            <div class="flex-grow-1"><h6 class="fw-bold mb-0 fs-5 text-dark">Kehadiran Tim</h6><small class="text-muted">Pantau absensi & foto</small></div>
                            <i class="bi bi-chevron-right ms-auto text-muted fs-5"></i>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <h6 class="section-title mt-0 fw-bold">Pengajuan & Dokumen</h6>
                <div class="row g-3">
                    <div class="col-md-6 col-lg-4" onclick="showModernAlert('Informasi Cuti','Status Anda <b><?= $karyawan['status_pegawai'] ?></b>.<br>Cuti tahunan baru dapat digunakan setelah <b><?= formatTanggal($karyawan['akhir_probation']) ?></b>.<br><br><span class=\'text-pink fw-bold\'>Fitur Izin Sakit akan segera hadir.</span>','bi bi-calendar-x-fill','var(--lb-pink)')">
                        <div class="action-card shadow-sm p-4 bg-white rounded-4 d-flex align-items-center employee-card">
                            <i class="bi bi-calendar-event text-dark fs-1 me-3"></i>
                            <div><h6 class="fw-bold mb-0 fs-5 text-dark">Pengajuan Cuti</h6><small class="text-muted">Cek kuota dan ajukan libur</small></div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4" onclick="aksesGajiDitolak()">
                        <div class="action-card shadow-sm p-4 bg-light rounded-4 d-flex align-items-center employee-card">
                            <i class="bi bi-lock-fill text-secondary fs-1 me-3"></i>
                            <div><h6 class="fw-bold mb-0 text-secondary fs-5">Slip Gaji <span class="badge bg-secondary ms-2" style="font-size:10px;">Terkunci</span></h6><small class="text-muted">Akses portal Finance</small></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($is_admin): ?>
        <div id="screen-admin-absen" class="app-screen">
            
            <div id="admin-view-list">
                <div class="bg-pink p-4 desktop-px position-relative shadow-sm text-center mb-4" style="border-radius: 0 0 25px 25px;">
                    <button class="btn btn-link text-white position-absolute top-50 translate-middle-y start-0 ms-md-4 ms-2" onclick="switchScreen('layanan')"><i class="bi bi-arrow-left fs-3"></i></button>
                    <h4 class="mb-0 text-white fw-bold mt-2 pb-2">Pilih Karyawan</h4>
                </div>
                <div class="p-3 desktop-px mx-auto" style="max-width: 1200px;">
                    <p class="text-muted small mb-3">Ketuk nama karyawan untuk melihat riwayat absensinya.</p>
                    <div class="row g-3">
                        <?php foreach ($semua_karyawan as $kar): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card employee-card rounded-4 h-100 shadow-sm" onclick="showEmployeeLog('<?= $kar['nik'] ?>', '<?= htmlspecialchars($kar['nama']) ?>')">
                                <div class="card-body p-3 d-flex align-items-center gap-3">
                                    <div class="avatar-initials bg-pink text-white d-flex align-items-center justify-content-center rounded-circle fw-bold" style="width: 50px; height: 50px; font-size: 20px;">
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
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div id="admin-view-detail" style="display:none;">
                <div class="bg-pink p-4 desktop-px position-relative shadow-sm text-center mb-4" style="border-radius: 0 0 25px 25px;">
                    <button class="btn btn-link text-white position-absolute top-50 translate-middle-y start-0 ms-md-4 ms-2" onclick="backToAdminList()"><i class="bi bi-arrow-left fs-3"></i></button>
                    <h4 class="mb-0 text-white fw-bold mt-2 pb-2" id="detail-nama-karyawan">Log Kehadiran</h4>
                </div>
                <div class="p-3 desktop-px mx-auto" style="max-width: 1200px;">
                    
                    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-light">
                        <div class="card-body p-3 p-md-4">
                            <h6 class="small fw-bold text-muted mb-2"><i class="bi bi-funnel-fill me-1"></i> Filter Tanggal</h6>
                            <div class="d-flex flex-wrap gap-2 gap-md-4">
                                <div class="flex-fill">
                                    <small class="text-muted" style="font-size:11px;">Dari Tanggal</small>
                                    <input type="date" id="filter-start" class="form-control form-control-lg border-0 shadow-sm rounded-3 fs-6">
                                </div>
                                <div class="flex-fill">
                                    <small class="text-muted" style="font-size:11px;">Sampai Tanggal</small>
                                    <input type="date" id="filter-end" class="form-control form-control-lg border-0 shadow-sm rounded-3 fs-6">
                                </div>
                                <div class="d-flex align-items-end mt-2 mt-md-0 w-100 w-md-auto">
                                    <button class="btn btn-lg btn-pink shadow-sm rounded-3 px-4 w-100" onclick="renderAdminTable()"><i class="bi bi-search me-2"></i>Cari</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive bg-white rounded-4 shadow-sm border" style="overflow: hidden;">
                        <table class="table table-hover table-admin align-middle mb-0" style="font-size: 14px;">
                            <thead class="table-light">
                                <tr>
                                    <th class="px-4 py-3">Tanggal & Status</th>
                                    <th class="text-center py-3">Jam Masuk</th>
                                    <th class="text-center py-3">Jam Pulang</th>
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
            <div class="bg-pink p-5 desktop-px text-center shadow-sm" style="border-radius: 0 0 25px 25px;">
                <div class="avatar-initials mx-auto mt-2 mb-3 bg-white text-pink d-flex align-items-center justify-content-center rounded-circle fw-bold shadow" style="width:100px; height:100px; font-size:38px;"><?= $inisial ?></div>
                <h3 class="text-white fw-bold mb-1"><?= htmlspecialchars($karyawan['nama']) ?></h3>
                <p class="text-white-50 mb-0 fs-5"><?= $karyawan['posisi'] ?></p>
            </div>
            <div class="p-3 desktop-px mx-auto" style="margin-top:-30px; max-width: 800px;">
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-0">
                        <div class="p-4 pb-0"><h6 class="section-title mt-0 text-muted fw-bold">Informasi Pribadi</h6></div>
                        <ul class="list-group list-group-flush rounded-4">
                            <li class="list-group-item d-flex justify-content-between p-4"><span class="text-muted">NIK</span><span class="fw-bold"><?= $karyawan['nik'] ?></span></li>
                            <li class="list-group-item d-flex justify-content-between p-4"><span class="text-muted">Email</span><span class="fw-bold text-end"><?= $karyawan['email'] ?></span></li>
                            <li class="list-group-item d-flex justify-content-between p-4"><span class="text-muted">No. Handphone</span><span class="fw-bold"><?= $karyawan['no_hp'] ?></span></li>
                            <li class="list-group-item d-flex justify-content-between p-4"><span class="text-muted">Tgl Bergabung</span><span class="fw-bold"><?= formatTanggal($karyawan['tgl_bergabung']) ?></span></li>
                        </ul>
                    </div>
                </div>
                <a href="/logout" class="btn btn-outline-danger w-100 rounded-4 py-3 fw-bold mt-3 shadow-sm fs-5">
                    <i class="bi bi-box-arrow-right me-2"></i> Keluar Aplikasi
                </a>
            </div>
        </div>

    </div> </div> <div class="modal fade" id="modalDetailAbsen" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content rounded-4 border-0">
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
                        <button class="nav-link active w-100" data-bs-toggle="pill" data-bs-target="#pills-in" type="button">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Check In
                        </button>
                    </li>
                    <li class="nav-item flex-fill text-center">
                        <button class="nav-link w-100" data-bs-toggle="pill" data-bs-target="#pills-out" type="button">
                            <i class="bi bi-box-arrow-right me-1"></i> Check Out
                        </button>
                    </li>
                    <li class="nav-item flex-fill text-center">
                        <button class="nav-link w-100" data-bs-toggle="pill" data-bs-target="#pills-data" type="button">
                            <i class="bi bi-person me-1"></i> Data Karyawan
                        </button>
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
                            <div class="avatar-initials bg-pink text-white d-flex align-items-center justify-content-center rounded-circle fw-bold mx-auto mb-3" style="width: 80px; height: 80px; font-size: 30px;">
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
    // LOGIKA ADMIN FILTER (TETAP SAMA)
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
            
            let statusIn = data.status_in || 'Tidak Hadir';
            let badgeBg = 'bg-hadir';
            if (statusIn === 'Telat') { statusIn = 'Terlambat'; badgeBg = 'bg-terlambat'; }
            if (statusIn === 'Tidak Hadir') badgeBg = 'bg-absen';
            if (data.in_time && !data.out_time) { statusIn = 'Check In'; badgeBg = 'bg-checkin'; }

            const tglParts = data.tgl.split('-');
            const ddmmyyyy = `${tglParts[2]}/${tglParts[1]}/${tglParts[0]}`;

            htmlRows += `
                <tr onclick="bukaDetailDariAdmin(${index})" style="cursor:pointer;">
                    <td class="px-4 py-3">
                        <span class="fw-bold d-block text-dark mb-1"><i class="bi bi-calendar-check me-2 text-pink"></i> ${ddmmyyyy}</span>
                        <span class="badge-status ${badgeBg}">${statusIn}</span>
                    </td>
                    <td class="text-center text-success fw-bold fs-6">${inTimeView}</td>
                    <td class="text-center text-danger fw-bold fs-6">${outTimeView}</td>
                </tr>
            `;
        });
        tbody.innerHTML = htmlRows;
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

        let stat = data.status_in || 'Tidak Hadir';
        if(stat === 'Telat') stat = 'Terlambat';
        if(data.in_time && !data.out_time) stat = 'Check In';

        const tglParts = data.tgl.split('-');
        const bln = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        const formatTgl = tglParts[2] + ' ' + bln[parseInt(tglParts[1])-1] + ' ' + tglParts[0];

        const modalData = {
            tanggal: formatTgl,
            nama: data.nama,
            status: stat,
            durasi: durasi_teks,
            in_time: data.in_time ? data.in_time.substring(11, 16) : '-',
            out_time: data.out_time ? data.out_time.substring(11, 16) : '-',
            in_lokasi: data.lok_in || 'Tidak ada data lokasi',
            out_lokasi: data.lok_out || 'Tidak ada data lokasi',
            in_foto: data.foto_in || '',
            out_foto: data.foto_out || '',
            penempatan: 'HO'
        };
        bukaDetail(modalData);
    };

    // =======================================================
    // LOGIKA KAMERA: AMBIL, PREVIEW & BATAL (NEW)
    // =======================================================
    const video = document.getElementById('kamera');
    const preview = document.getElementById('kamera-preview');
    const canvas = document.getElementById('canvas_kamera');
    let kameraAktif = false;
    let fotoDataURL = '';
    let absenJenisType = '';

    if (video) {
        navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" } })
            .then(stream => { video.srcObject = stream; kameraAktif = true; })
            .catch(() => {
                const w = document.querySelector('.camera-box');
                if (w) w.innerHTML = '<div class="d-flex align-items-center justify-content-center h-100 bg-dark w-100"><small class="text-danger fw-bold text-center px-2">Kamera Ditolak / Tidak Ditemukan.</small></div>';
            });
    }

    function ambilFoto(jenis) {
        if (!kameraAktif) { alert("Kamera tidak aktif. Izinkan akses kamera browser Anda."); return; }
        
        absenJenisType = jenis;
        
        // Jepret Frame Asli (Landscape/Portrait otomatis terbaca)
        canvas.width = video.videoWidth || 480;
        canvas.height = video.videoHeight || 640;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        fotoDataURL = canvas.toDataURL('image/jpeg', 0.8);

        // Ubah Tampilan ke Preview
        video.style.display = 'none';
        preview.src = fotoDataURL;
        preview.style.display = 'block';

        // Ganti Tombol
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
        
        // Sembunyikan tombol agar tidak di-klik 2x
        document.getElementById('btn-confirm-group').style.setProperty('display', 'none', 'important');

        const formData = new FormData();
        formData.append('jenis_absen', absenJenisType);
        formData.append('nik', userNIK);
        formData.append('foto', fotoDataURL);

        fetch('/proses_absen', { method: 'POST', body: formData })
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
        
        // Logika Warna Kotak Atas Modal
        let badgeWFO = `<span class="badge bg-success fw-normal ms-2" style="font-size:10px;">${data.penempatan || 'WFO'}</span>`;
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
        } else if (data.status === 'Tidak Hadir') {
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
        
        document.getElementById('mdl-in-time').innerText    = data.in_time;
        document.getElementById('mdl-out-time').innerText   = data.out_time;
        
        // Detail Lokasi (Simulasi IP dan Koordinat yang statis untuk UI)
        document.getElementById('mdl-in-lokasi').innerText  = `Jalan Alam Kurnia, ${data.in_lokasi}`;
        document.getElementById('mdl-out-lokasi').innerText = `Jalan Alam Kurnia, ${data.out_lokasi}`;
        
        const inFotoEl = document.getElementById('mdl-in-foto');
        if (data.in_foto && data.in_foto !== '' && data.in_foto !== 'NULL') { inFotoEl.src = data.in_foto; } 
        else { inFotoEl.src = 'https://placehold.co/400x500?text=Tidak+Ada+Foto'; }

        const outFotoEl = document.getElementById('mdl-out-foto');
        if (data.out_foto && data.out_foto !== '' && data.out_foto !== 'NULL') { outFotoEl.src = data.out_foto; } 
        else { outFotoEl.src = 'https://placehold.co/400x500?text=Tidak+Ada+Foto'; }
        
        // Buka tab Check In duluan
        const tabIn = new bootstrap.Tab(document.querySelector('#pills-tab button[data-bs-target="#pills-in"]'));
        tabIn.show();

        new bootstrap.Modal(document.getElementById('modalDetailAbsen')).show();
    }

    // JAM
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